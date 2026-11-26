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
 * Signs and verifies the personal review links in the notification mail.
 *
 * A link carries the article (a), the recipient (u), the expiry as a Unix timestamp (e) and an HMAC-SHA256
 * signature (s) of "a|u|e", made with a key derived from the site secret. Joomla-free.
 * An empty site secret would make every link forgeable: sign() then refuses and verify() is always false.
 *
 * @since  26.48.0
 */
final class ReviewToken
{
	/**
	 * The signing key, derived from the site secret.
	 *
	 * @var    string
	 * @since  26.48.0
	 */
	private readonly string $key;

	/**
	 * Whether the site secret is usable (not empty or whitespace).
	 *
	 * @var    boolean
	 * @since  26.48.0
	 */
	private readonly bool $usable;

	/**
	 * Constructor.
	 *
	 * @param   string  $siteSecret  The secret of the site (configuration.php).
	 *
	 * @since   26.48.0
	 */
	public function __construct(string $siteSecret)
	{
		$this->key    = hash_hmac('sha256', 'feedcurator-review', $siteSecret);
		$this->usable = trim($siteSecret) !== '';
	}

	/**
	 * Whether links can be signed and verified: false when the site secret is empty.
	 *
	 * @return  boolean
	 *
	 * @since   26.48.0
	 */
	public function isUsable(): bool
	{
		return $this->usable;
	}

	/**
	 * Creates the signature for a link.
	 *
	 * @param   integer  $articleId  The article to review.
	 * @param   integer  $userId     The recipient of the link.
	 * @param   integer  $expires    Unix timestamp after which the link is invalid.
	 *
	 * @return  string  Hex encoded HMAC-SHA256.
	 *
	 * @throws  \InvalidArgumentException  When the site secret is empty.
	 *
	 * @since   26.48.0
	 */
	public function sign(int $articleId, int $userId, int $expires): string
	{
		if (!$this->usable)
		{
			throw new \InvalidArgumentException('The site secret is empty; review links cannot be signed.');
		}

		return $this->hmac($articleId, $userId, $expires);
	}

	/**
	 * Checks only the signature of a link, not its expiry.
	 *
	 * @param   integer  $articleId  The article from the link.
	 * @param   integer  $userId     The user from the link.
	 * @param   integer  $expires    The expiry from the link.
	 * @param   string   $signature  The signature from the link.
	 *
	 * @return  boolean  True when the signature is genuine; always false with an empty site secret.
	 *
	 * @since   26.48.0
	 */
	public function isGenuine(int $articleId, int $userId, int $expires, string $signature): bool
	{
		return $this->usable && hash_equals($this->hmac($articleId, $userId, $expires), $signature);
	}

	/**
	 * Checks a signature and the expiry of a link.
	 *
	 * @param   integer  $articleId  The article from the link.
	 * @param   integer  $userId     The user from the link.
	 * @param   integer  $expires    The expiry from the link.
	 * @param   string   $signature  The signature from the link.
	 * @param   integer  $now        The current Unix timestamp.
	 *
	 * @return  boolean  True when the signature is genuine and the link has not expired.
	 *
	 * @since   26.48.0
	 */
	public function verify(int $articleId, int $userId, int $expires, string $signature, int $now): bool
	{
		return $this->isGenuine($articleId, $userId, $expires, $signature) && $now <= $expires;
	}

	/**
	 * The HMAC of "a|u|e" with the derived key.
	 *
	 * @param   integer  $articleId  The article.
	 * @param   integer  $userId     The user.
	 * @param   integer  $expires    The expiry.
	 *
	 * @return  string
	 *
	 * @since   26.48.0
	 */
	private function hmac(int $articleId, int $userId, int $expires): string
	{
		return hash_hmac('sha256', "{$articleId}|{$userId}|{$expires}", $this->key);
	}

	/**
	 * Builds the review URL for a signed link.
	 *
	 * @param   string   $siteUrl    The base address of the site.
	 * @param   integer  $articleId  The article to review.
	 * @param   integer  $userId     The recipient of the link.
	 * @param   integer  $expires    Unix timestamp after which the link is invalid.
	 * @param   string   $signature  The signature from sign().
	 *
	 * @return  string
	 *
	 * @since   26.48.0
	 */
	public static function url(string $siteUrl, int $articleId, int $userId, int $expires, string $signature): string
	{
		return rtrim($siteUrl, '/')
			. '/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html'
			. '&a=' . $articleId . '&u=' . $userId . '&e=' . $expires . '&s=' . $signature;
	}
}
