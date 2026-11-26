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
 * Maps a feed item to the fields of an unpublished article. The article holds no full text:
 * title, summary and a source line.
 *
 * @since  __DEPLOY_VERSION__
 */
final class ArticleMapper
{
	/**
	 * Builds the article fields.
	 *
	 * @param   FeedItem  $item        The feed item.
	 * @param   Source    $source      The source the item came from.
	 * @param   string    $sourceLine  Translated and escaped HTML of the source line.
	 *
	 * @return  array{title: string, introtext: string, metadesc: string, created: string, note: string}
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function map(FeedItem $item, Source $source, string $sourceLine): array
	{
		$summary   = Summary::fromHtml($item->description);
		$introtext = $summary === ''
			? $sourceLine
			: '<p>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p>' . $sourceLine;

		$title = html_entity_decode(strip_tags($item->title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$title = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $title));

		$utc     = new \DateTimeZone('UTC');
		$created = ($item->published ?? new \DateTimeImmutable('now', $utc))->setTimezone($utc);

		return [
			'title'     => $title,
			'introtext' => $introtext,
			// Cut at 159 so that the ellipsis keeps the total within 160 characters.
			'metadesc'  => Summary::fromHtml($item->description, 159),
			'created'   => $created->format('Y-m-d H:i:s'),
			'note'      => mb_substr('feedcurator:' . $source->name, 0, 255),
		];
	}
}
