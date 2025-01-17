<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Activity;

use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class Filter implements IFilter {
	public function __construct(
		protected string $appName,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
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
