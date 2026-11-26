<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_task_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Feedcurator\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Scheduler routine that imports on-topic feed items as unpublished Joomla articles.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Feedcurator extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait;

	/**
	 * The routines this plugin offers to the scheduler.
	 *
	 * @var    array<string, array{langConstPrefix: string, form: string, method: string}>
	 * @since  __DEPLOY_VERSION__
	 */
	protected const TASKS_MAP = [
		'feedcurator.import' => [
			'langConstPrefix' => 'PLG_TASK_FEEDCURATOR_TASK_IMPORT',
			'form'            => 'import',
			'method'          => 'import',
		],
	];

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Constructor.
	 *
	 * @param   array<string, mixed>  $config       The plugin configuration.
	 * @param   HttpFactory           $httpFactory  Creates the HTTP client for the feeds.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(array $config, private readonly HttpFactory $httpFactory)
	{
		parent::__construct($config);
	}

	/**
	 * Returns the events this plugin listens to.
	 *
	 * @return  array<string, string>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * The routine feedcurator.import: placeholder that succeeds; the import follows in a later release.
	 *
	 * @param   ExecuteTaskEvent  $event  The task event.
	 *
	 * @return  integer  A TaskStatus constant.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function import(ExecuteTaskEvent $event): int
	{
		return TaskStatus::OK;
	}
}
