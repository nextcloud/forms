/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @license GNU AGPL version 3 or any later version
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
 */

import QuestionMultiple from '../components/Questions/QuestionMultiple'
import QuestionDropdown from '../components/Questions/QuestionDropdown'
import QuestionShort from '../components/Questions/QuestionShort'
import QuestionLong from '../components/Questions/QuestionLong'
import QuestionDate from '../components/Questions/QuestionDate'

/**
 * @typedef {object} AnswerTypes
 * @property {string} multiple Checkbox Answer
 * @property {string} multiple_unique Multiple-Choice Answer
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
		icon: 'icon-answer-checkbox',
		label: t('forms', 'Checkboxes'),
		predefined: true,
		validate: question => question.options.length > 0,

		titlePlaceholder: t('forms', 'Checkbox question title'),
		warningInvalid: t('forms', 'This question needs a title and at least one answer!'),
	},

	multiple_unique: {
		component: QuestionMultiple,
		icon: 'icon-answer-multiple',
		// TRANSLATORS Take care, a translation by word might not match! The english called 'Multiple-Choice' only allows to select a single-option (basically single-choice)!
		label: t('forms', 'Multiple choice'),
		predefined: true,
		validate: question => question.options.length > 0,

		titlePlaceholder: t('forms', 'Multiple choice question title'),
		warningInvalid: t('forms', 'This question needs a title and at least one answer!'),

		// Using the same vue-component as multiple, this specifies that the component renders as multiple_unique.
		unique: true,
	},

	dropdown: {
		component: QuestionDropdown,
		icon: 'icon-answer-dropdown',
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
		icon: 'icon-answer-short',
		label: t('forms', 'Short answer'),
		predefined: false,

		titlePlaceholder: t('forms', 'Short answer question title'),
		createPlaceholder: t('forms', 'People can enter a short answer'),
		submitPlaceholder: t('forms', 'Enter a short answer'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	long: {
		component: QuestionLong,
		icon: 'icon-answer-long',
		label: t('forms', 'Long text'),
		predefined: false,

		titlePlaceholder: t('forms', 'Long text question title'),
		createPlaceholder: t('forms', 'People can enter a long text'),
		submitPlaceholder: t('forms', 'Enter a long text'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	date: {
		component: QuestionDate,
		icon: 'icon-answer-date',
		label: t('forms', 'Date'),
		predefined: false,

		titlePlaceholder: t('forms', 'Date question title'),
		createPlaceholder: t('forms', 'People can pick a date'),
		submitPlaceholder: t('forms', 'Pick a date'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	datetime: {
		component: QuestionDate,
		icon: 'icon-answer-datetime',
		label: t('forms', 'Datetime'),
		predefined: false,

		titlePlaceholder: t('forms', 'Datetime question title'),
		createPlaceholder: t('forms', 'People can pick a date and time'),
		submitPlaceholder: t('forms', 'Pick a date and time'),
		warningInvalid: t('forms', 'This question needs a title!'),

		// Using the same vue-component as date, this specifies that the component renders as datetime.
		includeTime: true,
	},

	time: {
		component: QuestionDate,
		icon: 'icon-answer-time',
		label: t('forms', 'Time'),
		predefined: false,

		titlePlaceholder: t('forms', 'Time question title'),
		createPlaceholder: t('forms', 'People can pick a time'),
		submitPlaceholder: t('forms', 'Pick a time'),
		warningInvalid: t('forms', 'This question needs a title!'),

		// Using the same vue-component as date, this specifies that the component renders as time.
		onlyTime: true,
	},
}
