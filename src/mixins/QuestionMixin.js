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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
import Question from '../components/Questions/Question'

export default {
	inheritAttrs: false,
	props: {
		/**
		 * The question title
		 */
		text: {
			type: String,
			required: true,
		},

		/**
		 * The user answers
		 */
		values: {
			type: Array,
			default() {
				return []
			},
		},

		/**
		 * The question list of answers
		 */
		options: {
			type: Array,
			required: true,
		},

		/**
		 * Answer type model object
		 */
		model: {
			type: Object,
			required: true,
		},
	},

	components: {
		Question,
	},

	data() {
		return {
			// Do we display this question in edit or fill mode
			edit: false,
		}
	},

	methods: {
		/**
		 * Forward the title change to the parent
		 *
		 * @param {string} text the title
		 */
		onTitleChange(text) {
			this.$emit('update:text', text)
		},

		/**
		 * Forward the answer(s) change to the parent
		 *
		 * @param {Array} values the array of answers
		 */
		onValuesChange(values) {
			this.$emit('update:values', values)
		},

		/**
		 * Delete this question
		 */
		onDelete() {
			this.$emit('delete')
		},
	},
}
