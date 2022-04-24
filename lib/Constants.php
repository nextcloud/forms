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

class Constants {
	/**
	 * Maximum String lengths, the database is set to store.
	 */
	public const MAX_STRING_LENGTHS = [
		'formTitle' => 256,
		'formDescription' => 8192,
		'questionText' => 2048,
		'questionDescription' => 4096,
		'optionText' => 1024,
		'answerText' => 4096,
	];

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
	public const ANSWER_TYPE_TIME = 'time';

	// All AnswerTypes
	public const ANSWER_TYPES = [
		self::ANSWER_TYPE_MULTIPLE,
		self::ANSWER_TYPE_MULTIPLEUNIQUE,
		self::ANSWER_TYPE_DROPDOWN,
		self::ANSWER_TYPE_SHORT,
		self::ANSWER_TYPE_LONG,
		self::ANSWER_TYPE_DATE,
		self::ANSWER_TYPE_DATETIME,
		self::ANSWER_TYPE_TIME
	];

	// AnswerTypes, that need/have predefined Options
	public const ANSWER_TYPES_PREDEFINED = [
		self::ANSWER_TYPE_MULTIPLE,
		self::ANSWER_TYPE_MULTIPLEUNIQUE,
		self::ANSWER_TYPE_DROPDOWN
	];

	// AnswerTypes for date/time questions
	public const ANSWER_TYPES_DATETIME = [
		self::ANSWER_TYPE_DATE,
		self::ANSWER_TYPE_DATETIME,
		self::ANSWER_TYPE_TIME
	];

	// Formats for AnswerTypes date/datetime/time
	public const ANSWER_PHPDATETIME_FORMAT = [
		self::ANSWER_TYPE_DATE => 'Y-m-d',
		self::ANSWER_TYPE_DATETIME => 'Y-m-d H:i',
		self::ANSWER_TYPE_TIME => 'H:i'
	];
}
