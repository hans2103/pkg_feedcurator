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
 * Decides whether a feed item is on topic, by include and exclude terms.
 *
 * Terms match whole words, case-insensitively; a trailing * matches any word ending,
 * whitespace in a phrase matches any whitespace.
 *
 * @since  __DEPLOY_VERSION__
 */
final class KeywordFilter
{
	/**
	 * @var  list<string>
	 */
	private array $includePatterns;

	/**
	 * @var  list<string>
	 */
	private array $excludePatterns;

	/**
	 * @param   list<string>  $include  Terms of which at least one must match.
	 * @param   list<string>  $exclude  Terms that reject an item.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(array $include, array $exclude)
	{
		$this->includePatterns = self::patterns($include);
		$this->excludePatterns = self::patterns($exclude);
	}

	/**
	 * Whether the item matches an include term and no exclude term, on title,
	 * description (tags stripped) and categories. No include terms: never.
	 *
	 * @param   FeedItem  $item  The item to test.
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function matches(FeedItem $item): bool
	{
		if ($this->includePatterns === [])
		{
			return false;
		}

		$haystack = $item->title . "\n" . Summary::fromHtml($item->description, PHP_INT_MAX)
			. "\n" . implode("\n", $item->categories);

		foreach ($this->excludePatterns as $pattern)
		{
			if (preg_match($pattern, $haystack) === 1)
			{
				return false;
			}
		}

		foreach ($this->includePatterns as $pattern)
		{
			if (preg_match($pattern, $haystack) === 1)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Splits a textarea into terms: one per line, trimmed, empty lines and
	 * case-insensitive duplicates removed.
	 *
	 * @param   string  $text  Textarea value.
	 *
	 * @return  list<string>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function parseList(string $text): array
	{
		$terms = [];

		foreach (preg_split('/\R/u', $text) ?: [] as $line)
		{
			$line = trim($line);

			if ($line !== '' && !isset($terms[mb_strtolower($line)]))
			{
				$terms[mb_strtolower($line)] = $line;
			}
		}

		return array_values($terms);
	}

	/**
	 * Patterns for the terms that have a word besides wildcards; a term such as * would match everything.
	 *
	 * @param   list<string>  $terms  Terms.
	 *
	 * @return  list<string>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private static function patterns(array $terms): array
	{
		$patterns = [];

		foreach ($terms as $term)
		{
			if (trim($term, "* \t\n\r") !== '')
			{
				$patterns[] = self::pattern($term);
			}
		}

		return $patterns;
	}

	/**
	 * @param   string  $term  One term.
	 *
	 * @return  string  Regular expression.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private static function pattern(string $term): string
	{
		$wildcard = str_ends_with($term, '*');
		$words    = preg_split('/\s+/u', trim($term, "* \t\n\r")) ?: [];
		$regex    = implode('\s+', array_map(static fn (string $w): string => preg_quote($w, '/'), $words));

		return '/(?<!\w)' . $regex . ($wildcard ? '\w*' : '') . '(?!\w)/iu';
	}
}
