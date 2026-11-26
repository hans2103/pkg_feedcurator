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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Counts and details of one import run.
 *
 * @since  26.48.0
 */
final class ImportResult
{
	/**
	 * The new unpublished articles.
	 *
	 * @var    list<array{id: int, title: string, source: string}>
	 * @since  26.48.0
	 */
	public array $created = [];

	/**
	 * Items skipped: off topic, without title or without a usable link.
	 *
	 * @var    integer
	 * @since  26.48.0
	 */
	public int $skipped = 0;

	/**
	 * Items that were imported in an earlier run.
	 *
	 * @var    integer
	 * @since  26.48.0
	 */
	public int $known = 0;

	/**
	 * Names of the sources that could not be reached or did not return a feed.
	 *
	 * @var    list<string>
	 * @since  26.48.0
	 */
	public array $unavailable = [];

	/**
	 * Items that could not be saved.
	 *
	 * @var    integer
	 * @since  26.48.0
	 */
	public int $failed = 0;
}
