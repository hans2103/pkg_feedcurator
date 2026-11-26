<?php

/**
 * @package     HKweb.Feedcurator
 * @subpackage  plg_task_feedcurator
 *
 * @copyright   (C) 2026 HKweb
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use HKweb\Plugin\Task\Feedcurator\Extension\Feedcurator;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Http\HttpFactory;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the task plugin in the DI container, created lazily.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function register(Container $container): void
	{
		// The routine uses ReviewToken from the system plugin, whose namespace is registered only when that plugin is loaded.
		\JLoader::registerNamespace('HKweb\\Plugin\\System\\Feedcurator', JPATH_PLUGINS . '/system/feedcurator/src');

		$container->set(
			PluginInterface::class,
			$container->lazy(
				Feedcurator::class,
				static function (Container $container): PluginInterface {
					$plugin = new Feedcurator(
						(array) PluginHelper::getPlugin('task', 'feedcurator'),
						new HttpFactory
					);
					$plugin->setApplication(Factory::getApplication());
					$plugin->setDatabase($container->get(DatabaseInterface::class));

					return $plugin;
				}
			)
		);
	}
};
