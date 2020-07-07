/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
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
 *
 */

import QuestionMultiple from '../components/Questions/QuestionMultiple'
import QuestionDropdown from '../components/Questions/QuestionDropdown'
import QuestionShort from '../components/Questions/QuestionShort'
import QuestionLong from '../components/Questions/QuestionLong'

/**
 * @typedef {Object} AnswerTypes
 * @property {string} multiple
 * @property {string} multiple_unique
 * @property {string} dropdown
 * @property {string} short
 * @property {string} long
 */
export default {
	/**
	 * Specifying Question-Models in a common place
	 * Further type-specific parameters are possible.
	 * @prop component The vue-component this answer-type relies on
	 * @prop icon The icon corresponding to this answer-type
	 * @prop label The answer-type label, that users will see as answer-type.
	 * @prop validate *optional* Define conditions where this question is not ok
	 *
	 * @prop titlePlaceholder The placeholder users see as empty question-title in edit-mode
	 * @prop createPlaceholder *optional* The placeholder that is visible in edit-mode, to indicate a submission form-input field
	 * @prop submitPlaceholder *optional* The placeholder that is visible in submit-mode, to indicate a form input-field
	 * @prop warningInvalid The warning users see in edit mode, if the question is invalid.
	 */

	multiple: {
		component: QuestionMultiple,
		icon: 'icon-answer-checkbox',
		label: t('forms', 'Checkboxes'),
		validate: question => question.options.length > 0,

		titlePlaceholder: t('forms', 'Checkbox question title'),
		warningInvalid: t('forms', 'This question needs a title and at least one answer!'),
	},

	multiple_unique: {
		component: QuestionMultiple,
		icon: 'icon-answer-multiple',
		label: t('forms', 'Multiple choice'),
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

		titlePlaceholder: t('forms', 'Short answer question title'),
		createPlaceholder: t('forms', 'People can enter a short answer'),
		submitPlaceholder: t('forms', 'Enter a short answer'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

	long: {
		component: QuestionLong,
		icon: 'icon-answer-long',
		label: t('forms', 'Long text'),

		titlePlaceholder: t('forms', 'Long text question title'),
		createPlaceholder: t('forms', 'People can enter a long text'),
		submitPlaceholder: t('forms', 'Enter a long text'),
		warningInvalid: t('forms', 'This question needs a title!'),
	},

}
