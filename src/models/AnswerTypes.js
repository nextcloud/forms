/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import QuestionMultiple from '../components/Questions/QuestionMultiple.vue'
import QuestionDropdown from '../components/Questions/QuestionDropdown.vue'
import QuestionShort from '../components/Questions/QuestionShort.vue'
import QuestionLong from '../components/Questions/QuestionLong.vue'
import QuestionDate from '../components/Questions/QuestionDate.vue'

import IconCheckboxOutline from 'vue-material-design-icons/CheckboxOutline.vue'
import IconRadioboxMarked from 'vue-material-design-icons/RadioboxMarked.vue'
import IconArrowDownDropCircleOutline from 'vue-material-design-icons/ArrowDownDropCircleOutline.vue'
import IconTextShort from 'vue-material-design-icons/TextShort.vue'
import IconTextLong from 'vue-material-design-icons/TextLong.vue'
import IconCalendar from 'vue-material-design-icons/Calendar.vue'
import IconClockOutline from 'vue-material-design-icons/ClockOutline.vue'

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
	 * @property {string} submitPlaceholder *optional* The placeholder that is visible in submit-mode, to indicate a form input-field
	 * @property {string} warningInvalid The warning users see in edit mode, if the question is invalid.
	 */

	multiple: {
		component: QuestionMultiple,
		icon: IconCheckboxOutline,
		label: t('forms', 'Checkboxes'),
		predefined: true,
		validate: question => question.options.length > 0,

		titlePlaceholder: t('forms', 'Checkbox question title'),
		warningInvalid: t('forms', 'This question needs a title and at least one answer!'),
	},

	multiple_unique: {
		component: QuestionMultiple,
		icon: IconRadioboxMarked,
		label: t('forms', 'Radio buttons'),
		predefined: true,
		validate: question => question.options.length > 0,

		titlePlaceholder: t('forms', 'Radio buttons question title'),
		warningInvalid: t('forms', 'This question needs a title and at least one answer!'),

		// Using the same vue-component as multiple, this specifies that the component renders as multiple_unique.
		unique: true,
	},

	dropdown: {
		component: QuestionDropdown,
		icon: IconArrowDownDropCircleOutline,
		label: t('forms', 'Dropdown'),
		predefined: true,
		validate: question => question.options.length > 0,

		titlePlaceholder: t('forms', 'Dropdown question title'),
		createPlaceholder: t('forms', 'People can pick one option'),
		submitPlaceholder: t('forms', 'Pick an option'),
		warningInvalid: t('forms', 'This question needs a title and at least one answer!'),
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
		submitPlaceholder: t('forms', 'Pick a date'),
		warningInvalid: t('forms', 'This question needs a title!'),

		pickerType: 'date',
		storageFormat: 'YYYY-MM-DD',
		momentFormat: 'LL',
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
		submitPlaceholder: t('forms', 'Pick a time'),
		warningInvalid: t('forms', 'This question needs a title!'),

		pickerType: 'time',
		storageFormat: 'HH:mm',
		momentFormat: 'LT',
	},
}
