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
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import debounce from 'debounce'

import logger from '../utils/Logger.js'
import GenRandomId from '../utils/GenRandomId.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
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
		 * ID of the form
		 */
		formId: {
			type: Number,
			default: null,
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
		 * The index of the question
		 */
		index: {
			type: Number,
			required: true,
		},

		/**
		 * Technical name
		 */
		name: {
			type: String,
			default: '',
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
		 * Order of the question
		 */
		order: {
			type: Number,
			default: -1,
		},

		/**
		 * Question type
		 */
		type: {
			type: String,
			default: null,
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
		 */
		extraSettings: {
			default: () => {
				return {}
			},
		},

		/**
		 * Mime-Types and file extensions that are allowed to be uploaded
		 */
		accept: {
			type: Array,
			default() {
				return []
			},
		},

		/**
		 * Can question be moved up in order?
		 */
		canMoveUp: {
			type: Boolean,
			default: false,
		},

		/**
		 * Can question be moved down in order?
		 */
		canMoveDown: {
			type: Boolean,
			default: false,
		},

		/**
		 * isComposing for IME handling
		 */
		isIMEComposing: {
			type: Boolean,
			default: false,
		},
	},

	components: {
		Question,
	},

	computed: {
		questionProps() {
			const props = { ...this.$props }
			const allowedKeys = Object.keys(Question.props)
			Object.keys(props).forEach((key) => {
				if (!allowedKeys.includes(key)) {
					delete props[key]
				}
			})
			return props
		},
		sortedOptions() {
			// Only shuffle options if not in editing mode (and shuffling is enabled)
			if (this.readOnly && this.extraSettings?.shuffleOptions) {
				return this.shuffleArray(this.options)
			}
			// Ensure order of options always is the same
			return [...this.options].sort((a, b) => a.id - b.id)
		},

		/**
		 * Listeners for all questions to forward
		 */
		commonListeners() {
			return {
				clone: this.onClone,
				delete: this.onDelete,
				'update:text': this.onTitleChange,
				'update:description': this.onDescriptionChange,
				'update:isRequired': this.onRequiredChange,
				'update:name': this.onNameChange,
				'move-down': (...args) => this.$emit('move-down', ...args),
				'move-up': (...args) => this.$emit('move-up', ...args),
			}
		},
	},

	methods: {
		/**
		 * Override to allow custom validation
		 */
		async validate() {
			return true
		},

		/**
		 * Forward the title change to the parent and store to db
		 *
		 * @param {string} text the title
		 */
		onTitleChange: debounce(function (text) {
			this.$emit('update:text', text)
			this.saveQuestionProperty('text', text)
		}, 200),
		/**
		 * Forward the description change to the parent and store to db
		 *
		 * @param {string} description the description
		 */
		onDescriptionChange: debounce(function (description) {
			this.$emit('update:description', description)
			this.saveQuestionProperty('description', description)
		}, 200),

		/**
		 * Forward the required change to the parent and store to db
		 *
		 * @param {boolean} isRequiredValue new isRequired Value
		 */
		onRequiredChange: debounce(function (isRequiredValue) {
			this.$emit('update:isRequired', isRequiredValue)
			this.saveQuestionProperty('isRequired', isRequiredValue)
		}, 200),

		/**
		 * Create mapper to forward the required change to the parent and store to db
		 *
		 * Either an object containing the *changed* settings.
		 *
		 * @param {object} newSettings changed settings
		 */
		onExtraSettingsChange: debounce(function (newSettings) {
			const newExtraSettings = { ...this.extraSettings, ...newSettings }
			this.$emit('update:extraSettings', newExtraSettings)
			this.saveQuestionProperty('extraSettings', newExtraSettings)
		}, 200),

		/**
		 * Forward the technical-name change to the parent and store to db
		 *
		 * @param {string} name The new technical name of the input
		 */
		onNameChange: debounce(function (name) {
			this.$emit('update:name', name)
			this.saveQuestionProperty('name', name)
		}, 200),

		/**
		 * Forward the required change to the parent and store to db
		 *
		 * @param {boolean} shuffle Should options be shuffled
		 */
		onShuffleOptionsChange(shuffle) {
			return this.onExtraSettingsChange({ shuffleOptions: shuffle })
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
		 * Clone this question.
		 */
		onClone() {
			this.$emit('clone')
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
			this.$el.scrollIntoView({ behavior: 'smooth' })
			this.$nextTick(() => {
				const title = this.$el.querySelector(
					'.question__header__title__text__input',
				)
				if (title) {
					title.focus()
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
				const rndIdx = Math.floor(Math.random() * (idx + 1))
				;[shuffled[rndIdx], shuffled[idx]] = [
					shuffled[idx],
					shuffled[rndIdx],
				]
			}
			return shuffled
		},

		async saveQuestionProperty(key, value) {
			try {
				// TODO: add loading status feedback ?
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{
							id: this.formId,
							questionId: this.id,
						},
					),
					{
						keyValuePairs: {
							[key]: value,
						},
					},
				)
				emit('forms:last-updated:set', this.formId)
			} catch (error) {
				logger.error('Error while saving question', { error })
				showError(t('forms', 'Error while saving question'))
			}
		},

		/**
		 * Handles multiple options for a question.
		 *
		 * @param {Array<string>} answers - The array of answers for the question.
		 */
		async handleMultipleOptions(answers) {
			this.isLoading = true
			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
						{
							id: this.formId,
							questionId: this.id,
						},
					),
					{
						optionTexts: answers, // Send the entire array of answers at once
					},
				)
				const newServerOptions = OcsResponse2Data(response) // Assuming this function can handle arrays
				const options = this.options.slice()
				newServerOptions.forEach((option) => {
					options.push({
						id: option.id, // Use the ID from the server
						questionId: this.id,
						text: option.text,
						local: false,
					})
				})
				this.updateOptions(options)
				this.$nextTick(() => {
					this.focusIndex(options.length - 1)
				})
			} catch (error) {
				logger.error('Error while saving question options', { error })
				showError(t('forms', 'Error while saving question options'))
			}
			this.isLoading = false
		},

		/**
		 * Add a new empty answer locally
		 * @param {InputEvent} event The input event that triggered adding a new entry
		 */
		addNewEntry({ target, isComposing }) {
			/*
			 * Check for !isComposing needed for languages using IME like Japanese or Chinese
			 * Check for !this.isComposing needed for IME inputs handled by CompositionEvents
			 * Check for target.value !== '' needed for Linux/Mac for characters like á or è
			 */
			if (!isComposing && !this.isIMEComposing && target.value !== '') {
				// Add local entry
				const options = [
					...this.options,
					{
						id: GenRandomId(),
						questionId: this.id,
						text: target.value,
						local: true,
					},
				]

				// Reset the "new answer" input if needed
				if (this.$refs.pseudoInput) {
					this.$refs.pseudoInput.value = ''
				}

				// Update questions
				this.updateOptions(options)

				this.$nextTick(() => {
					// Set focus to the created input element
					this.focusIndex(options.length - 1)

					// Trigger onInput on new AnswerInput for posting the new option to the API
					this.$refs.input[options.length - 1].debounceOnInput()
				})
			}
		},

		/**
		 * Handle compostion start event for IME inputs
		 */
		onCompositionStart() {
			this.isIMEComposing = true
		},

		/**
		 * Handle compostion end event for IME inputs
		 * @param {CompositionEvent} event The input event that triggered adding a new entry
		 */
		onCompositionEnd({ target, isComposing }) {
			this.isIMEComposing = false
			if (!isComposing) {
				this.addNewEntry({ target, isComposing })
			}
		},
	},
}
