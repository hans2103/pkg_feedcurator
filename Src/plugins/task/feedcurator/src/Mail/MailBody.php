<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_task_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Feedcurator\Mail;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the HTML and plain text body of the notification mail. Joomla-free.
 *
 * @since  26.48.0
 */
final class MailBody
{
	/**
	 * The HTML body: the intro and a list with one linked item per new article.
	 *
	 * @param   string                                                    $intro      The intro line (plain text, escaped here).
	 * @param   list<array{title: string, source: string, url: string}>  $items      The new articles.
	 * @param   string                                                    $linkLabel  The text of each link.
	 *
	 * @return  string
	 *
	 * @since   26.48.0
	 */
	public static function html(string $intro, array $items, string $linkLabel): string
	{
		$html = '<p>' . self::escape($intro) . '</p><ul>';

		foreach ($items as $item)
		{
			$html .= '<li>' . self::escape($item['title']);

			if ($item['source'] !== '')
			{
				$html .= ' (' . self::escape($item['source']) . ')';
			}

			$html .= '<br><a href="' . self::escape($item['url']) . '">' . self::escape($linkLabel) . '</a></li>';
		}

		return $html . '</ul>';
	}

	/**
	 * The plain text body: the intro, then per article the title and the URL on its own line.
	 *
	 * @param   string                                                    $intro      The intro line.
	 * @param   list<array{title: string, source: string, url: string}>  $items      The new articles.
	 * @param   string                                                    $linkLabel  The text before each link.
	 *
	 * @return  string
	 *
	 * @since   26.48.0
	 */
	public static function text(string $intro, array $items, string $linkLabel): string
	{
		$text = $intro . "\n";

		foreach ($items as $item)
		{
			$text .= "\n- " . $item['title'] . ($item['source'] !== '' ? ' (' . $item['source'] . ')' : '')
				. "\n  " . $linkLabel . ":\n" . $item['url'] . "\n";
		}

		return $text;
	}

	/**
	 * Escapes text for HTML.
	 *
	 * @param   string  $value  The text.
	 *
	 * @return  string
	 *
	 * @since   26.48.0
	 */
	private static function escape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
