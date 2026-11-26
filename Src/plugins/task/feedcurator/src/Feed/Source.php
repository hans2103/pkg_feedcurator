<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_task_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Feedcurator\Feed;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Settings of one feed source (one row of the sources subform).
 *
 * @since  __DEPLOY_VERSION__
 */
final readonly class Source
{
	/**
	 * @param   string        $name      Display name.
	 * @param   string        $url       Feed URL.
	 * @param   bool          $filter    Whether to apply the keyword filter.
	 * @param   list<string>  $keywords  Terms of which at least one must match.
	 * @param   list<string>  $exclude   Terms that reject an item.
	 * @param   bool          $enabled   Whether the source is imported.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(
		public string $name,
		public string $url,
		public bool $filter,
		public array $keywords,
		public array $exclude,
		public bool $enabled,
	) {
	}

	/**
	 * Builds a Source from a subform row. Missing keys fall back to empty or false.
	 *
	 * @param   array<string, mixed>  $row  Subform row.
	 *
	 * @return  self
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function fromArray(array $row): self
	{
		return new self(
			trim((string) ($row['name'] ?? '')),
			trim((string) ($row['url'] ?? '')),
			self::flag($row['filter'] ?? false),
			KeywordFilter::parseList((string) ($row['keywords'] ?? '')),
			KeywordFilter::parseList((string) ($row['exclude'] ?? '')),
			self::flag($row['enabled'] ?? false),
		);
	}

	/**
	 * @param   mixed  $value  Value from a form: '1', '0', 1, true, ''.
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private static function flag(mixed $value): bool
	{
		return $value === true || $value === 1 || $value === '1';
	}
}
