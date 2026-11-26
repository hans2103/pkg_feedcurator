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
 *
 * @since  __DEPLOY_VERSION__
 */
final class ReviewToken
{
	/**
	 * The signing key, derived from the site secret.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	private readonly string $key;

	/**
	 * Constructor.
	 *
	 * @param   string  $siteSecret  The secret of the site (configuration.php).
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(string $siteSecret)
	{
		$this->key = hash_hmac('sha256', 'feedcurator-review', $siteSecret);
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
	 * @since   __DEPLOY_VERSION__
	 */
	public function sign(int $articleId, int $userId, int $expires): string
	{
		return hash_hmac('sha256', "{$articleId}|{$userId}|{$expires}", $this->key);
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
	 * @since   __DEPLOY_VERSION__
	 */
	public function verify(int $articleId, int $userId, int $expires, string $signature, int $now): bool
	{
		return hash_equals($this->sign($articleId, $userId, $expires), $signature) && $now <= $expires;
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
	 * @since   __DEPLOY_VERSION__
	 */
	public static function url(string $siteUrl, int $articleId, int $userId, int $expires, string $signature): string
	{
		return rtrim($siteUrl, '/')
			. '/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html'
			. '&a=' . $articleId . '&u=' . $userId . '&e=' . $expires . '&s=' . $signature;
	}
}
