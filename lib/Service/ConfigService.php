<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Constants;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

class ConfigService {
	private readonly ?IUser $currentUser;

	public function __construct(
		protected string $appName,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly IGroupManager $groupManager,
		IUserSession $userSession,
	) {
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Load the single values, decode, have default values
	 */
	public function getAllowPermitAll(): bool {
		return $this->appConfig->getAppValueBool(Constants::CONFIG_KEY_ALLOWPERMITALL, true);
	}
	public function getAllowPublicLink(): bool {
		return $this->appConfig->getAppValueBool(Constants::CONFIG_KEY_ALLOWPUBLICLINK, true);
	}
	public function getAllowShowToAll() : bool {
		return $this->appConfig->getAppValueBool(Constants::CONFIG_KEY_ALLOWSHOWTOALL, true);
	}
	private function getUnformattedCreationAllowedGroups(): array {
		return $this->appConfig->getAppValueArray(Constants::CONFIG_KEY_CREATIONALLOWEDGROUPS, []);
	}
	public function getCreationAllowedGroups(): array {
		return $this->formatGroupsForMultiselect($this->getUnformattedCreationAllowedGroups());
	}
	public function getRestrictCreation(): bool {
		return $this->appConfig->getAppValueBool(Constants::CONFIG_KEY_RESTRICTCREATION, false);
	}

	public function getAllowConfirmationEmail(): bool {
		return $this->appConfig->getAppValueBool(Constants::CONFIG_KEY_ALLOWCONFIRMATIONEMAIL, false);
	}

	public function isMailConfigured(): bool {
		return $this->config->getSystemValue('mail_from_address', '') !== '';
	}

	public function getConfirmationEmailRateLimit(): int {
		return $this->appConfig->getAppValueInt(Constants::CONFIG_KEY_CONFIRMATIONEMAILRATELIMIT, 3);
	}

	public function getAllowComments(): bool {
		return $this->appConfig->getAppValueBool(Constants::CONFIG_KEY_ALLOWCOMMENTS, false);
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
			Constants::CONFIG_KEY_ALLOWCONFIRMATIONEMAIL => $this->getAllowConfirmationEmail(),
			Constants::CONFIG_KEY_CONFIRMATIONEMAILRATELIMIT => $this->getConfirmationEmailRateLimit(),
			'isMailConfigured' => $this->isMailConfigured(),
			Constants::CONFIG_KEY_ALLOWCOMMENTS => $this->getAllowComments(),

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
		if (count(array_intersect($userGroups, $this->getUnformattedCreationAllowedGroups()))) {
			return true;
		}

		return false;
	}
}
