<?php
/**
 * Nextcloud - restya
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Restya
 * @copyright Restya 2021
 */

namespace OCA\Restya\Service;

use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\IUser;
use OCP\Http\Client\IClientService;
use OCP\Notification\IManager as INotificationManager;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ConnectException;

use OCA\Restya\AppInfo\Application;

class RestyaAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to Restyaboard (JSON) API
	 */
	public function __construct (IUserManager $userManager,
								string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IConfig $config,
								INotificationManager $notificationManager,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->clientService = $clientService;
		$this->notificationManager = $notificationManager;
		$this->client = $clientService->newClient();
	}

	/**
	 * triggered by a cron job
	 * notifies user of their number of new tickets
	 *
	 * @return void
	 */
	public function checkOpenTickets(): void {
		$this->userManager->callForAllUsers(function (IUser $user) {
			$this->checkOpenTicketsForUser($user->getUID());
		});
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	private function checkOpenTicketsForUser(string $userId): void {
		$notificationEnabled = ($this->config->getUserValue($userId, Application::APP_ID, 'notification_enabled', '0') === '1');
		if ($notificationEnabled) {
			$lastNotificationCheck = $this->config->getUserValue($userId, Application::APP_ID, 'last_open_check', '');
			$lastNotificationCheck = $lastNotificationCheck === '' ? null : $lastNotificationCheck;

			$key = $this->config->getUserValue($userId, Application::APP_ID, 'user_id', '');
			$notifications = $this->getNotifications($userId, $lastNotificationCheck, 7, $key);
			if (!isset($notifications['error']) && count($notifications) > 0) {
				$myAccountKey = $this->config->getUserValue($userId, Application::APP_ID, 'user_key', '');
				$myAccountId = $this->config->getUserValue($userId, Application::APP_ID, 'user_account_id', '');
				if ($myAccountKey === '' && $myAccountId === '') {
					return;
				}
				$restyaUrl = $notifications[0]['restyaUrl'];
				$lastNotificationCheck = $notifications[0]['fields']['updated'];
				$this->config->setUserValue($userId, Application::APP_ID, 'last_open_check', $lastNotificationCheck);
				$nbOpen = 0;
				foreach ($notifications as $n) {
					$status_key = $n['fields']['status']['statusCategory']['key'] ?? '';
					$assigneeKey = $n['fields']['assignee']['key'] ?? '';
					$assigneeId = $n['fields']['assignee']['accountId'] ?? '';
					$embededAccountId = $n['my_account_id'] ?? '';
					// from what i saw, key is used in self hosted and accountId in cloud version
					// embededAccountId can be usefull when accessing multiple cloud resources, it being specific to the resource
					if ( (
							($myAccountKey !== '' && $assigneeKey === $myAccountKey)
							|| ($myAccountId !== '' && $myAccountId === $assigneeId)
							|| ($embededAccountId !== '' && $embededAccountId === $assigneeId)
						)
						&& $status_key !== 'done') {
						$nbOpen++;
					}
				}
				if ($nbOpen > 0) {
					$this->sendNCNotification($userId, 'new_open_tickets', [
						'nbOpen' => $nbOpen,
						'link' => $restyaUrl
					]);
				}
			}
		}
	}

	/**
	 * @param string $userId
	 * @param string $subject
	 * @param array $params
	 * @return void
	 */
	private function sendNCNotification(string $userId, string $subject, array $params): void {
		$manager = $this->notificationManager;
		$notification = $manager->createNotification();

		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setDateTime(new \DateTime())
			->setObject('dum', 'dum')
			->setSubject($subject, $params);

		$manager->notify($notification);
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function getRestyaResources(string $userId): array {
		$strRes = $this->config->getUserValue($userId, Application::APP_ID, 'resources', '');
		$resources = json_decode($strRes, true);
		$resources = ($resources && count($resources) > 0) ? $resources : [];
		return $resources;
	}

	/**
	 * @param string $userId
	 * @param ?string $since
	 * @param ?int $limit
	 * @return array
	 */
	public function getNotifications(string $userId, ?string $since = null, ?int $limit = null, ?string $key = null): array {
		$myIssues = [];

		$endPoint = 'api/v1/users/'. $key .'/activities.json';

		$basicAuthHeader = $this->config->getUserValue($userId, Application::APP_ID, 'user_key', '');
		// self hosted Restya
		if ($basicAuthHeader !== '') {
			$restyaUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', '');

			// check if there is a forced instance
			$forcedInstanceUrl = $this->config->getAppValue(Application::APP_ID, 'forced_instance_url', '');
			if ($forcedInstanceUrl !== '' && $forcedInstanceUrl !== $restyaUrl) {
				return [
					'error' => 'Unauthorized Restyaboard instance URL',
				];
			}

			$issuesResult = $this->basicRequest($restyaUrl, '', $endPoint, ['token' => $basicAuthHeader, 'mode' => 'all']);
			if (isset($issuesResult['error'])) {
				return $issuesResult;
			}
			foreach ($issuesResult['data'] as $k => $issue) {
				$issuesResult['data'][$k]['restyaUrl'] = $restyaUrl;
				$replaceData = [];
				$replaceData['##ORGANIZATION_LINK##'] = (isset($issuesResult['data'][$k]['organization_name'])) ? $issuesResult['data'][$k]['organization_name'] : '';
				$replaceData['##USER_NAME##'] = (isset($issuesResult['data'][$k]['full_name'])) ? $issuesResult['data'][$k]['full_name'] : '';
				$replaceData['##CARD_LINK##'] = (isset($issuesResult['data'][$k]['card_name'])) ? $issuesResult['data'][$k]['card_name'] : '';
				$replaceData['##LABEL_NAME##'] = (isset($issuesResult['data'][$k]['label_name'])) ? $issuesResult['data'][$k]['label_name'] : '';
				$replaceData['##CARD_NAME##'] = (isset($issuesResult['data'][$k]['card_name'])) ? $issuesResult['data'][$k]['card_name'] : '';
				$replaceData['##DESCRIPTION##'] = (isset($issuesResult['data'][$k]['card_description'])) ? $issuesResult['data'][$k]['card_description'] : '';
				$replaceData['##LIST_NAME##'] = (isset($issuesResult['data'][$k]['list_name'])) ? $issuesResult['data'][$k]['list_name'] : '';
				$replaceData['##BOARD_NAME##'] = (isset($issuesResult['data'][$k]['board_name'])) ? $issuesResult['data'][$k]['board_name'] : '';
				$replaceData['##CHECKLIST_NAME##'] = (isset($issuesResult['data'][$k]['checklist_name'])) ? $issuesResult['data'][$k]['checklist_name'] : '';
				$replaceData['##CHECKLIST_ITEM_NAME##'] = (isset($issuesResult['data'][$k]['checklist_item_name'])) ? $issuesResult['data'][$k]['checklist_item_name'] : '';
				$replaceData['##CHECKLIST_ITEM_PARENT_NAME##'] = (isset($issuesResult['data'][$k]['checklist_item_parent_name'])) ? $issuesResult['data'][$k]['checklist_item_parent_name'] : '';
				$issuesResult['data'][$k]['my_account_id'] = $issuesResult['my_account_id'] ?? '';
				$issuesResult['data'][$k]['comment'] = strtr($issuesResult['data'][$k]['comment'],$replaceData);
				if (!empty($issuesResult['data'][$k]['profile_picture_path']) && $issuesResult['data'][$k]['profile_picture_path'] !== null) {
					$SecuritySalt = 'e9a556134534545ab47c6c81c14f06c0b8sdfsdf';
					$hash = md5($SecuritySalt . 'User' . $issuesResult['data'][$k]['user_id'] . 'png' . 'small_thumb');
					$issuesResult['data'][$k]['profile_picture_path'] = $restyaUrl . '/img/small_thumb/User/' . $issuesResult['data'][$k]['user_id'] . '.' . $hash . '.png';
				}
				$myIssues[] = $issuesResult['data'][$k];
			}
		} else {
			// Restyaboard cloud
			$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token', '');
			$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token', '');
			$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
			$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');
			if ($accessToken === '' || $refreshToken === '') {
				return ['error' => 'no credentials'];
			}
			$resources = $this->getRestyaResources($userId);

			foreach ($resources as $resource) {
				$cloudId = $resource['id'];
				$restyaUrl = $resource['url'];
				$issuesResult = $this->oauthRequest(
					$accessToken, $refreshToken, $clientID, $clientSecret, $userId, 'ex/restya/' . $cloudId . '/' . $endPoint
				);
				if (!isset($issuesResult['error']) && isset($issuesResult['issues'])) {
					foreach ($issuesResult['issues'] as $k => $issue) {
						$issuesResult['issues'][$k]['restyaUrl'] = $restyaUrl;
						$issuesResult['issues'][$k]['my_account_id'] = $issuesResult['my_account_id'] ?? '';
						$myIssues[] = $issuesResult['issues'][$k];
					}
				} else {
					return $issuesResult;
				}
			}
		}

		if (!is_null($since)) {
			$sinceDate = new \Datetime($since);
			$sinceTimestamp = $sinceDate->getTimestamp();
			$myIssues = array_filter($myIssues, function($elem) use ($sinceTimestamp) {
				$date = new \Datetime($elem['modified']);
				$elemTs = $date->getTimestamp();
				return $elemTs > $sinceTimestamp;
			});
		}

		// sort by updated
		$a = usort($myIssues, function($a, $b) {
			$a = new \Datetime($a['modified']);
			$ta = $a->getTimestamp();
			$b = new \Datetime($b['modified']);
			$tb = $b->getTimestamp();
			return ($ta > $tb) ? -1 : 1;
		});

		return $myIssues;
	}

	/**
	 * @param string $userId
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function search(string $userId, string $query, int $offset = 0, int $limit = 7): array {
		$myIssues = [];
		$endPoint = 'api/v1/cards/search.json';
		$words = preg_split('/\s+/', $query);
		$searchString = '';
		foreach ($words as $word) {
			// put a star only if it's only latin letters
			if (preg_match('/^[a-z]+$/i', $word)) {
				$searchString .= $word . '* ';
			} else {
				$searchString .= $word . ' ';
			}
		}
		$searchString = preg_replace('/\s+\*\*/', '', $searchString);
		$searchString = preg_replace('/\s+$/', '', $searchString);

		$params = [
			'name' => $searchString,
			'limit' => 10,
		];

		$basicAuthHeader = $this->config->getUserValue($userId, Application::APP_ID, 'user_key', '');
		// self hosted Restya
		if ($basicAuthHeader !== '') {
			$restyaUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', '');

			// check if there is a forced instance
			$forcedInstanceUrl = $this->config->getAppValue(Application::APP_ID, 'forced_instance_url', '');
			if ($forcedInstanceUrl !== '' && $forcedInstanceUrl !== $restyaUrl) {
				return [
					'error' => 'Unauthorized Restyaboard instance URL',
				];
			}

			$issuesResult = $this->basicRequest($restyaUrl,'', $endPoint, ['token' => $basicAuthHeader, 'name' => $searchString]);
			if (isset($issuesResult['error'])) {
				return $issuesResult;
			}
			foreach ($issuesResult as $k => $issue) {
				$issuesResult[$k]['restyaUrl'] = $restyaUrl;
				$myIssues[] = $issuesResult[$k];
			}
	  }
		return array_slice($myIssues, $offset, $limit);
	}

	/**
	 * @param string $userId
	 * @param string $accountId
	 * @param string $accountKey
	 * @return array
	 */
	public function getAccountInfo(string $userId, string $accountId, string $accountKey): array {
		$params = [];
		if ($accountId) {
			$params['accountId'] = $accountId;
		} elseif ($accountKey) {
			$params['key'] = $accountKey;
		} else {
			return ['error' => 'not found'];
		}
		$endPoint = 'rest/api/2/user';

		$basicAuthHeader = $this->config->getUserValue($userId, Application::APP_ID, 'basic_auth_header', '');
		if ($basicAuthHeader !== '') {
			$restyaUrl = $this->config->getUserValue($userId, Application::APP_ID, 'url', '');

			// check if there is a forced instance
			$forcedInstanceUrl = $this->config->getAppValue(Application::APP_ID, 'forced_instance_url', '');
			if ($forcedInstanceUrl !== '' && $forcedInstanceUrl !== $restyaUrl) {
				return [
					'error' => 'Unauthorized Restyaboard instance URL',
				];
			}

			return $this->basicRequest($restyaUrl, $basicAuthHeader, $endPoint, $params);
		} else {
			$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token', '');
			$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token', '');
			$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id', '');
			$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret', '');
			if ($accessToken === '' || $refreshToken === '') {
				return ['error' => 'no credentials'];
			}

			$resources = $this->getRestyaResources($userId);

			foreach ($resources as $resource) {
				$cloudId = $resource['id'];
				$restyaUrl = $resource['url'];
				$result = $this->oauthRequest(
					$accessToken, $refreshToken, $clientID, $clientSecret, $userId, 'ex/restya/' . $cloudId . '/' . $endPoint, $params
				);
				if (!isset($result['error'])) {
					return $result;
				}
			}
		}
		return ['error' => 'not found'];
	}

	/**
	 * authenticated request to get an image from restya
	 *
	 * @param string $userId
	 * @param string $accountId
	 * @param string $accountKey
	 * @return ?string
	 */
	public function getRestyaAvatar(string $userId, string $accountId, string $accountKey): ?string {
		$imageUrl = $accountId;
		$basicAuthHeader = $this->config->getUserValue($userId, Application::APP_ID, 'user_key', '');
		return $this->client->get($imageUrl, ['token' => $basicAuthHeader])->getBody();
	}

	/**
	 * @param string $url
	 * @param string $authHeader
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function basicRequest(string $url, string $authHeader,
								string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Restyaboard integration',
				]
			];
			if ($method === 'POST') {
				$options['headers']['Content-Type'] = 'application/json';
			}

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = json_encode($params, JSON_UNESCAPED_UNICODE);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();
			$headers = $response->getHeaders();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$this->logger->warning('Restyaboard API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		} catch (ConnectException $e) {
			$this->logger->warning('Restyaboard API connection error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param string $clientID
	 * @param string $clientSecret
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function oauthRequest(string $accessToken, string $refreshToken,
							string $clientID, string $clientSecret, string $userId,
							string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = Application::RESTYA_API_URL . '/' . $endPoint;
			$options = [
				'headers' => [
					'Authorization'  => 'Bearer ' . $accessToken,
					'User-Agent' => 'Nextcloud Restyaboard integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();
			$headers = $response->getHeaders();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				$decodedResult = json_decode($body, true);
				if (isset($headers['x-aaccountid']) && is_array($headers['x-aaccountid']) && count($headers['x-aaccountid']) > 0) {
					$decodedResult['my_account_id'] = $headers['x-aaccountid'][0];
				}
				return $decodedResult;
			}
		} catch (ServerException | ClientException $e) {
			$response = $e->getResponse();
			$body = (string) $response->getBody();
			// refresh token if it's invalid
			// response can be : 'response:\n{\"code\":401,\"message\":\"Unauthorized\"}'
			if ($response->getStatusCode() === 401) {
				$this->logger->info('Trying to REFRESH the access token', ['app' => $this->appName]);
				// try to refresh the token
				$result = $this->requestOAuthAccessToken([
					'client_id' => $clientID,
					'client_secret' => $clientSecret,
					'grant_type' => 'refresh_token',
					'refresh_token' => $refreshToken,
				], 'POST');
				if (isset($result['access_token'])) {
					$accessToken = $result['access_token'];
					$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
					// retry the request with new access token
					return $this->oauthRequest(
						$accessToken, $refreshToken, $clientID, $clientSecret, $userId, $endPoint, $params, $method
					);
				}
			}
			$this->logger->warning('Restyaboard API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		} catch (ConnectException $e) {
			$this->logger->warning('Restyaboard API connection error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(array $params = [], string $method = 'GET'): array {
		try {
			$url = Application::RESTYA_AUTH_URL . '/oauth/token';
			$options = [
				'headers' => [
					'User-Agent'  => 'Nextcloud Restyaboard integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (\Exception | \Throwable $e) {
			$this->logger->warning('Restyaboard OAuth error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
