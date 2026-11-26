<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_task_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace HKweb\Plugin\Task\Feedcurator\Mail;

use HKweb\Plugin\System\Feedcurator\Review\ReviewToken;
use HKweb\Plugin\Task\Feedcurator\Import\ImportResult;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Mails the board members one message about the new concept articles, each with a personal signed review link.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Notifier
{
	/**
	 * Writes a line to the task log.
	 *
	 * @var    \Closure(string, string): void
	 * @since  __DEPLOY_VERSION__
	 */
	private readonly \Closure $logger;

	/**
	 * Constructor.
	 *
	 * @param   CMSApplicationInterface  $app       The application, for the site secret and the site name.
	 * @param   DatabaseInterface        $db        The database.
	 * @param   Language                 $language  The language of the site: the mail is in the site language, not that of the CLI.
	 * @param   ?\Closure                $log       Receives a message and a level (info, warning, error); default: no logging.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(
		private readonly CMSApplicationInterface $app,
		private readonly DatabaseInterface $db,
		private readonly Language $language,
		?\Closure $log = null,
	) {
		$this->logger = $log ?? static function (string $message, string $level): void {
		};
	}

	/**
	 * Sends each recipient one mail with a signed link per new article.
	 *
	 * @param   ImportResult  $result  The result of the import run.
	 * @param   Registry      $params  The task settings (notify_groups, site_url, link_days).
	 *
	 * @return  integer  The number of mails sent.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function notify(ImportResult $result, Registry $params): int
	{
		if ($result->created === [])
		{
			return 0;
		}

		$recipients = $this->recipients(array_map('intval', (array) $params->get('notify_groups', [])));

		if ($recipients === [])
		{
			($this->logger)($this->language->_('PLG_TASK_FEEDCURATOR_LOG_MAIL_NO_RECIPIENTS'), 'warning');

			return 0;
		}

		$token    = new ReviewToken((string) $this->app->get('secret'));
		$siteUrl  = (string) $params->get('site_url', '');
		$days     = max(1, (int) $params->get('link_days', 7));
		$siteName = (string) $this->app->get('sitename');
		$count    = \count($result->created);
		$one      = $count === 1;
		$subject  = \sprintf($this->language->_($one ? 'PLG_TASK_FEEDCURATOR_MAIL_SUBJECT_ONE' : 'PLG_TASK_FEEDCURATOR_MAIL_SUBJECT_MORE'), $siteName, $count);
		$intro    = \sprintf($this->language->_($one ? 'PLG_TASK_FEEDCURATOR_MAIL_INTRO_ONE' : 'PLG_TASK_FEEDCURATOR_MAIL_INTRO_MORE'), $siteName, $count);
		$label    = $this->language->_('PLG_TASK_FEEDCURATOR_MAIL_LINK_LABEL');
		$factory  = Factory::getContainer()->get(MailerFactoryInterface::class);
		$sent     = 0;

		foreach ($recipients as $recipient)
		{
			$expires = time() + $days * 86400;
			$items   = [];

			foreach ($result->created as $article)
			{
				$items[] = [
					'title'  => $article['title'],
					'source' => $article['source'],
					'url'    => ReviewToken::url(
						$siteUrl,
						$article['id'],
						$recipient['id'],
						$expires,
						$token->sign($article['id'], $recipient['id'], $expires)
					),
				];
			}

			try
			{
				$mailer = $factory->createMailer();
				$mailer->addRecipient($recipient['email'], $recipient['name']);
				$mailer->setSubject($subject);
				$mailer->isHtml(true);
				$mailer->setBody(MailBody::html($intro, $items, $label));
				$mailer->AltBody = MailBody::text($intro, $items, $label);

				if ($mailer->Send() === true)
				{
					$sent++;

					continue;
				}

				($this->logger)(\sprintf($this->language->_('PLG_TASK_FEEDCURATOR_LOG_MAIL_FAILED'), $recipient['email'], (string) $mailer->ErrorInfo), 'error');
			}
			catch (\Throwable $e)
			{
				($this->logger)(\sprintf($this->language->_('PLG_TASK_FEEDCURATOR_LOG_MAIL_FAILED'), $recipient['email'], $e->getMessage()), 'error');
			}
		}

		($this->logger)(\sprintf($this->language->_('PLG_TASK_FEEDCURATOR_LOG_MAIL_SENT'), $sent, \count($recipients)), 'info');

		return $sent;
	}

	/**
	 * The active users in the groups, one row per user.
	 *
	 * @param   list<int>  $groups  The user group ids.
	 *
	 * @return  list<array{id: int, name: string, email: string}>
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function recipients(array $groups): array
	{
		$groups = array_values(array_filter($groups));

		if ($groups === [])
		{
			return [];
		}

		$query = $this->db->createQuery()
			->select($this->db->quoteName(['u.id', 'u.name', 'u.email']))
			->from($this->db->quoteName('#__users', 'u'))
			->join('INNER', $this->db->quoteName('#__user_usergroup_map', 'm'), $this->db->quoteName('m.user_id') . ' = ' . $this->db->quoteName('u.id'))
			->where($this->db->quoteName('u.block') . ' = 0')
			->where('(' . $this->db->quoteName('u.activation') . ' = ' . $this->db->quote('') . ' OR ' . $this->db->quoteName('u.activation') . ' = ' . $this->db->quote('0') . ')')
			->whereIn($this->db->quoteName('m.group_id'), $groups, ParameterType::INTEGER)
			->group($this->db->quoteName(['u.id', 'u.name', 'u.email']))
			->order($this->db->quoteName('u.id'));

		$recipients = [];

		foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row)
		{
			$recipients[] = ['id' => (int) $row->id, 'name' => (string) $row->name, 'email' => (string) $row->email];
		}

		return $recipients;
	}
}
