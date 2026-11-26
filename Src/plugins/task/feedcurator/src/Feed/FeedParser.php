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
 * Reads an RSS 2.0 or Atom feed into FeedItem objects.
 *
 * @since  26.48.0
 */
final class FeedParser
{
	/**
	 * Atom namespace.
	 *
	 * @var    string
	 * @since  26.48.0
	 */
	private const ATOM = 'http://www.w3.org/2005/Atom';

	/**
	 * Parses a feed.
	 *
	 * @param   string  $xml  The feed as received.
	 *
	 * @return  list<FeedItem>  Items without a title, or without both link and guid, are left out.
	 *
	 * @throws  FeedUnavailableException  When the body is neither an RSS channel nor an Atom feed.
	 *
	 * @since   26.48.0
	 */
	public function parse(string $xml): array
	{
		$previous = libxml_use_internal_errors(true);

		try
		{
			// LIBXML_NONET: never fetch anything; without LIBXML_NOENT entities are not substituted.
			$root = simplexml_load_string($xml, \SimpleXMLElement::class, \LIBXML_NONET | \LIBXML_NOCDATA);
		}
		finally
		{
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}

		if (!$root instanceof \SimpleXMLElement)
		{
			throw new FeedUnavailableException('Geen geldige RSS- of Atom-feed ontvangen.');
		}

		$items = [];

		if (isset($root->channel))
		{
			foreach ($root->channel->item as $node)
			{
				$items[] = $this->rssItem($node);
			}
		}
		elseif ($root->getName() === 'feed')
		{
			foreach ($root->children(self::ATOM)->entry as $node)
			{
				$items[] = $this->atomItem($node);
			}
		}
		else
		{
			throw new FeedUnavailableException('Geen geldige RSS- of Atom-feed ontvangen.');
		}

		return array_values(array_filter($items, static fn (?FeedItem $item): bool => $item !== null));
	}

	/**
	 * One RSS <item> as FeedItem.
	 *
	 * @param   \SimpleXMLElement  $node  The <item> element.
	 *
	 * @return  ?FeedItem  Null when the item has no title, or neither link nor guid.
	 *
	 * @since   26.48.0
	 */
	private function rssItem(\SimpleXMLElement $node): ?FeedItem
	{
		$categories = [];

		foreach ($node->category as $category)
		{
			$categories[] = (string) $category;
		}

		return $this->build(
			$this->text($node, 'guid'),
			$this->text($node, 'link'),
			$this->text($node, 'title'),
			$this->text($node, 'description'),
			$categories,
			$this->text($node, 'pubDate')
		);
	}

	/**
	 * One Atom <entry> as FeedItem.
	 *
	 * @param   \SimpleXMLElement  $node  The <entry> element.
	 *
	 * @return  ?FeedItem  Null when the entry has no title, or neither link nor id.
	 *
	 * @since   26.48.0
	 */
	private function atomItem(\SimpleXMLElement $node): ?FeedItem
	{
		$atom = $node->children(self::ATOM);
		$link = '';

		foreach ($atom->link as $candidate)
		{
			$attributes = $candidate->attributes();
			$rel        = (string) ($attributes['rel'] ?? '');

			if ($rel === '' || $rel === 'alternate')
			{
				$link = (string) ($attributes['href'] ?? '');
				break;
			}
		}

		$categories = [];

		foreach ($atom->category as $category)
		{
			$attributes = $category->attributes();
			$label      = (string) ($attributes['label'] ?? '');
			$term       = $label !== '' ? $label : (string) ($attributes['term'] ?? '');

			if ($term !== '')
			{
				$categories[] = $term;
			}
		}

		$body = $this->text($atom, 'content');

		if ($body === '')
		{
			$body = $this->text($atom, 'summary');
		}

		$date = $this->text($atom, 'published');

		if ($date === '')
		{
			$date = $this->text($atom, 'updated');
		}

		return $this->build($this->text($atom, 'id'), $link, $this->text($atom, 'title'), $body, $categories, $date);
	}

	/**
	 * Builds the item from raw values.
	 *
	 * @param   string        $guid        Raw guid.
	 * @param   string        $link        Raw link.
	 * @param   string        $title       Raw title.
	 * @param   string        $body        Raw description.
	 * @param   list<string>  $categories  Raw categories.
	 * @param   string        $date        Raw date.
	 *
	 * @return  ?FeedItem  Null when the item has no title, or neither link nor guid.
	 *
	 * @since   26.48.0
	 */
	private function build(string $guid, string $link, string $title, string $body, array $categories, string $date): ?FeedItem
	{
		$guid  = trim($guid);
		$link  = trim($link);
		$title = $this->plain($title);

		if ($title === '' || ($link === '' && $guid === ''))
		{
			return null;
		}

		$labels = [];

		foreach ($categories as $category)
		{
			$label = $this->plain($category);

			if ($label !== '' && !\in_array($label, $labels, true))
			{
				$labels[] = $label;
			}
		}

		return new FeedItem($guid, $link, $title, trim($body), $labels, $this->date($date));
	}

	/**
	 * Text of a child element.
	 *
	 * @param   \SimpleXMLElement  $node  The parent element.
	 * @param   string             $name  Name of the child element.
	 *
	 * @return  string  The text, or an empty string when the element is missing.
	 *
	 * @since   26.48.0
	 */
	private function text(\SimpleXMLElement $node, string $name): string
	{
		return isset($node->{$name}) ? (string) $node->{$name} : '';
	}

	/**
	 * Titles and labels may carry HTML entities (&#39;, &amp;) after XML decoding.
	 *
	 * @param   string  $value  Text with tags and entities.
	 *
	 * @return  string  Plain text with collapsed whitespace.
	 *
	 * @since   26.48.0
	 */
	private function plain(string $value): string
	{
		$value = html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

		return trim((string) preg_replace('/\s+/u', ' ', $value));
	}

	/**
	 * Reads an RSS date (RFC 822), an Atom date (RFC 3339), or any format DateTimeImmutable understands.
	 *
	 * @param   string  $value  The date as text.
	 *
	 * @return  ?\DateTimeImmutable  Null when empty or unreadable.
	 *
	 * @since   26.48.0
	 */
	private function date(string $value): ?\DateTimeImmutable
	{
		if (trim($value) === '')
		{
			return null;
		}

		try
		{
			return new \DateTimeImmutable(trim($value));
		}
		catch (\Exception)
		{
			return null;
		}
	}
}
