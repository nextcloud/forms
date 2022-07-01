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

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class SettingsSection implements IIconSection {

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IL10N $l10n, IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
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
