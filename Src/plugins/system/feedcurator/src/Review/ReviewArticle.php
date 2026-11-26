<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_system_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\System\Feedcurator\Review;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Splits the introtext of an imported article into plain-text parts for the review page. Joomla-free.
 *
 * The importer writes "<p>{summary}</p><p class="feedcurator-source">{source} — <a href="{link}">{text}</a></p>".
 * Every part comes back as plain text (tags stripped, entities decoded), so the page escapes it like any other value.
 *
 * @since  26.48.0
 */
final class ReviewArticle
{
	/**
	 * Splits the introtext.
	 *
	 * @param   string  $introtext  The introtext of the article.
	 *
	 * @return  array{summary: string, source: string, link: string, linkText: string}  The link is empty unless http(s).
	 *
	 * @since   26.48.0
	 */
	public static function parse(string $introtext): array
	{
		$source   = '';
		$link     = '';
		$linkText = '';

		if (preg_match('~<p\s+class="feedcurator-source"\s*>(.*?)</p>~is', $introtext, $match, PREG_OFFSET_CAPTURE) === 1)
		{
			$line      = $match[1][0];
			$introtext = substr($introtext, 0, $match[0][1]) . substr($introtext, $match[0][1] + \strlen($match[0][0]));

			if (preg_match('~<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>~is', $line, $anchor, PREG_OFFSET_CAPTURE) === 1)
			{
				$href     = self::text($anchor[1][0]);
				$link     = preg_match('~^https?://~i', $href) === 1 ? $href : '';
				$linkText = self::text($anchor[2][0]);
				$line     = substr($line, 0, $anchor[0][1]);
			}

			$source = (string) preg_replace('/[\s\x{2013}\x{2014}-]+$/u', '', self::text($line));
		}

		return [
			'summary'  => self::text($introtext),
			'source'   => $source,
			'link'     => $link,
			'linkText' => $linkText,
		];
	}

	/**
	 * Plain text of an HTML fragment: tags stripped, entities decoded, whitespace collapsed.
	 *
	 * @param   string  $html  The fragment.
	 *
	 * @return  string
	 *
	 * @since   26.48.0
	 */
	private static function text(string $html): string
	{
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

		return trim((string) preg_replace('/\s+/u', ' ', $text));
	}
}
