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
use HKweb\Plugin\Task\Feedcurator\Feed\FeedItem;
use HKweb\Plugin\Task\Feedcurator\Feed\Source;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Collects the items of all sources and picks the ones to import (no Joomla calls).
 *
 * Items that can never become an article (no http(s) link, no title) are rejected on entry, so
 * they never take a slot of max_per_run.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Selector
{
	/**
	 * The candidates by item key.
	 *
	 * @var    array<string, array{item: FeedItem, source: Source, lookback: bool}>
	 * @since  __DEPLOY_VERSION__
	 */
	private array $candidates = [];

	/**
	 * Number of items rejected because they can't become an article.
	 *
	 * @var    integer
	 * @since  __DEPLOY_VERSION__
	 */
	public int $invalid = 0;

	/**
	 * Number of candidates that were known and so left out by select().
	 *
	 * @var    integer
	 * @since  __DEPLOY_VERSION__
	 */
	public int $known = 0;

	/**
	 * Constructor.
	 *
	 * @param   \DateTimeImmutable  $now     The moment of the run, for items without a date.
	 * @param   ArticleMapper       $mapper  The mapper, to see whether an item gets a title.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(private readonly \DateTimeImmutable $now, private readonly ArticleMapper $mapper)
	{
	}

	/**
	 * Adds an item that passed the look-back window and the keyword filter.
	 *
	 * @param   FeedItem  $item      The item.
	 * @param   Source    $source    Its source.
	 * @param   boolean   $lookback  Whether the source is in its one-time look-back.
	 *
	 * @return  boolean  False when the item can't become an article.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function add(FeedItem $item, Source $source, bool $lookback): bool
	{
		if (!preg_match('#^https?://#i', $item->link) || $this->mapper->map($item, $source, '')['title'] === '')
		{
			$this->invalid++;

			return false;
		}

		$key = $item->key();

		if (isset($this->candidates[$key]))
		{
			// The same message from a second source: one article, and a look-back keeps counting.
			$this->candidates[$key]['lookback'] = $this->candidates[$key]['lookback'] || $lookback;

			return true;
		}

		$this->candidates[$key] = ['item' => $item, 'source' => $source, 'lookback' => $lookback];

		return true;
	}

	/**
	 * The keys of the candidates, to look up which are known.
	 *
	 * @return  list<string>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function keys(): array
	{
		return array_map('strval', array_keys($this->candidates));
	}

	/**
	 * The items to import now: not known, oldest first, look-back items always and the others up to the maximum.
	 *
	 * @param   array<string, true>  $known       Keys that were imported before.
	 * @param   integer              $maxPerRun   Maximum of items that are not in a look-back.
	 *
	 * @return  list<array{key: string, item: FeedItem, source: Source, lookback: bool}>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function select(array $known, int $maxPerRun): array
	{
		$rows = [];

		foreach ($this->candidates as $key => $candidate)
		{
			if (isset($known[(string) $key]))
			{
				$this->known++;

				continue;
			}

			$rows[] = ['key' => (string) $key] + $candidate;
		}

		usort(
			$rows,
			fn (array $a, array $b): int => ($a['item']->published ?? $this->now) <=> ($b['item']->published ?? $this->now)
		);

		$selected = [];
		$regular  = 0;

		foreach ($rows as $row)
		{
			if (!$row['lookback'] && ++$regular > $maxPerRun)
			{
				continue;
			}

			$selected[] = $row;
		}

		return $selected;
	}
}
