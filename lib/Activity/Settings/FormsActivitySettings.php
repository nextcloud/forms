<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Activity\Settings;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

abstract class FormsActivitySettings extends ActivitySettings {
	public function __construct(
		protected string $appName,
		protected IL10N $l10n,
	) {
	}

	/**
	 * Settings Group ID
	 * @return string
	 */
	public function getGroupIdentifier(): string {
		return $this->appName;
	}

	/**
	 * Human Readable Group Title
	 * @return string
	 */
	public function getGroupName(): string {
		return $this->l10n->t('Forms');
	}

	/**
	 * Priority of the Setting (0-100)
	 * Using this as Forms-Basepriority
	 * @return int
	 */
	public function getPriority(): int {
		return 60;
	}

	/**
	 * User can change Notification
	 * @return bool
	 */
	public function canChangeNotification(): bool {
		return true;
	}

	/**
	 * Notification enabled by default
	 */
	public function isDefaultEnabledNotification(): bool {
		return true;
	}

	/**
	 * User can change Mail
	 * @return bool
	 */
	public function canChangeMail(): bool {
		return true;
	}

	/**
	 * Mail disabled by default
	 * @return bool
	 */
	public function isDefaultEnabledMail(): bool {
		return false;
	}
}
