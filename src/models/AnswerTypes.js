/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import QuestionColor from '../components/Questions/QuestionColor.vue'
import QuestionDate from '../components/Questions/QuestionDate.vue'
import QuestionDropdown from '../components/Questions/QuestionDropdown.vue'
import QuestionFile from '../components/Questions/QuestionFile.vue'
import QuestionGrid from '../components/Questions/QuestionGrid.vue'
import QuestionLinearScale from '../components/Questions/QuestionLinearScale.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'

import IconArrowDownDropCircleOutline from 'vue-material-design-icons/ArrowDownDropCircleOutline.vue'
import IconCalendar from 'vue-material-design-icons/CalendarOutline.vue'
import IconCheckboxOutline from 'vue-material-design-icons/CheckboxOutline.vue'
import IconClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import IconFile from 'vue-material-design-icons/FileOutline.vue'
import IconGrid from 'vue-material-design-icons/Grid.vue'
import IconLinearScale from '../components/Icons/IconLinearScale.vue'
import IconPalette from '../components/Icons/IconPalette.vue'
import IconRadioboxMarked from 'vue-material-design-icons/RadioboxMarked.vue'
import IconTextLong from 'vue-material-design-icons/TextLong.vue'
import IconTextShort from 'vue-material-design-icons/TextShort.vue'
import IconNumeric from 'vue-material-design-icons/Numeric.vue'
import IconRadioboxBlank from "vue-material-design-icons/RadioboxBlank.vue";

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
		component: QuestionMultiple,
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
		component: QuestionMultiple,
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
		component: QuestionDropdown,
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
		component: QuestionFile,
		icon: IconFile,
		label: t('forms', 'File'),
		predefined: false,

		titlePlaceholder: t('forms', 'File question title'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	grid: {
		component: QuestionGrid,
		icon: IconGrid,
		label: t('forms', 'Grid'),
        // fixme: remove non-needed properties
		predefined: false,

        subtypes: {
            radio: {
                label: t('forms', 'Radio'),
                icon: IconRadioboxBlank,
                extraSettings: {
                    questionType: 'radio',
                },
            },
            checkbox: {
                label: t('forms', 'Checkbox'),
                icon: IconCheckboxOutline,
                extraSettings: {
                    questionType: 'checkbox',
                },
            },
            number: {
                label: t('forms', 'Number'),
                icon: IconNumeric,
                extraSettings: {
                    questionType: 'number',
                },
            },
            text: {
                label: t('forms', 'Text'),
                icon: IconTextShort,
                extraSettings: {
                    questionType: 'text',
                },
            }
        },

        validate: (question) => question.options.length > 0,

        titlePlaceholder: t('forms', 'Grid question title'),
        warningInvalid: t('forms', 'This question needs a title!'),
        createPlaceholder: t('forms', 'People can submit a different answer'),
        submitPlaceholder: t('forms', 'Enter your answer'),
    },

	short: {
		component: QuestionShort,
		icon: IconTextShort,
		label: t('forms', 'Short answer'),
		predefined: false,

		titlePlaceholder: t('forms', 'Short answer question title'),
		createPlaceholder: t('forms', 'People can enter a short answer'),
		submitPlaceholder: t('forms', 'Enter your answer'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	long: {
		component: QuestionLong,
		icon: IconTextLong,
		label: t('forms', 'Long text'),
		predefined: false,

		titlePlaceholder: t('forms', 'Long text question title'),
		createPlaceholder: t('forms', 'People can enter a long text'),
		submitPlaceholder: t('forms', 'Enter your answer'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	date: {
		component: QuestionDate,
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
		component: QuestionDate,
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
		component: QuestionDate,
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
		component: QuestionLinearScale,
		icon: IconLinearScale,
		label: t('forms', 'Linear scale'),
		predefined: true,

		titlePlaceholder: t('forms', 'Linear scale question title'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	color: {
		component: QuestionColor,
		icon: IconPalette,
		label: t('forms', 'Color'),
		predefined: false,

		titlePlaceholder: t('forms', 'Color question title'),
		createPlaceholder: t('forms', 'People can pick a color'),
		submitPlaceholder: t('forms', 'Pick a color'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},
}
