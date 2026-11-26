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

use HKweb\Plugin\Task\Feedcurator\Feed\FeedException;
use HKweb\Plugin\Task\Feedcurator\Import\FailureCounter;
use HKweb\Plugin\Task\Feedcurator\Import\Importer;
use HKweb\Plugin\Task\Feedcurator\Mail\Notifier;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use Joomla\Registry\Registry;

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
	 * The routine feedcurator.import: imports the feeds with the settings of the task.
	 *
	 * When a source is unavailable, the run only counts as failed (KNOCKOUT, and so the failure
	 * mail) from the setting notify_after failed runs in a row on; before that it logs a warning.
	 *
	 * @param   ExecuteTaskEvent  $event  The task event.
	 *
	 * @return  integer  A TaskStatus constant: OK, or KNOCKOUT when the settings are incomplete or sources keep failing.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function import(ExecuteTaskEvent $event): int
	{
		$params   = new Registry($event->getArgument('params'));
		$language = $this->siteLanguage();
		$counter  = new FailureCounter($this->getDatabase());
		$taskId   = $event->getTaskId();
		$importer = new Importer(
			$this->getApplication(),
			$this->getDatabase(),
			$this->httpFactory,
			$language,
			$this->logTask(...)
		);

		try
		{
			$result = $importer->run($params, $taskId);
		}
		catch (FeedException $e)
		{
			$this->logTask($e->getMessage(), 'error');

			return TaskStatus::KNOCKOUT;
		}

		$this->logTask(
			\sprintf(
				$language->_('PLG_TASK_FEEDCURATOR_LOG_RESULT'),
				\count($result->created),
				$result->skipped,
				$result->known,
				$result->failed
			)
		);

		if ($result->created !== [])
		{
			try
			{
				(new Notifier($this->getApplication(), $this->getDatabase(), $language, $this->logTask(...)))->notify($result, $params);
			}
			catch (\Throwable $e)
			{
				// The articles are saved; a mail problem must not skip the failure counter below.
				$this->logTask(\sprintf($language->_('PLG_TASK_FEEDCURATOR_LOG_MAIL_ERROR'), $e->getMessage()), 'error');
			}
		}

		if ($result->unavailable !== [])
		{
			$failures  = $counter->increment($taskId);
			$threshold = (int) $params->get('notify_after', 3);
			$names     = implode(', ', $result->unavailable);

			if (!FailureCounter::shouldReport($failures, $threshold))
			{
				$this->logTask(\sprintf($language->_('PLG_TASK_FEEDCURATOR_LOG_RETRY'), $names, $failures, $threshold), 'warning');

				return TaskStatus::OK;
			}

			$this->logTask(\sprintf($language->_('PLG_TASK_FEEDCURATOR_LOG_FAILED'), $names, $failures), 'error');

			return TaskStatus::KNOCKOUT;
		}

		$previous = $counter->reset($taskId);

		if ($previous > 0)
		{
			$this->logTask(\sprintf($language->_('PLG_TASK_FEEDCURATOR_LOG_RECOVERED'), $previous));
		}

		return TaskStatus::OK;
	}

	/**
	 * The language of the site, not of the running application (cron runs the command line in en-GB).
	 *
	 * Used for the stored source line and the log lines. The plugin strings come from the plugin's own
	 * language folder when they are not installed in the administrator language folder.
	 *
	 * @return  Language
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function siteLanguage(): Language
	{
		$tag      = (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
		$language = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag, false);
		$language->load('joomla', JPATH_ADMINISTRATOR, $tag, true);

		if (!$language->load('plg_task_feedcurator', JPATH_ADMINISTRATOR, $tag, true))
		{
			$language->load('plg_task_feedcurator', JPATH_PLUGINS . '/task/feedcurator', $tag, true);
		}

		return $language;
	}
}
