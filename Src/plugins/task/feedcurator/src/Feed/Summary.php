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
 * Plain-text summary of feed HTML.
 *
 * @since  26.48.0
 */
final class Summary
{
	/**
	 * Strips the WordPress "Het bericht ... verscheen eerst op ..." footer, tags and entities,
	 * collapses whitespace and cuts at the last space before $max characters.
	 *
	 * @param   string  $html  HTML from the feed.
	 * @param   int     $max   Maximum number of characters before the ellipsis.
	 *
	 * @return  string  Plain text, ending in an ellipsis when it was cut.
	 *
	 * @since   26.48.0
	 */
	public static function fromHtml(string $html, int $max = 300): string
	{
		$html = (string) preg_replace('/<p>\s*Het bericht .*? verscheen eerst op .*?<\/p>/isu', ' ', $html);
		$html = (string) preg_replace('~</?(?:p|br|div|li|ul|ol|h[1-6]|tr|td|blockquote)\b[^>]*>~i', ' ', $html);
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));

		if (mb_strlen($text) <= $max)
		{
			return $text;
		}

		$cut   = mb_substr($text, 0, $max);
		$space = mb_strrpos($cut, ' ');

		if ($space !== false && $space > 0)
		{
			$cut = mb_substr($cut, 0, $space);
		}

		return rtrim($cut) . '…';
	}
}
