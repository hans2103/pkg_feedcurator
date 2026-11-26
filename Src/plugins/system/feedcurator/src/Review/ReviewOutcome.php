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
 * What the review page does with a request. Joomla-free.
 *
 * @since  26.48.0
 */
enum ReviewOutcome: string
{
	/**
	 * Show the article and the form (GET on a concept).
	 *
	 * @since  26.48.0
	 */
	case Show = 'show';

	/**
	 * The link or the request is not genuine.
	 *
	 * @since  26.48.0
	 */
	case Invalid = 'invalid';

	/**
	 * The link is genuine but has expired.
	 *
	 * @since  26.48.0
	 */
	case Expired = 'expired';

	/**
	 * The article is no longer a concept: published, trashed or archived already.
	 *
	 * @since  26.48.0
	 */
	case AlreadyDone = 'done';

	/**
	 * Publish or discard the article (POST on a concept).
	 *
	 * @since  26.48.0
	 */
	case Act = 'act';
}
