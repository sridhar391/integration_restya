<?php
/**
 * Nextcloud - restya
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Restya <info@restya.com>
 * @copyright Restya 2021
 */

namespace OCA\Restya\Controller;

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use Psr\Log\LoggerInterface;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Restya\Service\RestyaAPIService;
use OCA\Restya\AppInfo\Application;

class RestyaAPIController extends Controller {


	private $userId;
	private $config;
	private $dbconnection;
	private $dbtype;

	public function __construct($AppName,
								IRequest $request,
								IServerContainer $serverContainer,
								IConfig $config,
								IL10N $l10n,
								IAppManager $appManager,
								IAppData $appData,
								LoggerInterface $logger,
								RestyaAPIService $restyaAPIService,
								$userId) {
		parent::__construct($AppName, $request);
		$this->userId = $userId;
		$this->l10n = $l10n;
		$this->appData = $appData;
		$this->serverContainer = $serverContainer;
		$this->config = $config;
		$this->logger = $logger;
		$this->restyaAPIService = $restyaAPIService;
	}

	/**
	 * get restya user avatar
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $accountId
	 * @param string $accountId
	 * @return DataDisplayResponse
	 */
	public function getRestyaAvatar(string $accountId = '', string $accountKey = ''): DataDisplayResponse {
		$avatarContent = $this->restyaAPIService->getRestyaAvatar($this->userId, $accountId, $accountKey);
		if (is_null($avatarContent)) {
			return new DataDisplayResponse('', 401);
		} else {
			$response = new DataDisplayResponse($avatarContent);
			$response->cacheFor(60*60*24);
			return $response;
		}
	}

	/**
	 * get notifications list
	 * @NoAdminRequired
	 *
	 * @param ?string $since
	 * @return DataResponse
	 */
	public function getNotifications(?string $since = null): DataResponse {
		$key = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id', '');
		$result = $this->restyaAPIService->getNotifications($this->userId, $since, 7, $key);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}
}
