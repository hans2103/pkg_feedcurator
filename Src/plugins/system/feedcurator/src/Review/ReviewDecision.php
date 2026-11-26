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
 * Decides what the review page does with a request. Joomla-free.
 *
 * Only a POST can lead to a change (Act); a GET, for example by a link scanner, at most shows the form.
 *
 * @since  26.48.0
 */
final class ReviewDecision
{
	/**
	 * The actions a POST may carry.
	 *
	 * @var    list<string>
	 * @since  26.48.0
	 */
	public const ACTIONS = ['publish', 'discard'];

	/**
	 * Decides the outcome of a request.
	 *
	 * @param   boolean  $signatureValid  Whether the signature of the link is genuine (and the user may use it).
	 * @param   boolean  $expired         Whether the link has expired.
	 * @param   integer  $articleState    The state of the article: 0 is a concept (unpublished).
	 * @param   boolean  $isPost          Whether the request is a POST.
	 * @param   ?string  $action          The posted action: 'publish' or 'discard'.
	 *
	 * @return  ReviewOutcome
	 *
	 * @since   26.48.0
	 */
	public static function decide(bool $signatureValid, bool $expired, int $articleState, bool $isPost, ?string $action): ReviewOutcome
	{
		if (!$signatureValid)
		{
			return ReviewOutcome::Invalid;
		}

		if ($expired)
		{
			return ReviewOutcome::Expired;
		}

		if ($isPost && !\in_array($action, self::ACTIONS, true))
		{
			return ReviewOutcome::Invalid;
		}

		if ($articleState !== 0)
		{
			return ReviewOutcome::AlreadyDone;
		}

		return $isPost ? ReviewOutcome::Act : ReviewOutcome::Show;
	}
}
