<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class SettingsSection implements IIconSection {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * Section ID to be used for Setting
	 *
	 * @return string
	 */
	public function getID(): string {
		return 'forms';
	}

	/**
	 * Translated Name to display
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Forms');
	}

	/**
	 * Priority of the Section. Using Priority here as on Navigationorder.
	 *
	 * @return int between 0-99
	 */
	public function getPriority(): int {
		return 77;
	}

	/**
	 * Section Icon
	 *
	 * @return string Relative Path to the icon
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('forms', 'forms-dark.svg');
	}
}
