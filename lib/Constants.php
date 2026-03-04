<?php

/**
 * SPDX-FileCopyrightText: 2021-2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms;

use OCP\Share\IShare;

class Constants {
	/**
	 * Used AppConfig Keys
	 */
	public const CONFIG_KEY_ALLOWPERMITALL = 'allowPermitAll';
	public const CONFIG_KEY_ALLOWPUBLICLINK = 'allowPublicLink';
	public const CONFIG_KEY_ALLOWSHOWTOALL = 'allowShowToAll';
	public const CONFIG_KEY_CREATIONALLOWEDGROUPS = 'creationAllowedGroups';
	public const CONFIG_KEY_RESTRICTCREATION = 'restrictCreation';
	public const CONFIG_KEYS = [
		self::CONFIG_KEY_ALLOWPERMITALL,
		self::CONFIG_KEY_ALLOWPUBLICLINK,
		self::CONFIG_KEY_ALLOWSHOWTOALL,
		self::CONFIG_KEY_CREATIONALLOWEDGROUPS,
		self::CONFIG_KEY_RESTRICTCREATION
	];

	/**
	 * Maximum String lengths, the database is set to store.
	 */
	public const MAX_STRING_LENGTHS = [
		'formTitle' => 256,
		'formDescription' => 8192,
		'submissionMessage' => 2048,
		'questionText' => 2048,
		'questionDescription' => 4096,
		'optionText' => 1024,
		'answerText' => 4096,
	];

	/**
	 * State flags of a form
	 */
	public const FORM_STATE_ACTIVE = 0;
	public const FORM_STATE_CLOSED = 1;
	public const FORM_STATE_ARCHIVED = 2;

	/**
	 * Access flags of a form
	 */
	public const FORM_ACCESS_NOPUBLICSHARE = 0;
	public const FORM_ACCESS_PERMITALLUSERS = 1;
	public const FORM_ACCESS_SHOWTOALLUSERS = 2;
	/** @deprecated 5.0.0 still needed for Migrations */
	public const FORM_ACCESS_LEGACYLINK = 3;
	public const FORM_ACCESS_ARRAY_PERMIT = [
		self::FORM_ACCESS_PERMITALLUSERS,
	];
	public const FORM_ACCESS_ARRAY_SHOWN = [
		self::FORM_ACCESS_SHOWTOALLUSERS,
	];

	/**
	 * !! Keep in sync with src/models/AnswerTypes.js !!
	 */

	// Available AnswerTypes
	public const ANSWER_TYPE_COLOR = 'color';
	public const ANSWER_TYPE_CONDITIONAL = 'conditional';
	public const ANSWER_TYPE_DATE = 'date';
	public const ANSWER_TYPE_DATETIME = 'datetime';
	public const ANSWER_TYPE_DROPDOWN = 'dropdown';
	public const ANSWER_TYPE_FILE = 'file';
	public const ANSWER_TYPE_GRID = 'grid';
	public const ANSWER_TYPE_LINEARSCALE = 'linearscale';
	public const ANSWER_TYPE_LONG = 'long';
	public const ANSWER_TYPE_MULTIPLE = 'multiple';
	public const ANSWER_TYPE_MULTIPLEUNIQUE = 'multiple_unique';
	public const ANSWER_TYPE_SHORT = 'short';
	public const ANSWER_TYPE_TIME = 'time';

	public const ANSWER_GRID_TYPE_CHECKBOX = 'checkbox';
	public const ANSWER_GRID_TYPE_NUMBER = 'number';
	public const ANSWER_GRID_TYPE_RADIO = 'radio';

	// All AnswerTypes
	public const ANSWER_TYPES = [
		self::ANSWER_TYPE_COLOR,
		self::ANSWER_TYPE_CONDITIONAL,
		self::ANSWER_TYPE_DATE,
		self::ANSWER_TYPE_DATETIME,
		self::ANSWER_TYPE_DROPDOWN,
		self::ANSWER_TYPE_FILE,
		self::ANSWER_TYPE_GRID,
		self::ANSWER_TYPE_LINEARSCALE,
		self::ANSWER_TYPE_LONG,
		self::ANSWER_TYPE_MULTIPLE,
		self::ANSWER_TYPE_MULTIPLEUNIQUE,
		self::ANSWER_TYPE_SHORT,
		self::ANSWER_TYPE_TIME,
	];

	// AnswerTypes, that need/have predefined Options
	public const ANSWER_TYPES_PREDEFINED = [
		self::ANSWER_TYPE_DROPDOWN,
		self::ANSWER_TYPE_LINEARSCALE,
		self::ANSWER_TYPE_MULTIPLE,
		self::ANSWER_TYPE_MULTIPLEUNIQUE,
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

	/**
	 * !! Keep in sync with src/models/ValidationTypes.js !!
	 */

	// Allowed short input types
	public const SHORT_INPUT_TYPES = [
		'phone',
		'email',
		'regex',
		'number'
	];

	// This are allowed extra settings
	public const EXTRA_SETTINGS_DROPDOWN = [
		'allowOtherAnswer' => ['boolean'],
		'shuffleOptions' => ['boolean'],
	];

	public const EXTRA_SETTINGS_MULTIPLE = [
		'allowOtherAnswer' => ['boolean'],
		'optionsLimitMax' => ['integer'],
		'optionsLimitMin' => ['integer'],
		'shuffleOptions' => ['boolean'],
	];

	public const EXTRA_SETTINGS_SHORT = [
		'validationType' => ['string'],
		'validationRegex' => ['string'],
	];

	public const EXTRA_SETTINGS_FILE = [
		'allowedFileTypes' => ['array'],
		'allowedFileExtensions' => ['array'],
		'maxAllowedFilesCount' => ['integer'],
		'maxFileSize' => ['integer'],
	];

	public const EXTRA_SETTINGS_DATE = [
		'dateMax' => ['integer', 'NULL'],
		'dateMin' => ['integer', 'NULL'],
		'dateRange' => ['boolean', 'NULL'],
	];

	public const EXTRA_SETTINGS_TIME = [
		'timeMax' => ['string', 'NULL'],
		'timeMin' => ['string', 'NULL'],
		'timeRange' => ['boolean', 'NULL'],
	];

	// should be in sync with FileTypes.js
	public const EXTRA_SETTINGS_ALLOWED_FILE_TYPES = [
		'image',
		'x-office/document',
		'x-office/presentation',
		'x-office/spreadsheet',
	];

	public const EXTRA_SETTINGS_LINEARSCALE = [
		'optionsLowest' => ['integer', 'NULL'],
		'optionsHighest' => ['integer', 'NULL'],
		'optionsLabelLowest' => ['string', 'NULL'],
		'optionsLabelHighest' => ['string', 'NULL'],
	];

	public const EXTRA_SETTINGS_GRID = [
		'columns' => ['array'],
		'questionType' => ['string'],
		'rows' => ['array'],
	];

	public const EXTRA_SETTINGS_GRID_QUESTION_TYPE = [
		self::ANSWER_GRID_TYPE_CHECKBOX,
		self::ANSWER_GRID_TYPE_NUMBER,
		self::ANSWER_GRID_TYPE_RADIO,
	];

	/**
	 * Extra settings for conditional questions
	 * - triggerType: The question type used for the trigger (e.g., 'multiple_unique', 'dropdown', 'short')
	 * - branches: Array of branch definitions, each containing:
	 *   - id: Unique branch identifier
	 *   - conditions: Array of condition objects defining when this branch is active
	 *     For predefined types: [{ optionId: number }]
	 *     For text types: [{ type: 'string_equals'|'string_contains'|'regex', value: string }]
	 *     For numeric/scale: [{ type: 'value_equals'|'value_range', value: number, min?: number, max?: number }]
	 */
	public const EXTRA_SETTINGS_CONDITIONAL = [
		'triggerType' => ['string'],
		'branches' => ['array'],
	];

	/**
	 * Condition types for conditional questions
	 */
	public const CONDITION_TYPE_OPTION_SELECTED = 'option_selected';
	public const CONDITION_TYPE_OPTIONS_COMBINATION = 'options_combination';
	public const CONDITION_TYPE_STRING_EQUALS = 'string_equals';
	public const CONDITION_TYPE_STRING_CONTAINS = 'string_contains';
	public const CONDITION_TYPE_REGEX = 'regex';
	public const CONDITION_TYPE_VALUE_EQUALS = 'value_equals';
	public const CONDITION_TYPE_VALUE_RANGE = 'value_range';
	public const CONDITION_TYPE_DATE_RANGE = 'date_range';
	public const CONDITION_TYPE_FILE_UPLOADED = 'file_uploaded';

	public const CONDITION_TYPES = [
		self::CONDITION_TYPE_OPTION_SELECTED,
		self::CONDITION_TYPE_OPTIONS_COMBINATION,
		self::CONDITION_TYPE_STRING_EQUALS,
		self::CONDITION_TYPE_STRING_CONTAINS,
		self::CONDITION_TYPE_REGEX,
		self::CONDITION_TYPE_VALUE_EQUALS,
		self::CONDITION_TYPE_VALUE_RANGE,
		self::CONDITION_TYPE_DATE_RANGE,
		self::CONDITION_TYPE_FILE_UPLOADED,
	];

	/**
	 * Trigger types allowed for conditional questions
	 * Maps each trigger type to its supported condition types
	 */
	public const CONDITIONAL_TRIGGER_TYPES = [
		self::ANSWER_TYPE_MULTIPLEUNIQUE => [self::CONDITION_TYPE_OPTION_SELECTED],
		self::ANSWER_TYPE_DROPDOWN => [self::CONDITION_TYPE_OPTION_SELECTED],
		self::ANSWER_TYPE_MULTIPLE => [self::CONDITION_TYPE_OPTIONS_COMBINATION],
		self::ANSWER_TYPE_SHORT => [self::CONDITION_TYPE_STRING_EQUALS, self::CONDITION_TYPE_STRING_CONTAINS, self::CONDITION_TYPE_REGEX],
		self::ANSWER_TYPE_LONG => [self::CONDITION_TYPE_STRING_CONTAINS, self::CONDITION_TYPE_REGEX],
		self::ANSWER_TYPE_LINEARSCALE => [self::CONDITION_TYPE_VALUE_EQUALS, self::CONDITION_TYPE_VALUE_RANGE],
		self::ANSWER_TYPE_DATE => [self::CONDITION_TYPE_DATE_RANGE],
		self::ANSWER_TYPE_DATETIME => [self::CONDITION_TYPE_DATE_RANGE],
		self::ANSWER_TYPE_TIME => [self::CONDITION_TYPE_VALUE_RANGE],
		self::ANSWER_TYPE_COLOR => [self::CONDITION_TYPE_VALUE_EQUALS],
		self::ANSWER_TYPE_FILE => [self::CONDITION_TYPE_FILE_UPLOADED],
	];

	public const FILENAME_INVALID_CHARS = [
		"\n",
		'/',
		'\\',
		':',
		'*',
		'?',
		'"',
		'<',
		'>',
		'|',
	];

	/**
	 * !! Keep in sync with src/mixins/ShareTypes.js !!
	 */
	public const SHARE_TYPES_USED = [
		IShare::TYPE_CIRCLE,
		IShare::TYPE_GROUP,
		IShare::TYPE_LINK,
		IShare::TYPE_USER,
	];

	/**
	 * !! Keep in sync with src/mixins/PermissionTypes.js !!
	 * Permission values equal the route names, thus making it easy on frontend to evaluate.
	 */
	// Define Form Permissions
	public const PERMISSION_EDIT = 'edit';
	public const PERMISSION_RESULTS = 'results';
	public const PERMISSION_RESULTS_DELETE = 'results_delete';
	public const PERMISSION_SUBMIT = 'submit';
	/** Special internal permissions to allow embedding a form (share) into external websites */
	public const PERMISSION_EMBED = 'embed';

	public const PERMISSION_ALL = [
		self::PERMISSION_EDIT,
		self::PERMISSION_EMBED,
		self::PERMISSION_RESULTS,
		self::PERMISSION_RESULTS_DELETE,
		self::PERMISSION_SUBMIT,
	];

	/**
	 * !! Keep in sync with src/FormsEmptyContent.vue !!
	 * InitialStates for emptyContent to render as...
	 */
	public const EMPTY_EXPIRED = 'expired';
	public const EMPTY_NOTFOUND = 'notfound';

	/**
	 * Constants related to extra settings for questions
	 */
	public const QUESTION_EXTRASETTINGS_OTHER_PREFIX = 'system-other-answer:';

	public const SUPPORTED_EXPORT_FORMATS = [
		'csv' => 'text/csv',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	];

	public const DEFAULT_FILE_FORMAT = 'csv';

	public const UNSUBMITTED_FILES_FOLDER = self::FILES_FOLDER . '/unsubmitted';

	public const FILES_FOLDER = 'Forms';
}
