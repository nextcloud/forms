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

namespace OCA\Forms\Service;

use OCA\Forms\Constants;

use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

class ConfigService {
	private ?IUser $currentUser;
		
	public function __construct(
		protected string $appName,
		private IConfig $config,
		private IGroupManager $groupManager,
		IUserSession $userSession
	) {
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Load the single values, decode, have default values
	 */
	public function getAllowPermitAll(): bool {
		return json_decode($this->config->getAppValue($this->appName, Constants::CONFIG_KEY_ALLOWPERMITALL, 'true'));
	}
	public function getAllowPublicLink(): bool {
		return json_decode($this->config->getAppValue($this->appName, Constants::CONFIG_KEY_ALLOWPUBLICLINK, 'true'));
	}
	public function getAllowShowToAll() : bool {
		return json_decode($this->config->getAppValue($this->appName, Constants::CONFIG_KEY_ALLOWSHOWTOALL, 'true'));
	}
	private function getUnformattedCreationAllowedGroups(): array {
		return json_decode($this->config->getAppValue($this->appName, Constants::CONFIG_KEY_CREATIONALLOWEDGROUPS, '[]'));
	}
	public function getCreationAllowedGroups(): array {
		return $this->formatGroupsForMultiselect($this->getUnformattedCreationAllowedGroups());
	}
	public function getRestrictCreation(): bool {
		return json_decode($this->config->getAppValue($this->appName, Constants::CONFIG_KEY_RESTRICTCREATION, 'false'));
	}

	/**
	 * Provide the full AppConfig
	 */
	public function getAppConfig(): array {
		return [
			Constants::CONFIG_KEY_ALLOWPERMITALL => $this->getAllowPermitAll(),
			Constants::CONFIG_KEY_ALLOWPUBLICLINK => $this->getAllowPublicLink(),
			Constants::CONFIG_KEY_ALLOWSHOWTOALL => $this->getAllowShowToAll(),
			Constants::CONFIG_KEY_CREATIONALLOWEDGROUPS => $this->getCreationAllowedGroups(),
			Constants::CONFIG_KEY_RESTRICTCREATION => $this->getRestrictCreation(),

			// Additional, calculated information out of Config
			'canCreateForms' => $this->canCreateForms()
		];
	}

	/**
	 * Format the stored groups
	 *
	 * @param String[] $groups String Array of the groupIds
	 * @return Array[] Array of GroupObjects
	 */
	private function formatGroupsForMultiselect(array $groups): array {
		$formattedGroups = [];
		foreach ($groups as $groupId) {
			$group = $this->groupManager->get($groupId);
			if ($group instanceof IGroup) {
				$formattedGroups[] = [
					'groupId' => $groupId,
					'displayName' => $group->getDisplayName()
				];
			}
		}
		return $formattedGroups;
	}

	/**
	 * Check if currentUser is allowed to create Forms
	 * @return bool
	 */
	public function canCreateForms(): bool {
		if ($this->currentUser === null) {
			return false;
		}

		// Restriction active or not
		if (!$this->getRestrictCreation()) {
			return true;
		}

		$userGroups = $this->groupManager->getUserGroupIds($this->currentUser);
		// If array intersection is not empty, user is member of any allowed group.
		if (sizeof(array_intersect($userGroups, $this->getUnformattedCreationAllowedGroups()))) {
			return true;
		}

		return false;
	}
}
