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
		<ul class="question__content">
			<template v-for="(answer, index) in options">
				<li v-if="!edit" :key="answer.id" class="question__item">
					<!-- Answer radio/checkbox + label -->
					<!-- TODO: migrate to radio/checkbox component once available -->
					<input :id="`${id}-answer-${answer.id}`"
						ref="checkbox"
						:aria-checked="isChecked(answer.id)"
						:checked="isChecked(answer.id)"
						:class="{
							'radio question__radio': isUnique,
							'checkbox question__checkbox': !isUnique,
						}"
						:name="`${id}-answer`"
						:required="checkRequired(answer.id)"
						:type="isUnique ? 'radio' : 'checkbox'"
						@change="onChange($event, answer.id)"
						@keydown.enter.exact.prevent="onKeydownEnter">
					<label v-if="!edit"
						ref="label"
						:for="`${id}-answer-${answer.id}`"
						class="question__label">{{ answer.text }}</label>
				</li>

				<!-- Answer text input edit -->
				<AnswerInput v-else
					:key="index /* using index to keep the same vnode after new answer creation */"
					ref="input"
					:answer="answer"
					:index="index"
					:is-unique="isUnique"
					:is-dropdown="false"
					:max-option-length="maxStringLengths.optionText"
					@add="addNewEntry"
					@delete="deleteOption"
					@update:answer="updateAnswer" />
			</template>

			<li v-if="(edit && !isLastEmpty) || hasNoAnswer" class="question__item">
				<div class="question__item__pseudoInput" :class="{'question__item__pseudoInput--unique':isUnique}" />
				<input :aria-label="t('forms', 'Add a new answer')"
					:placeholder="t('forms', 'Add a new answer')"
					class="question__input"
					:maxlength="maxStringLengths.optionText"
					minlength="1"
					type="text"
					@click="addNewEntry"
					@focus="addNewEntry">
			</li>
		</ul>
	</Question>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import AnswerInput from './AnswerInput'
import QuestionMixin from '../../mixins/QuestionMixin'
import GenRandomId from '../../utils/GenRandomId'

// Implementations docs
// https://www.w3.org/TR/2016/WD-wai-aria-practices-1.1-20160317/examples/radio/radio.html
// https://www.w3.org/TR/2016/WD-wai-aria-practices-1.1-20160317/examples/checkbox/checkbox-2.html
export default {
	name: 'QuestionMultiple',

	components: {
		AnswerInput,
	},

	mixins: [QuestionMixin],

	computed: {
		contentValid() {
			return this.answerType.validate(this)
		},

		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim().length === 0
		},

		isUnique() {
			return this.answerType.unique === true
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
		onChange(event, answerId) {
			const isChecked = event.target.checked === true
			let values = this.values.slice()

			// Radio
			if (this.isUnique) {
				this.$emit('update:values', [answerId])
				return
			}

			// Checkbox
			if (isChecked) {
				values.push(answerId)
			} else {
				values = values.filter(id => id !== answerId)
			}

			// Emit values and remove duplicates
			this.$emit('update:values', [...new Set(values)])
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
		 * Is the provided answer required ?
		 * This is needed for checkboxes as html5
		 * doesn't allow to require at least ONE checked.
		 * So we require the one that are checked or all
		 * if none are checked yet.
		 *
		 * @param {number} id the answer id
		 * @return {boolean}
		 */
		checkRequired(id) {
			// false, if question not required
			if (!this.isRequired) {
				return false
			}

			// true for Radiobuttons
			if (this.isUnique) {
				return true
			}

			// For checkboxes, only required if no other is checked
			return this.areNoneChecked
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

	// Taking styles from server radio-input items
	&__pseudoInput {
		flex-shrink: 0;
		display: inline-block;
		height: 16px;
		width: 16px !important;
		vertical-align: middle;
		margin: 0 14px 0px 0px;
		border: 1px solid #878787;
		border-radius: 1px;
		// Adjust position manually to match pseudo-input and proper position to text
		position: relative;
		top: 10px;

		// Show round for Pseudo-Radio-Button
		&--unique {
			border-radius: 50%;
		}

		&:hover {
			border-color: var(--color-primary-element);
		}
	}

	.question__label {
		flex: 1 1 100%;
		// Overwrite guest page core styles
		text-align: left !important;
		// Some rounding issues lead to this strange number, so label and answerInput show up a the same position, working on different browsers.
		padding: 6.5px 0 0 30px;
		line-height: 22px;
		min-height: 34px;
		height: min-content;
		position: relative;

		&::before {
			box-sizing: border-box;
			// Adjust position manually for proper position to text
			position: absolute;
			top: 10px;
			width: 16px;
			height: 16px;
			margin-bottom: 0;
			margin-left: -30px !important;
			margin-right: 14px !important;
		}
	}
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

input.question__radio,
input.question__checkbox {
	z-index: -1;
	// make sure browser warnings are properly
	// displayed at the correct location
	left: 0px;
	width: 16px;
}

</style>
