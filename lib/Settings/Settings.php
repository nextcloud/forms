<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Settings;

use OCA\Forms\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IGroupManager;
use OCP\Settings\ISettings;
use OCP\Util;

class Settings implements ISettings {
	public function __construct(
		private string $appName,
		private ConfigService $configService,
		private IGroupManager $groupManager,
		private IInitialState $initialState,
	) {
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
		Util::addStyle($this->appName, 'forms-style');
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
