<?php
/**
 * @copyright Copyright (c) 2020 Restya <info@restya.com>
 *
 * @author Restya <info@restya.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Restya\Dashboard;

use OCP\Dashboard\IWidget;
use OCP\IL10N;

use OCA\Restya\AppInfo\Application;

class RestyaWidget implements IWidget {

	/** @var IL10N */
	private $l10n;

	public function __construct(
		IL10N $l10n
	) {
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'restya_notifications';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Restyaboard notifications');
		}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'icon-restya';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return \OC::$server->getURLGenerator()->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		\OC_Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		\OC_Util::addStyle(Application::APP_ID, 'dashboard');
	}
}