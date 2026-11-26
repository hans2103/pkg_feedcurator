<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_task_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Feedcurator\Import;

use HKweb\Plugin\Task\Feedcurator\Feed\ArticleMapper;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedException;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedItem;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedParser;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedUnavailableException;
use HKweb\Plugin\Task\Feedcurator\Feed\KeywordFilter;
use HKweb\Plugin\Task\Feedcurator\Feed\Source;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Version;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Imports on-topic feed items into com_content as unpublished articles.
 *
 * The table #__feedcurator_items remembers every imported item, so an item never comes back, not even
 * when the editors delete the article. The table #__feedcurator_sources remembers which sources have
 * had their one-time look-back (first_run_weeks).
 *
 * @since  __DEPLOY_VERSION__
 */
final class Importer
{
	/**
	 * Writes a line to the task log.
	 *
	 * @var    \Closure(string, string): void
	 * @since  __DEPLOY_VERSION__
	 */
	private readonly \Closure $log;

	/**
	 * Constructor.
	 *
	 * @param   CMSApplicationInterface  $app    The application, for the event dispatcher and the workflow.
	 * @param   DatabaseInterface        $db     The database.
	 * @param   HttpFactory              $http   Creates the HTTP client for the feeds.
	 * @param   ?\Closure                $log    Receives a message and a level (info, warning, error); default: no logging.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(
		private readonly CMSApplicationInterface $app,
		private readonly DatabaseInterface $db,
		private readonly HttpFactory $http,
		?\Closure $log = null,
	) {
		$this->log = $log ?? static function (string $message, string $level): void {
		};
	}

	/**
	 * Runs the import with the settings of the task.
	 *
	 * @param   Registry  $params  The task settings.
	 * @param   integer   $taskId  The scheduled task, to remember the look-back per source.
	 *
	 * @return  ImportResult
	 *
	 * @throws  FeedException  When the settings are incomplete.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function run(Registry $params, int $taskId): ImportResult
	{
		$sources = $this->sources($params);

		if ($sources === [])
		{
			throw new FeedException('Geen actieve bron ingesteld.');
		}

		if ((int) $params->get('catid', 0) === 0)
		{
			throw new FeedException('Geen categorie ingesteld.');
		}

		if (trim((string) $params->get('site_url', '')) === '')
		{
			throw new FeedException('Geen site-URL ingesteld.');
		}

		$result      = new ImportResult;
		$parser      = new FeedParser;
		$now         = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$general     = KeywordFilter::parseList((string) $params->get('keywords', ''));
		$maxAge      = max(1, (int) $params->get('max_age_days', 30));
		$firstWeeks  = max(0, (int) $params->get('first_run_weeks', 0));
		$maxPerRun   = max(1, (int) $params->get('max_per_run', 10));
		$candidates  = [];
		$lookedBack  = [];

		foreach ($sources as $source)
		{
			try
			{
				$items = $parser->parse($this->fetch($source));
			}
			catch (FeedUnavailableException $e)
			{
				$result->unavailable[] = $source->name;
				($this->log)(\sprintf('Bron %s niet beschikbaar: %s', $source->name, $e->getMessage()), 'warning');

				continue;
			}

			$lookBack = $firstWeeks > 0 && !$this->hasLookedBack($taskId, $source);
			$cutoff   = $now->modify('-' . ($lookBack ? $firstWeeks * 7 : $maxAge) . ' days');
			$filter   = $source->filter ? new KeywordFilter(array_merge($general, $source->keywords), $source->exclude) : null;

			if ($lookBack)
			{
				$lookedBack[$source->url] = $source;
			}

			foreach ($items as $item)
			{
				if (($item->published ?? $now) < $cutoff)
				{
					continue;
				}

				if ($filter !== null && !$filter->matches($item))
				{
					$result->skipped++;

					continue;
				}

				$key = $item->key();

				if (isset($candidates[$key]))
				{
					// The same message from a second source: one article.
					$candidates[$key]['lookback'] = $candidates[$key]['lookback'] || $lookBack;

					continue;
				}

				$candidates[$key] = ['item' => $item, 'source' => $source, 'lookback' => $lookBack];
			}
		}

		$known = $this->knownKeys(array_keys($candidates));

		foreach (array_keys($candidates) as $key)
		{
			if (isset($known[$key]))
			{
				$result->known++;
				unset($candidates[$key]);
			}
		}

		uasort(
			$candidates,
			fn (array $a, array $b): int => ($a['item']->published ?? $now) <=> ($b['item']->published ?? $now)
		);

		$selected = [];
		$regular  = 0;

		foreach ($candidates as $key => $candidate)
		{
			if (!$candidate['lookback'] && ++$regular > $maxPerRun)
			{
				continue;
			}

			$selected[$key] = $candidate;
		}

		$failedSources = [];
		$mapper        = new ArticleMapper;
		$author        = (int) $params->get('created_by', 0) ?: $this->fallbackAuthor();

		foreach ($selected as $key => $candidate)
		{
			/** @var FeedItem $item */
			$item = $candidate['item'];
			/** @var Source $source */
			$source = $candidate['source'];

			try
			{
				$article = $this->import($item, $source, $key, $mapper, (int) $params->get('catid'), $author);
			}
			catch (\Throwable $e)
			{
				$result->failed++;
				$failedSources[$source->url] = true;
				($this->log)(\sprintf('Item %s uit %s niet opgeslagen: %s', $item->link ?: $item->guid, $source->name, $e->getMessage()), 'error');

				continue;
			}

			if ($article === null)
			{
				$result->skipped++;

				continue;
			}

			$result->created[] = ['id' => $article['id'], 'title' => $article['title'], 'source' => $source->name];
		}

		// Only a source that was fetched and stored without a failure has had its look-back.
		foreach ($lookedBack as $url => $source)
		{
			if (!isset($failedSources[$url]))
			{
				$this->markLookedBack($taskId, $source);
			}
		}

		return $result;
	}

	/**
	 * The enabled sources with a name and an address.
	 *
	 * @param   Registry  $params  The task settings.
	 *
	 * @return  list<Source>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function sources(Registry $params): array
	{
		$sources = [];

		foreach ((array) $params->get('sources', []) as $row)
		{
			$source = Source::fromArray((array) $row);

			if ($source->enabled && $source->url !== '' && $source->name !== '')
			{
				$sources[] = $source;
			}
		}

		return $sources;
	}

	/**
	 * Fetches a feed.
	 *
	 * @param   Source  $source  The source.
	 *
	 * @return  string  The body.
	 *
	 * @throws  FeedUnavailableException  When the feed can't be reached or doesn't answer with HTTP 200.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function fetch(Source $source): string
	{
		$options = new Registry;
		$options->set('userAgent', (new Version)->getUserAgent('Joomla', true, false));

		try
		{
			$response = $this->http->getHttp($options)->get($source->url, [], 30);
		}
		catch (\Throwable $e)
		{
			throw new FeedUnavailableException('Geen verbinding: ' . $e->getMessage(), 0, $e);
		}

		if ($response->getStatusCode() !== 200)
		{
			throw new FeedUnavailableException('HTTP ' . $response->getStatusCode());
		}

		return (string) $response->getBody();
	}

	/**
	 * Whether the one-time look-back of this source was done in this task.
	 *
	 * @param   integer  $taskId  The scheduled task.
	 * @param   Source   $source  The source.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function hasLookedBack(int $taskId, Source $source): bool
	{
		$hash  = hash('sha256', $source->url);
		$query = $this->db->createQuery()
			->select('1')
			->from($this->db->quoteName('#__feedcurator_sources'))
			->where($this->db->quoteName('task_id') . ' = :taskId')
			->where($this->db->quoteName('url_hash') . ' = :hash')
			->bind(':taskId', $taskId, ParameterType::INTEGER)
			->bind(':hash', $hash);

		return (bool) $this->db->setQuery($query)->loadResult();
	}

	/**
	 * Remembers that the one-time look-back of this source is done.
	 *
	 * @param   integer  $taskId  The scheduled task.
	 * @param   Source   $source  The source.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function markLookedBack(int $taskId, Source $source): void
	{
		$row = (object) [
			'task_id'   => $taskId,
			'url_hash'  => hash('sha256', $source->url),
			'first_run' => gmdate('Y-m-d H:i:s'),
		];

		try
		{
			$this->db->insertObject('#__feedcurator_sources', $row);
		}
		catch (\Throwable $e)
		{
			($this->log)('Terugblik van ' . $source->name . ' niet vastgelegd: ' . $e->getMessage(), 'warning');
		}
	}

	/**
	 * The keys among the given ones that were imported before.
	 *
	 * @param   list<string|int>  $keys  Item keys.
	 *
	 * @return  array<string, true>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function knownKeys(array $keys): array
	{
		$known = [];

		foreach (array_chunk(array_map('strval', $keys), 200) as $chunk)
		{
			$query = $this->db->createQuery()
				->select($this->db->quoteName('item_key'))
				->from($this->db->quoteName('#__feedcurator_items'))
				->whereIn($this->db->quoteName('item_key'), $chunk, ParameterType::STRING);

			foreach ($this->db->setQuery($query)->loadColumn() as $key)
			{
				$known[(string) $key] = true;
			}
		}

		return $known;
	}

	/**
	 * Saves one item as an unpublished article and remembers it.
	 *
	 * @param   FeedItem       $item    The item.
	 * @param   Source         $source  Its source.
	 * @param   string         $key     The item key.
	 * @param   ArticleMapper  $mapper  The mapper.
	 * @param   integer        $catid   The category.
	 * @param   integer        $author  The author.
	 *
	 * @return  ?array{id: int, title: string}  The article, or null when the item can't become one.
	 *
	 * @throws  \RuntimeException  When the article can't be saved.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function import(FeedItem $item, Source $source, string $key, ArticleMapper $mapper, int $catid, int $author): ?array
	{
		if (!preg_match('#^https?://#i', $item->link))
		{
			($this->log)('Item zonder bruikbare link overgeslagen: ' . $item->title, 'warning');

			return null;
		}

		$created = $mapper->map($item, $source, '');

		if ($created['title'] === '')
		{
			return null;
		}

		$sourceLine = Text::sprintf(
			'PLG_TASK_FEEDCURATOR_SOURCE_LINE',
			htmlspecialchars($source->name, ENT_QUOTES, 'UTF-8'),
			HTMLHelper::date($created['created'], Text::_('DATE_FORMAT_LC3')),
			htmlspecialchars($item->link, ENT_QUOTES, 'UTF-8')
		);
		$data = $mapper->map($item, $source, $sourceLine);

		$article             = new Content($this->db);
		$article->title      = $data['title'];
		$article->alias      = $this->uniqueAlias($data['title'], $catid);
		$article->introtext  = $data['introtext'];
		$article->fulltext   = '';
		$article->metadesc   = $data['metadesc'];
		$article->catid      = $catid;
		$article->state      = 0;
		$article->created    = $data['created'];
		$article->publish_up = $data['created'];
		$article->created_by = $author;
		$article->access     = 1;
		$article->language   = '*';
		$article->featured   = 0;
		$article->version    = 1;
		$article->attribs    = '{}';
		$article->urls       = '{}';
		$article->metakey    = '';
		$article->metadata   = '{"robots":"noindex, follow"}';
		$article->images     = '{}';
		$article->note       = $data['note'];

		$this->store($article);

		$row = (object) [
			'item_key'   => $key,
			'source'     => mb_substr($source->url, 0, 255),
			'article_id' => (int) $article->id,
			'imported'   => gmdate('Y-m-d H:i:s'),
		];
		$this->db->insertObject('#__feedcurator_items', $row);

		return ['id' => (int) $article->id, 'title' => $data['title']];
	}

	/**
	 * Checks and stores the article with the content events, as the article model would.
	 *
	 * @param   Content  $article  The new article.
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException  When the article can't be saved or a content plugin stops it.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function store(Content $article): void
	{
		$dispatcher = $this->app->getDispatcher();
		$context    = 'com_content.article';
		$data       = $article->getProperties();

		if (!$article->check())
		{
			throw new \RuntimeException($article->getError());
		}

		$arguments = [
			'context' => $context,
			'subject' => $article,
			'isNew'   => true,
			'data'    => $data,
		];

		$before = $dispatcher->dispatch('onContentBeforeSave', new BeforeSaveEvent('onContentBeforeSave', $arguments));

		if (\in_array(false, $before->getArgument('result', []), true))
		{
			throw new \RuntimeException('Opslaan tegengehouden door een contentplugin.');
		}

		if (!$article->store())
		{
			throw new \RuntimeException($article->getError());
		}

		// Without a workflow association the article is missing from Content > Articles.
		// @phpstan-ignore argument.type (Workflow asks for DatabaseDriver; the plugin gets the driver as DatabaseInterface.)
		$workflow = new Workflow('com_content.article', $this->app, $this->db);
		$workflow->createAssociation((int) $article->id, (int) $workflow->getDefaultStageByCategory((int) $article->catid));

		$dispatcher->dispatch('onContentAfterSave', new AfterSaveEvent('onContentAfterSave', $arguments));
	}

	/**
	 * An alias that is free in the category.
	 *
	 * @param   string   $title  The article title.
	 * @param   integer  $catid  The category id.
	 *
	 * @return  string  The alias.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function uniqueAlias(string $title, int $catid): string
	{
		$unicode = (int) $this->app->get('unicodeslugs', 0) === 1;
		$alias   = $unicode ? OutputFilter::stringUrlUnicodeSlug($title) : OutputFilter::stringURLSafe($title);
		$alias   = $alias !== '' ? $alias : Factory::getDate()->format('Y-m-d-H-i-s');
		$table   = new Content($this->db);

		while ($table->load(['alias' => $alias, 'catid' => $catid]))
		{
			$alias = StringHelper::increment($alias, 'dash');
		}

		return $alias;
	}

	/**
	 * The Super User with the lowest id, as author when the task sets none.
	 *
	 * @return  integer  The user id, or 0 when there is none.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function fallbackAuthor(): int
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName('m.user_id'))
			->from($this->db->quoteName('#__user_usergroup_map', 'm'))
			->where($this->db->quoteName('m.group_id') . ' = 8')
			->order($this->db->quoteName('m.user_id'));

		return (int) $this->db->setQuery($query, 0, 1)->loadResult();
	}
}
