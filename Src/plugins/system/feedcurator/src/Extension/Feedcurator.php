<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_system_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\Feedcurator\Extension;

use HKweb\Plugin\System\Feedcurator\Review\PageCachePolicy;
use HKweb\Plugin\System\Feedcurator\Review\ReviewArticle;
use HKweb\Plugin\System\Feedcurator\Review\ReviewDecision;
use HKweb\Plugin\System\Feedcurator\Review\ReviewOutcome;
use HKweb\Plugin\System\Feedcurator\Review\ReviewToken;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\PageCache\IsExcludedEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\CurrentUserInterface;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryAwareInterface;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Review page for imported feed items: a board member publishes or discards an item from a mail link.
 *
 * GET only shows the article and a form, so a link scanner that opens the link changes nothing. A POST with the
 * same signed fields and a form token publishes (state 1) or trashes (state -2) the article through the
 * com_content article model, under the identity of the user the link was signed for.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Feedcurator extends CMSPlugin implements SubscriberInterface, UserFactoryAwareInterface
{
	use DatabaseAwareTrait;
	use UserFactoryAwareTrait;

	/**
	 * Language keys for the article states.
	 *
	 * @var    array<int, string>
	 * @since  __DEPLOY_VERSION__
	 */
	private const STATES = [
		0  => 'PLG_SYSTEM_FEEDCURATOR_STATE_CONCEPT',
		1  => 'PLG_SYSTEM_FEEDCURATOR_STATE_PUBLISHED',
		2  => 'PLG_SYSTEM_FEEDCURATOR_STATE_ARCHIVED',
		-2 => 'PLG_SYSTEM_FEEDCURATOR_STATE_TRASHED',
	];

	/**
	 * Returns the events this plugin listens to.
	 *
	 * @return  array<string, string>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAjaxFeedcurator'     => 'review',
			'onPageCacheIsExcluded' => 'excludeFromPageCache',
		];
	}

	/**
	 * Keeps the review page out of System - Page Cache: the page holds a form token, and the cache ignores
	 * Cache-Control: no-store, so a link scanner's visit would hand its token to the real recipient.
	 *
	 * @param   IsExcludedEvent  $event  The page cache event; receives true as a result for the review page.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function excludeFromPageCache(IsExcludedEvent $event): void
	{
		$input = $this->getApplication()->getInput();

		if (PageCachePolicy::isExcluded($input->getCmd('option'), $input->getCmd('plugin')))
		{
			$event->addResult(true);
		}
	}

	/**
	 * Handles index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a=&u=&e=&s=.
	 *
	 * com_ajax echoes the result into the component area, so with format=html the page renders in the site template.
	 *
	 * @param   AjaxEvent  $event  The com_ajax event; receives the HTML of the page as its result.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function review(AjaxEvent $event): void
	{
		$app = $this->getApplication();

		if (!$app->isClient('site'))
		{
			return;
		}

		$this->loadLanguage();

		$app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
		$app->setHeader('Cache-Control', 'no-store', true);

		$document = $app->getDocument();

		if ($document instanceof HtmlDocument)
		{
			$document->setTitle(Text::_('PLG_SYSTEM_FEEDCURATOR_PAGE_TITLE'));
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		$event->addResult($this->render($this->handle($app)));
	}

	/**
	 * Works out the page for the request. Only a POST with outcome Act changes anything.
	 *
	 * @param   CMSApplicationInterface  $app  The site application.
	 *
	 * @return  array<string, mixed>  The display data for tmpl/review.php.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function handle(CMSApplicationInterface $app): array
	{
		$input     = $app->getInput();
		$articleId = $input->getInt('a');
		$userId    = $input->getInt('u');
		$expires   = $input->getInt('e');
		$signature = $input->getCmd('s');
		$isPost    = $input->getMethod() === 'POST';
		$action    = $isPost ? $input->post->getCmd('action') : null;

		$token   = new ReviewToken((string) $app->get('secret'));
		$valid   = $articleId > 0 && $userId > 0 && $token->isGenuine($articleId, $userId, $expires, $signature);
		$expired = time() > $expires;
		$user    = null;
		$article = null;

		if ($valid && !$expired)
		{
			$user = $this->getUserFactory()->loadUserById($userId);

			if ((int) $user->id !== $userId || (int) $user->block !== 0)
			{
				$valid = false;
			}
			else
			{
				$article = new Content($this->getDatabase());

				if (!$article->load($articleId))
				{
					return $this->page([$this->message('error', 'PLG_SYSTEM_FEEDCURATOR_NOT_FOUND')], 404);
				}
			}
		}

		$state   = $article !== null ? (int) $article->state : 0;
		$outcome = ReviewDecision::decide($valid, $expired, $state, $isPost, $action === '' ? null : $action);

		switch ($outcome)
		{
			case ReviewOutcome::Invalid:
				return $this->page([$this->message('error', 'PLG_SYSTEM_FEEDCURATOR_INVALID')], 403);

			case ReviewOutcome::Expired:
				return $this->page([$this->message('warning', 'PLG_SYSTEM_FEEDCURATOR_EXPIRED')], 410);

			case ReviewOutcome::AlreadyDone:
				return $this->page([$this->doneMessage($state)], 200, $this->articleData($article), null, $this->resultLinks($article, $state));

			case ReviewOutcome::Show:
				return $this->page([], 200, $this->articleData($article), [
					'action'   => Uri::base(true) . '/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html',
					'a'        => $articleId,
					'u'        => $userId,
					'e'        => $expires,
					's'        => $signature,
					'actingAs' => Text::sprintf('PLG_SYSTEM_FEEDCURATOR_ACTING_AS', $user->name),
				]);

			case ReviewOutcome::Act:
			default:
				return $this->act($app, $article, $user, (string) $action);
		}
	}

	/**
	 * Publishes or discards the article as the user of the link (POST only, after the decision said Act).
	 *
	 * @param   CMSApplicationInterface  $app      The site application.
	 * @param   Content                  $article  The concept article.
	 * @param   User                     $user     The user the link was signed for.
	 * @param   string                   $action   'publish' or 'discard'.
	 *
	 * @return  array<string, mixed>  The display data.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function act(CMSApplicationInterface $app, Content $article, User $user, string $action): array
	{
		if (!Session::checkToken())
		{
			return $this->page([$this->message('error', 'PLG_SYSTEM_FEEDCURATOR_TOKEN')], 403, $this->articleData($article));
		}

		$id    = (int) $article->id;
		$value = $action === 'publish' ? 1 : -2;

		if (!$user->authorise('core.edit.state', 'com_content.article.' . $id))
		{
			return $this->page([$this->message('error', 'PLG_SYSTEM_FEEDCURATOR_NO_RIGHTS')], 403, $this->articleData($article));
		}

		/*
		 * The change runs as the user of the link, so Joomla checks their rights and the action log records their name.
		 * The action log only logs changes made from a loggable extension (the request option), so the request
		 * passes for com_content while the model runs.
		 */
		$input          = $app->getInput();
		$previousOption = $input->getCmd('option');
		$previous       = $app->getIdentity();
		$app->loadIdentity($user);
		$input->set('option', 'com_content');

		try
		{
			$model = $app->bootComponent('com_content')->getMVCFactory()
				->createModel('Article', 'Administrator', ['ignore_request' => true]);

			if ($model instanceof CurrentUserInterface)
			{
				$model->setCurrentUser($user);
			}

			$pks   = [$id];
			$done  = (bool) $model->publish($pks, $value);
			$error = $done ? '' : (string) $model->getError();
		}
		catch (\Throwable $e)
		{
			// With com_content workflows on, the workflow plugin refuses a direct state change with an exception.
			$done  = false;
			$error = $e->getMessage();
		}
		finally
		{
			$input->set('option', $previousOption);
			$app->loadIdentity($previous);
		}

		// publish() also returns true when it skipped the article (checked out by someone else): compare the state.
		$article->reset();
		$article->load($id);

		if (!$done || (int) $article->state !== $value)
		{
			$messages = [];

			if ($error !== '')
			{
				$messages[] = ['type' => 'error', 'text' => $error];
			}
			elseif ($done)
			{
				$messages[] = $this->message('error', 'PLG_SYSTEM_FEEDCURATOR_NOT_CHANGED');
			}

			$messages[] = $this->message('warning', 'PLG_SYSTEM_FEEDCURATOR_USE_ADMIN');

			return $this->page($messages, 200, $this->articleData($article), null, [$this->adminLink($id)]);
		}

		return $this->page(
			[$this->message('success', $value === 1 ? 'PLG_SYSTEM_FEEDCURATOR_PUBLISHED' : 'PLG_SYSTEM_FEEDCURATOR_DISCARDED')],
			200,
			$this->articleData($article),
			null,
			$this->resultLinks($article, $value)
		);
	}

	/**
	 * The message for an article that is no longer a concept.
	 *
	 * @param   integer  $state  The state of the article.
	 *
	 * @return  array{type: string, text: string}
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function doneMessage(int $state): array
	{
		return match ($state) {
			1       => $this->message('info', 'PLG_SYSTEM_FEEDCURATOR_DONE_PUBLISHED'),
			-2      => $this->message('info', 'PLG_SYSTEM_FEEDCURATOR_DONE_TRASHED'),
			default => ['type' => 'info', 'text' => Text::sprintf('PLG_SYSTEM_FEEDCURATOR_DONE_OTHER', $this->stateLabel($state))],
		};
	}

	/**
	 * Links after a change or for an article that is done: the article on the site when published.
	 *
	 * @param   Content  $article  The article.
	 * @param   integer  $state    Its state.
	 *
	 * @return  list<array{url: string, text: string}>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function resultLinks(Content $article, int $state): array
	{
		if ($state !== 1)
		{
			return [];
		}

		return [[
			'url'  => Route::_(RouteHelper::getArticleRoute((int) $article->id, (int) $article->catid, (string) $article->language), false),
			'text' => Text::_('PLG_SYSTEM_FEEDCURATOR_VIEW_ARTICLE'),
		]];
	}

	/**
	 * The link to the article in the administrator.
	 *
	 * @param   integer  $id  The article id.
	 *
	 * @return  array{url: string, text: string}
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function adminLink(int $id): array
	{
		return [
			'url'  => Uri::root() . 'administrator/index.php?option=com_content&task=article.edit&id=' . $id,
			'text' => Text::_('PLG_SYSTEM_FEEDCURATOR_ADMIN_LINK'),
		];
	}

	/**
	 * The article as plain-text parts for the template.
	 *
	 * @param   ?Content  $article  The article.
	 *
	 * @return  ?array{title: string, state: string, summary: string, source: string, link: string, linkText: string}
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function articleData(?Content $article): ?array
	{
		if ($article === null)
		{
			return null;
		}

		return ['title' => (string) $article->title, 'state' => $this->stateLabel((int) $article->state)]
			+ ReviewArticle::parse((string) $article->introtext);
	}

	/**
	 * The label of an article state.
	 *
	 * @param   integer  $state  The state.
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function stateLabel(int $state): string
	{
		return Text::_(self::STATES[$state] ?? 'PLG_SYSTEM_FEEDCURATOR_STATE_OTHER');
	}

	/**
	 * A message from a language key.
	 *
	 * @param   string  $type  error, warning, info or success.
	 * @param   string  $key   The language key.
	 *
	 * @return  array{type: string, text: string}
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function message(string $type, string $key): array
	{
		return ['type' => $type, 'text' => Text::_($key)];
	}

	/**
	 * Display data for the template; also sets the HTTP status.
	 *
	 * @param   list<array{type: string, text: string}>  $messages  The messages.
	 * @param   integer                                  $status    The HTTP status.
	 * @param   ?array<string, string>                   $article   The article parts, or null.
	 * @param   ?array<string, mixed>                    $form      The form fields, or null for no form.
	 * @param   list<array{url: string, text: string}>   $links     Links below the messages.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function page(array $messages, int $status = 200, ?array $article = null, ?array $form = null, array $links = []): array
	{
		if ($status !== 200)
		{
			$this->getApplication()->setHeader('status', (string) $status, true);
		}

		return [
			'heading'  => Text::_('PLG_SYSTEM_FEEDCURATOR_PAGE_TITLE'),
			'messages' => $messages,
			'article'  => $article,
			'form'     => $form,
			'links'    => $links,
		];
	}

	/**
	 * Renders tmpl/review.php (a template override in html/plg_system_feedcurator/review.php wins).
	 *
	 * @param   array<string, mixed>  $displayData  The display data.
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function render(array $displayData): string
	{
		$app = $this->getApplication();

		ob_start();

		try
		{
			(static function (string $file, array $displayData) use ($app): void {
				extract($displayData, EXTR_SKIP);
				include $file;
			})(PluginHelper::getLayoutPath('system', 'feedcurator', 'review'), $displayData);
		}
		finally
		{
			$html = (string) ob_get_clean();
		}

		return $html;
	}
}
