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

namespace OCA\Forms\Activity\Settings;

use OCA\Forms\Activity\ActivityConstants;

class NewShare extends FormsActivitySettings {
	/**
	 * Event-Type this setting applies to
	 * Only lowercase letters and underscore allowed
	 * @return string
	 */
	public function getIdentifier(): string {
		return ActivityConstants::TYPE_NEWSHARE;
	}

	/**
	 * Text of the setting
	 * @return string Translated String
	 */
	public function getName(): string {
		return $this->l10n->t('A form has been <strong>shared</strong> with you');
	}

	/**
	 * Priority of the Setting
	 * Using Forms Base-Priority (parent)
	 * @return int
	 */
	public function getPriority(): int {
		return parent::getPriority() + 0;
	}
}
