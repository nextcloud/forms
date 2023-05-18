<?php
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
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

namespace OCA\Forms\Activity;

use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	protected $appName;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(string $appName,
		IL10N $l10n,
		IURLGenerator $urlGenerator) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Filter ID
	 * @return string
	 */
	public function getIdentifier(): string {
		return $this->appName;
	}

	/**
	 * Translated, readable Filter-Name
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Forms');
	}

	/**
	 * Icon to use
	 * @return string Full Icon-URL
	 */
	public function getIcon(): string {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, 'forms-dark.svg'));
	}

	/**
	 * Filter Priority within Activity App
	 * @return int
	 */
	public function getPriority(): int {
		return 60;
	}

	/**
	 * Only show Activities by Forms-App.
	 * @return string[] Array of allowed Apps
	 */
	public function allowedApps(): array {
		return [$this->appName];
	}

	/**
	 * No Sub-Filter within forms.
	 * @param string[] $types
	 * @return string[] An array of allowed Event-Types. Return param $types to allow all.
	 */
	public function filterTypes(array $types): array {
		return $types;
	}
}
