<?php
namespace OCA\Restya\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Restya\AppInfo\Application;

class Admin implements ISettings {

	private $request;
	private $config;
	private $dataDirPath;
	private $urlGenerator;
	private $l;

	public function __construct(
						string $appName,
						IL10N $l,
						IRequest $request,
						IConfig $config,
						IURLGenerator $urlGenerator,
						IInitialStateService $initialStateService,
						$userId) {
		$this->appName = $appName;
		$this->urlGenerator = $urlGenerator;
		$this->request = $request;
		$this->l = $l;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');
		$forcedInstanceUrl = $this->config->getAppValue(Application::APP_ID, 'forced_instance_url', '');

		$adminConfig = [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'forced_instance_url' => $forcedInstanceUrl,
		];
		$this->initialStateService->provideInitialState($this->appName, 'admin-config', $adminConfig);
		return new TemplateResponse(Application::APP_ID, 'adminSettings');
	}

	public function getSection() {
		return 'connected-accounts';
	}

	public function getPriority() {
		return 10;
	}
}
