<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Forms\Settings;

use OCA\Forms\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use OCP\Util;

class Settings implements ISettings {
	private $appName;

	/** @var ConfigService */
	private $configService;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IInitialState */
	private $initialState;

	public function __construct(string $appName,
		ConfigService $configService,
		IGroupManager $groupManager,
		IInitialState $initialState) {
		$this->appName = $appName;
		$this->configService = $configService;
		$this->groupManager = $groupManager;
		$this->initialState = $initialState;
	}

	/**
	 * Provide all available Groups
	 *
	 * @return Array[] Array of GroupObjects
	 */
	private function getAvailableGroups(): array {
		$formattedGroups = [];
		$groups = $this->groupManager->search('');
		foreach ($groups as $group) {
			$formattedGroups[] = [
				'groupId' => $group->getGID(),
				'displayName' => $group->getDisplayName()
			];
		}
		return $formattedGroups;
	}

	public function getForm(): TemplateResponse {
		Util::addScript($this->appName, 'forms-settings');
		$this->initialState->provideInitialState('availableGroups', $this->getAvailableGroups());
		$this->initialState->provideInitialState('appConfig', $this->configService->getAppConfig());

		return new TemplateResponse($this->appName, 'settings');
	}

	public function getSection(): string {
		return 'forms';
	}

	public function getPriority(): int {
		return 50;
	}
}
