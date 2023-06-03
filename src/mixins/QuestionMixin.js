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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { debounce } from 'debounce'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import GenRandomId from '../utils/GenRandomId.js'

import logger from '../utils/Logger.js'
import Question from '../components/Questions/Question.vue'

export default {
	inheritAttrs: false,
	props: {

		/**
		 * Question-Id
		 */
		id: {
			type: Number,
			required: true,
		},

		/**
		 * The question title
		 */
		text: {
			type: String,
			required: true,
		},

		/**
		 * Question Description
		 */
		description: {
			type: String,
			required: true,
		},

		/**
		 * Required-Setting
		 */
		isRequired: {
			type: Boolean,
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
		answerType: {
			type: Object,
			required: true,
		},

		/**
		 * Submission or Edit-Mode
		 */
		readOnly: {
			type: Boolean,
			default: false,
		},

		/**
		 * Database-Restrictions
		 */
		maxStringLengths: {
			type: Object,
			required: true,
		},

		/**
		 * Extra settings
		 * Currently only contains whether options should be shuffled
		 */
		extraSettings: {
			type: Object,
			default: () => {
				return {}
			},
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

	computed: {
		sortedOptions() {
			// Only shuffle options if not in editing mode (and shuffling is enabled)
			if (!this.edit && this.extraSettings?.shuffleOptions) {
				return this.shuffleArray(this.options)
			}
			// Ensure order of options always is the same
			return [...this.options].sort((a, b) => a.id - b.id)
		},
	},

	methods: {
		/**
		 * Forward the title change to the parent and store to db
		 *
		 * @param {string} text the title
		 */
		onTitleChange: debounce(function(text) {
			this.$emit('update:text', text)
			this.saveQuestionProperty('text', text)
		}, 200),
		/**
		 * Forward the description change to the parent and store to db
		 *
		 * @param {string} description the description
		 */
		onDescriptionChange: debounce(function(description) {
			this.$emit('update:description', description)
			this.saveQuestionProperty('description', description)
		}, 200),

		/**
		 * Forward the required change to the parent and store to db
		 *
		 * @param {boolean} isRequiredValue new isRequired Value
		 */
		onRequiredChange: debounce(function(isRequiredValue) {
			this.$emit('update:isRequired', isRequiredValue)
			this.saveQuestionProperty('isRequired', isRequiredValue)
		}, 200),

		/**
		 * Create mapper to forward the required change to the parent and store to db
		 *
		 * @param {string} parameter Name of the setting that changed
		 * @param {any} value New value of the setting
		 */
		onExtraSettingsChange: debounce(function(parameter, value) {
			const newSettings = Object.assign({}, this.extraSettings)
			newSettings[parameter] = value
			this.$emit('update:extraSettings', newSettings)
			this.saveQuestionProperty('extraSettings', newSettings)
		}, 200),

		/**
		 * Forward the required change to the parent and store to db
		 *
		 * @param {boolean} shuffle Should options be shuffled
		 */
		onShuffleOptionsChange(shuffle) {
			return this.onExtraSettingsChange('shuffleOptions', shuffle)
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

		/**
		 * Don't automatically submit form on Enter, parent will handle that
		 * To be called with prevent: @keydown.enter.prevent="onKeydownEnter"
		 *
		 * @param {object} event The fired event
		 */
		onKeydownEnter(event) {
			this.$emit('keydown', event)
		},

		/**
		 * Focus the first focusable element
		 */
		focus() {
			this.edit = true
			this.$el.scrollIntoView({ behavior: 'smooth' })
			this.$nextTick(() => {
				const title = this.$el.querySelector('.question__header-title')
				if (title) {
					title.select()
				}
			})
		},

		/**
		 * Shuffle an array using Fisher-Yates
		 *
		 * @param {Array} input Input array to shuffle
		 * @return {Array} Shuffled input array
		 */
		shuffleArray(input) {
			const shuffled = [...input]
			let idx = shuffled.length
			while (--idx > 0) {
				const rndIdx = Math.floor(Math.random() * (idx + 1));
				[shuffled[rndIdx], shuffled[idx]] = [shuffled[idx], shuffled[rndIdx]]
			}
			return shuffled
		},

		async saveQuestionProperty(key, value) {
			try {
				// TODO: add loading status feedback ?
				await axios.post(generateOcsUrl('apps/forms/api/v2.1/question/update'), {
					id: this.id,
					keyValuePairs: {
						[key]: value,
					},
				})
				emit('forms:last-updated:set', this.$attrs.formId)
			} catch (error) {
				logger.error('Error while saving question', { error })
				showError(t('forms', 'Error while saving question'))
			}
		},
		async handleMultipleOptions(answers) {
			this.edit = true
			const options = this.options.slice()
			for (let i = 0; i < answers.length; i++) {
				options.push({
					id: GenRandomId(),
					questionId: this.id,
					text: answers[i],
					local: false,
				})

				await axios.post(generateOcsUrl('apps/forms/api/v2/option'), {
					questionId: this.id,
					text: answers[i],
				})
			}
			options.push({
				id: GenRandomId(),
				questionId: this.id,
				text: '',
				local: true,
			})
			this.updateOptions(options)
			this.$nextTick(() => {
				this.focusIndex(options.length - 1)
			})
		},
	},
}
