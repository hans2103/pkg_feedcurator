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
 * One entry of an RSS or Atom feed.
 *
 * @since  __DEPLOY_VERSION__
 */
final readonly class FeedItem
{
	/**
	 * The fields are public and readonly: a FeedItem is a value object.
	 *
	 * @param   string               $guid         Identifier from the feed (guid or Atom id).
	 * @param   string               $link         URL of the original article.
	 * @param   string               $title        Plain-text title.
	 * @param   string               $description  Summary or text as HTML.
	 * @param   list<string>         $categories   Category labels from the feed.
	 * @param   ?\DateTimeImmutable  $published    Publication date, when the feed gives one.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(
		public string $guid,
		public string $link,
		public string $title,
		public string $description,
		public array $categories,
		public ?\DateTimeImmutable $published,
	) {
	}

	/**
	 * Stable key to recognise an item that was seen before: the normalised link
	 * (trimmed, scheme and host in lowercase, no fragment), or the guid when there is no link.
	 *
	 * @return  string  SHA-256 hash in hexadecimal.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function key(): string
	{
		$link = trim($this->link);

		if ($link === '')
		{
			return hash('sha256', $this->guid);
		}

		$hash = strpos($link, '#');

		if ($hash !== false)
		{
			$link = substr($link, 0, $hash);
		}

		$link = (string) preg_replace_callback(
			'~^([a-z][a-z0-9+.-]*)://([^/?]*)~i',
			static fn (array $m): string => strtolower($m[1]) . '://' . strtolower($m[2]),
			$link
		);

		return hash('sha256', $link);
	}
}
