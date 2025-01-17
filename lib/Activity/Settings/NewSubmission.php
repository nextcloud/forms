<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Activity\Settings;

use OCA\Forms\Activity\ActivityConstants;

class NewSubmission extends FormsActivitySettings {
	/**
	 * Event-Type this setting applies to
	 * Only lowercase letters and underscore allowed
	 * @return string
	 */
	public function getIdentifier(): string {
		return ActivityConstants::TYPE_NEWSUBMISSION;
	}

	/**
	 * Text of the setting
	 * @return string Translated String
	 */
	public function getName(): string {
		return $this->l10n->t('Someone <strong>answered</strong> a form');
	}

	/**
	 * Priority of the Setting
	 * Using Forms Base-Priority (parent)
	 * @return int
	 */
	public function getPriority(): int {
		return parent::getPriority() + 1;
	}
}
