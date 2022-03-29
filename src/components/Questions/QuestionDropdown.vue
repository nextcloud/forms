<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Question v-bind.sync="$attrs"
		:text="text"
		:is-required="isRequired"
		:edit.sync="edit"
		:read-only="readOnly"
		:max-question-length="maxStringLengths.questionText"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		@update:text="onTitleChange"
		@update:isRequired="onRequiredChange"
		@delete="onDelete">
		<select v-if="!edit"
			:id="text"
			:name="text"
			:multiple="isMultiple"
			:required="isRequired"
			class="question__content"
			@change="onChange">
			<option value="">
				{{ selectOptionPlaceholder }}
			</option>
			<option v-for="answer in options"
				:key="answer.id"
				:value="answer.id"
				:selected="isChecked(answer.id)">
				{{ answer.text }}
			</option>
		</select>

		<ol v-if="edit" class="question__content">
			<!-- Answer text input edit -->
			<AnswerInput v-for="(answer, index) in options"
				:key="index /* using index to keep the same vnode after new answer creation */"
				ref="input"
				:answer="answer"
				:index="index"
				:is-unique="!isMultiple"
				:is-dropdown="true"
				:max-option-length="maxStringLengths.optionText"
				@add="addNewEntry"
				@delete="deleteOption"
				@update:answer="updateAnswer" />

			<li v-if="!isLastEmpty || hasNoAnswer" class="question__item">
				<input :aria-label="t('forms', 'Add a new answer')"
					:placeholder="t('forms', 'Add a new answer')"
					class="question__input"
					:maxlength="maxStringLengths.optionText"
					minlength="1"
					type="text"
					@click="addNewEntry"
					@focus="addNewEntry">
			</li>
		</ol>
	</Question>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import AnswerInput from './AnswerInput'
import QuestionMixin from '../../mixins/QuestionMixin'
import GenRandomId from '../../utils/GenRandomId'

export default {
	name: 'QuestionDropdown',

	components: {
		AnswerInput,
	},

	mixins: [QuestionMixin],

	computed: {
		selectOptionPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		contentValid() {
			return this.answerType.validate(this)
		},

		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim().length === 0
		},

		isMultiple() {
			// This can be extended if we want to include support for <select multiple>
			return false
		},

		hasNoAnswer() {
			return this.options.length === 0
		},

		areNoneChecked() {
			return this.values.length === 0
		},

		shiftDragHandle() {
			return this.edit && this.options.length !== 0 && !this.isLastEmpty
		},
	},

	watch: {
		edit(edit) {
			// When leaving edit mode, filter and delete empty options
			if (!edit) {
				const options = this.options.filter(option => {
					if (!option.text) {
						this.deleteOptionFromDatabase(option)
						return false
					}
					return true
				})

				// update parent
				this.updateOptions(options)
			}
		},
	},

	methods: {
		onChange(event) {
			// Get all selected options
			const answerIds = [...event.target.options]
				.filter(option => option.selected)
				.map(option => parseInt(option.value, 10))

			// Simple select
			if (!this.isMultiple) {
				this.$emit('update:values', [answerIds[0]])
				return
			}

			// Emit values and remove duplicates
			this.$emit('update:values', [...new Set(answerIds)])
		},

		/**
		 * Is the provided answer checked ?
		 *
		 * @param {number} id the answer id
		 * @return {boolean}
		 */
		isChecked(id) {
			return this.values.indexOf(id) > -1
		},

		/**
		 * Update the options
		 *
		 * @param {Array} options options to change
		 */
		updateOptions(options) {
			this.$emit('update:options', options)
		},

		/**
		 * Update an existing answer locally
		 *
		 * @param {string|number} id the answer id
		 * @param {object} answer the answer to update
		 */
		updateAnswer(id, answer) {
			const options = this.options.slice()
			const answerIndex = options.findIndex(option => option.id === id)
			options[answerIndex] = answer

			this.updateOptions(options)
		},

		/**
		 * Add a new empty answer locally
		 */
		addNewEntry() {
			// If entering from non-edit-mode (possible by click), activate edit-mode
			this.edit = true

			// Add local entry
			const options = this.options.slice()
			options.push({
				id: GenRandomId(),
				questionId: this.id,
				text: '',
				local: true,
			})

			// Update question
			this.updateOptions(options)

			this.$nextTick(() => {
				this.focusIndex(options.length - 1)
			})
		},

		/**
		 * Restore an option locally
		 *
		 * @param {object} option the option
		 * @param {number} index the options index in this.options
		 */
		restoreOption(option, index) {
			const options = this.options.slice()
			options.splice(index, 0, option)

			this.updateOptions(options)
			this.focusIndex(index)
		},

		/**
		 * Delete an option
		 *
		 * @param {number} id the options id
		 */
		deleteOption(id) {
			const options = this.options.slice()
			const optionIndex = options.findIndex(option => option.id === id)

			if (options.length === 1) {
				// Clear Text, but don't remove. Will be removed, when leaving edit-mode
				options[0].text = ''
			} else {
				// Remove entry
				const option = Object.assign({}, this.options[optionIndex])

				// delete locally
				options.splice(optionIndex, 1)

				// delete from Db
				this.deleteOptionFromDatabase(option)
			}

			// Update question
			this.updateOptions(options)

			this.$nextTick(() => {
				this.focusIndex(optionIndex - 1)
			})
		},

		/**
		 * Delete the option from Db in background.
		 * Restore option if delete not possible
		 *
		 * @param {object} option The option to delete
		 */
		deleteOptionFromDatabase(option) {
			const optionIndex = this.options.findIndex(opt => opt.id === option.id)

			if (!option.local) {
				// let's not await, deleting in background
				axios.delete(generateOcsUrl('apps/forms/api/v2/option/{id}', { id: option.id }))
					.catch(error => {
						showError(t('forms', 'There was an issue deleting this option'))
						console.error(error)
						// restore option
						this.restoreOption(option, optionIndex)
					})
			}
		},

		/**
		 * Focus the input matching the index
		 *
		 * @param {number} index the value index
		 */
		focusIndex(index) {
			const inputs = this.$refs.input
			if (inputs && inputs[index]) {
				const input = inputs[index]
				input.focus()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
}

.question__item {
	position: relative;
	display: inline-flex;
	min-height: 44px;
}

// Using type to have a higher order than the input styling of server
.question__input[type=text] {
	width: 100%;
	// Height 34px + 1px Border
	min-height: 35px;
	margin: 0;
	padding: 0 0;
	border: 0;
	border-bottom: 1px dotted var(--color-border-dark);
	border-radius: 0;
	font-size: 14px;
	position: relative;
}

// Fix display of select dropdown and adjust to Forms text
select.question__content {
	height: 44px;
	padding: 12px 0 12px 12px;
	font-size: 14px;
}
</style>
