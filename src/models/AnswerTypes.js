/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import IconNumeric from '@material-symbols/svg-400/outlined/123.svg?raw'
import IconArrowDownDropCircleOutline from '@material-symbols/svg-400/outlined/arrow_drop_down_circle.svg?raw'
import IconCalendar from '@material-symbols/svg-400/outlined/calendar_today.svg?raw'
import IconCheckboxOutline from '@material-symbols/svg-400/outlined/check_box.svg?raw'
import IconFile from '@material-symbols/svg-400/outlined/draft.svg?raw'
import IconGrid from '@material-symbols/svg-400/outlined/grid_view.svg?raw'
import IconLinearScale from '@material-symbols/svg-400/outlined/linear_scale.svg?raw'
import IconPalette from '@material-symbols/svg-400/outlined/palette.svg?raw'
import IconRadioboxMarked from '@material-symbols/svg-400/outlined/radio_button_checked.svg?raw'
import IconClockOutline from '@material-symbols/svg-400/outlined/schedule.svg?raw'
import IconTextShort from '@material-symbols/svg-400/outlined/short_text.svg?raw'
import IconTextLong from '@material-symbols/svg-400/outlined/subject.svg?raw'
import IconSwapVertical from '@material-symbols/svg-400/outlined/swap_vert.svg?raw'
import { markRaw } from 'vue'
import QuestionColor from '../components/Questions/QuestionColor.vue'
import QuestionDate from '../components/Questions/QuestionDate.vue'
import QuestionDropdown from '../components/Questions/QuestionDropdown.vue'
import QuestionFile from '../components/Questions/QuestionFile.vue'
import QuestionGrid from '../components/Questions/QuestionGrid.vue'
import QuestionLinearScale from '../components/Questions/QuestionLinearScale.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import QuestionRanking from '../components/Questions/QuestionRanking.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'
import { OptionType } from './Constants.ts'

/**
 * @typedef {object} AnswerTypes
 * @property {string} multiple Checkbox Answer
 * @property {string} multiple_unique Radio buttons Answer
 * @property {string} dropdown Dropdown Answer
 * @property {string} short Short Text Answer
 * @property {string} long Long Text Answer
 * @property {string} date Date Answer
 * @property {string} datetime Date and Time Answer
 * @property {string} time Time Answer
 * @property {string} linearscale Linear Scale Answer
 * @property {string} color Color Answer
 */
export default {
	/**
	 * !! Keep in SYNC with lib/Constants.php for props that are necessary on php !!
	 * Specifying Question-Models in a common place
	 * Further type-specific parameters are possible.
	 *
	 * @property {object} component The vue-component this answer-type relies on
	 * @property {string} icon The icon corresponding to this answer-type
	 * @property {string} label The answer-type label, that users will see as answer-type.
	 * @property {boolean} predefined SYNC This AnswerType has/needs predefined Options.
	 * @property {Function} validate *optional* Define conditions where this question is not ok
	 * @property {string} titlePlaceholder The placeholder users see as empty question-title in edit-mode
	 * @property {string} createPlaceholder *optional* The placeholder that is visible in edit-mode, to indicate a submission form-input field
	 * @property {string} createPlaceholderRange *optional* The placeholder that is visible in edit-mode, to indicate a submission form-input field for date fields that use a date range
	 * @property {string} submitPlaceholder *optional* The placeholder that is visible in submit-mode, to indicate a form input-field
	 * @property {string} submitPlaceholderRange *optional* The placeholder that is visible in submit-mode, to indicate a form input-field for date fields that use a date range
	 * @property {string} warningInvalid The warning users see in edit mode, if the question is invalid.
	 */

	multiple: {
		component: markRaw(QuestionMultiple),
		icon: IconCheckboxOutline,
		label: t('forms', 'Checkboxes'),
		predefined: true,
		validate: (question) => question.options.length > 0,

		titlePlaceholder: t('forms', 'Checkbox question title'),
		createPlaceholder: t('forms', 'People can submit a different answer'),
		submitPlaceholder: t('forms', 'Enter your answer'),
		warningInvalid: t(
			'forms',
			'This question needs a title and at least one answer!',
		),
	},

	multiple_unique: {
		component: markRaw(QuestionMultiple),
		icon: IconRadioboxMarked,
		label: t('forms', 'Radio buttons'),
		predefined: true,
		validate: (question) => question.options.length > 0,

		titlePlaceholder: t('forms', 'Radio buttons question title'),
		createPlaceholder: t('forms', 'People can submit a different answer'),
		submitPlaceholder: t('forms', 'Enter your answer'),
		warningInvalid: t(
			'forms',
			'This question needs a title and at least one answer!',
		),

		// Using the same vue-component as multiple, this specifies that the component renders as multiple_unique.
		unique: true,
	},

	dropdown: {
		component: markRaw(QuestionDropdown),
		icon: IconArrowDownDropCircleOutline,
		label: t('forms', 'Dropdown'),
		predefined: true,
		validate: (question) => question.options.length > 0,

		titlePlaceholder: t('forms', 'Dropdown question title'),
		createPlaceholder: t('forms', 'People can pick one option'),
		submitPlaceholder: t('forms', 'Pick an option'),
		warningInvalid: t(
			'forms',
			'This question needs a title and at least one answer!',
		),
	},

	file: {
		component: markRaw(QuestionFile),
		icon: IconFile,
		label: t('forms', 'File'),
		predefined: false,

		titlePlaceholder: t('forms', 'File question title'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	grid: {
		component: markRaw(QuestionGrid),
		icon: IconGrid,
		label: t('forms', 'Grid'),
		predefined: false,

		subtypes: {
			radio: {
				icon: IconRadioboxMarked,
				label: t('forms', 'Radio buttons'),
				extraSettings: {
					questionType: 'radio',
				},
			},
			checkbox: {
				icon: IconCheckboxOutline,
				label: t('forms', 'Checkboxes'),
				extraSettings: {
					questionType: 'checkbox',
				},
			},
			number: {
				icon: IconNumeric,
				label: t('forms', 'Number'),
				extraSettings: {
					questionType: 'number',
				},
			},
		},

		validate: (question) => {
			return (
				question.options.filter(
					(option) => option.optionType === OptionType.Column,
				).length > 0
				&& question.options.filter(
					(option) => option.optionType === OptionType.Row,
				).length > 0
			)
		},

		titlePlaceholder: t('forms', 'Grid question title'),
		warningInvalid: t('forms', 'This question needs a title!'),
		createPlaceholder: t('forms', 'People can submit a grid answer'),
		submitPlaceholder: t('forms', 'Enter your answer'),
	},

	short: {
		component: markRaw(QuestionShort),
		icon: IconTextShort,
		label: t('forms', 'Short answer'),
		predefined: false,

		titlePlaceholder: t('forms', 'Short answer question title'),
		createPlaceholder: t('forms', 'People can enter a short answer'),
		submitPlaceholder: t('forms', 'Enter your answer'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	long: {
		component: markRaw(QuestionLong),
		icon: IconTextLong,
		label: t('forms', 'Long text'),
		predefined: false,

		titlePlaceholder: t('forms', 'Long text question title'),
		createPlaceholder: t('forms', 'People can enter a long text'),
		submitPlaceholder: t('forms', 'Enter your answer'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	date: {
		component: markRaw(QuestionDate),
		icon: IconCalendar,
		label: t('forms', 'Date'),
		predefined: false,

		titlePlaceholder: t('forms', 'Date question title'),
		createPlaceholder: t('forms', 'People can pick a date'),
		createPlaceholderRange: t('forms', 'People can pick a date range'),
		submitPlaceholder: t('forms', 'Pick a date'),
		submitPlaceholderRange: t('forms', 'Pick a date range'),
		warningInvalid: t('forms', 'This question needs a title!'),

		pickerType: 'date',
		storageFormat: 'YYYY-MM-DD',
		momentFormat: 'L',
	},

	datetime: {
		component: markRaw(QuestionDate),
		icon: IconClockOutline,
		label: t('forms', 'Datetime'),
		predefined: false,

		titlePlaceholder: t('forms', 'Datetime question title'),
		createPlaceholder: t('forms', 'People can pick a date and time'),
		submitPlaceholder: t('forms', 'Pick a date and time'),
		warningInvalid: t('forms', 'This question needs a title!'),

		pickerType: 'datetime',
		storageFormat: 'YYYY-MM-DD HH:mm',
		momentFormat: 'LLL',
	},

	time: {
		component: markRaw(QuestionDate),
		icon: IconClockOutline,
		label: t('forms', 'Time'),
		predefined: false,

		titlePlaceholder: t('forms', 'Time question title'),
		createPlaceholder: t('forms', 'People can pick a time'),
		createPlaceholderRange: t('forms', 'People can pick a time range'),
		submitPlaceholder: t('forms', 'Pick a time'),
		submitPlaceholderRange: t('forms', 'Pick a time range'),
		warningInvalid: t('forms', 'This question needs a title!'),

		pickerType: 'time',
		storageFormat: 'HH:mm',
		momentFormat: 'LT',
	},

	linearscale: {
		component: markRaw(QuestionLinearScale),
		icon: IconLinearScale,
		label: t('forms', 'Linear scale'),
		predefined: true,

		titlePlaceholder: t('forms', 'Linear scale question title'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	color: {
		component: markRaw(QuestionColor),
		icon: IconPalette,
		label: t('forms', 'Color'),
		predefined: false,

		titlePlaceholder: t('forms', 'Color question title'),
		createPlaceholder: t('forms', 'People can pick a color'),
		submitPlaceholder: t('forms', 'Pick a color'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	ranking: {
		component: markRaw(QuestionRanking),
		icon: IconSwapVertical,
		label: t('forms', 'Ranking'),
		predefined: true,
		validate: (question) => question.options.length > 0,

		titlePlaceholder: t('forms', 'Ranking question title'),
		createPlaceholder: t('forms', 'People can rank options'),
		submitPlaceholder: t('forms', 'Drag to rank'),
		warningInvalid: t(
			'forms',
			'This question needs a title and at least one answer!',
		),
	},
}
