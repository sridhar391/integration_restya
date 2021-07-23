<?php
/**
 * Nextcloud - Restya
 *
 *
 * @author Restya <info@restya.com>
 * @copyright Restya 2021
 */

namespace OCA\Restya\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\Notification\IManager as INotificationManager;
use OCP\IConfig;

use OCA\Restya\Controller\PageController;
use OCA\Restya\Dashboard\RestyaWidget;
use OCA\Restya\Search\RestyaSearchProvider;
use OCA\Restya\Notification\Notifier;

/**
 * Class Application
 *
 * @package OCA\Restya\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_restya';
	public const RESTYA_API_URL = 'https://api.restya.com';
	public const RESTYA_AUTH_URL = 'https://auth.restya.com';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->container = $container;
		$manager = $container->query(INotificationManager::class);
		$manager->registerNotifierService(Notifier::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(RestyaWidget::class);
		$context->registerSearchProvider(RestyaSearchProvider::class);
	}

	public function boot(IBootContext $context): void {
	}
}

