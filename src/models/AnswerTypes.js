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

import QuestionLong from '../components/Questions/QuestionLong'
import QuestionShort from '../components/Questions/QuestionShort'
import QuestionMultiple from '../components/Questions/QuestionMultiple'

/**
 * @typedef {Object} AnswerTypes
 * @property {string} multiple_unique
 * @property {string} multiple
 * @property {string} short
 * @property {string} long
 */
export default {

	multiple_unique: {
		component: QuestionMultiple,
		icon: 'icon-answer-multiple',
		label: t('forms', 'Multiple choice'),
		titlePlaceholder: t('forms', 'MultiUniqueTitlePlaceholder'),
		unique: true,
		// Define conditions where this questions is not ok
		validate: question => question.options.length > 0,
	},

	multiple: {
		component: QuestionMultiple,
		icon: 'icon-answer-checkbox',
		label: t('forms', 'Checkboxes'),
		titlePlaceholder: t('forms', 'MultiTitlePlaceholder'),
		// Define conditions where this questions is not ok
		validate: question => question.options.length > 0,
	},

	short: {
		component: QuestionShort,
		icon: 'icon-answer-short',
		label: t('forms', 'Short answer'),

		titlePlaceholder: t('forms', 'ShortTitlePlaceholder'),
		submitPlaceholder: t('forms', 'Enter a short answer'),
		createPlaceholder: t('forms', 'People can enter a short answer'),
	},

	long: {
		component: QuestionLong,
		icon: 'icon-answer-long',
		label: t('forms', 'Long text'),

		titlePlaceholder: t('forms', 'LongTitlePlaceholder'),
		submitPlaceholder: t('forms', 'Enter a long text'),
		createPlaceholder: t('forms', 'People can enter a long text'),
	},

}
