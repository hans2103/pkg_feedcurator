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
 * Decides whether a request must stay out of System - Page Cache. Joomla-free.
 *
 * The review page carries a form token. A cached copy would hand the token of the first visitor (for example a
 * link scanner) to the real recipient, and the POST would fail.
 *
 * @since  __DEPLOY_VERSION__
 */
final class PageCachePolicy
{
	/**
	 * Whether the request is the review page.
	 *
	 * @param   ?string  $option  The option request parameter.
	 * @param   ?string  $plugin  The plugin request parameter.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function isExcluded(?string $option, ?string $plugin): bool
	{
		return $option === 'com_ajax' && $plugin === 'feedcurator';
	}
}
