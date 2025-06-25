<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		IUserSession $userSession,
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
