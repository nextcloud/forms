<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms;

use OCP\App\IAppManager;
use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {
	public function __construct(
		private IAppManager $appManager,
	) {
	}

	/**
	 * Provide App Capabilities
	 * @inheritdoc
	 * @return array{forms: array{version: string, apiVersions: list<string>}}
	 */
	public function getCapabilities() {
		return [
			'forms' => [
				'version' => $this->appManager->getAppVersion('forms'),
				'apiVersions' => ['v3']
			]
		];
	}
}
