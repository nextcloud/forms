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

namespace OCA\Forms;

use OCP\Share\IShare;

class Constants {
	/**
	 * !! Keep in sync with src/models/AnswerTypes.js !!
	 */

	// Available AnswerTypes
	public const ANSWER_TYPE_MULTIPLE = 'multiple';
	public const ANSWER_TYPE_MULTIPLEUNIQUE = 'multiple_unique';
	public const ANSWER_TYPE_DROPDOWN = 'dropdown';
	public const ANSWER_TYPE_SHORT = 'short';
	public const ANSWER_TYPE_LONG = 'long';
	public const ANSWER_TYPE_DATE = 'date';
	public const ANSWER_TYPE_DATETIME = 'datetime';

	// AnswerTypes, that need/have predefined Options
	public const ANSWER_PREDEFINED = [self::ANSWER_TYPE_MULTIPLE, self::ANSWER_TYPE_MULTIPLEUNIQUE, self::ANSWER_TYPE_DROPDOWN];

	/**
	 * !! Keep in sync with src/mixins/ShareTypes.js !!
	 */
	public const SHARE_TYPES_USED = [IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_LINK];

	/**
	 * !! Keep in sync with src/mixins/PermissionTypes.js !!
	 * Permission values equal the route names, thus making it easy on frontend to evaluate.
	 */
	// Define Form Permissions
	public const PERMISSION_EDIT = 'edit';
	public const PERMISSION_RESULTS = 'results';
	public const PERMISSION_SUBMIT = 'submit';
	public const PERMISSION_ALL = [self::PERMISSION_EDIT, self::PERMISSION_RESULTS, self::PERMISSION_SUBMIT];
}
