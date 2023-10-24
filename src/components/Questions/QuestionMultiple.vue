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
		:name="name"
		:description="description"
		:is-required="isRequired"
		:edit.sync="edit"
		:read-only="readOnly"
		:max-string-lengths="maxStringLengths"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox :checked="extraSettings?.shuffleOptions"
				@update:checked="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionCheckbox :checked="extraSettings?.allowOtherAnswer"
				@update:checked="onAllowOtherAnswerChange">
				{{ t('forms', 'Add "other"') }}
			</NcActionCheckbox>
		</template>
		<template v-if="!edit">
			<fieldset :name="name || undefined" :aria-labelledby="titleId">
				<NcCheckboxRadioSwitch v-for="(answer) in sortedOptions"
					:key="answer.id"
					:checked.sync="questionValues"
					:value="answer.id.toString()"
					:name="`${id}-answer`"
					:type="isUnique ? 'radio' : 'checkbox'"
					:required="checkRequired(answer.id)"
					@update:checked="onChange"
					@keydown.enter.exact.prevent="onKeydownEnter">
					{{ answer.text }}
				</NcCheckboxRadioSwitch>
				<div v-if="allowOtherAnswer" class="question__other-answer">
					<NcCheckboxRadioSwitch :checked.sync="questionValues"
						:value="valueOtherAnswer"
						:name="`${id}-answer`"
						:type="isUnique ? 'radio' : 'checkbox'"
						:required="checkRequired('other-answer')"
						class="question__label"
						@update:checked="onChange"
						@keydown.enter.exact.prevent="onKeydownEnter">
						{{ t('forms', 'Other:') }}
					</NcCheckboxRadioSwitch>
					<NcInputField :label="placeholderOtherAnswer"
						:required="hasRequiredOtherAnswerInput"
						:value.sync="inputOtherAnswer"
						class="question__input" />
				</div>
			</fieldset>
		</template>

		<template v-else>
			<ul class="question__content">
				<template v-for="(answer, index) in sortedOptions">
					<!-- Answer text input edit -->
					<AnswerInput v-if="edit"
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
				<li v-if="allowOtherAnswer" class="question__item">
					<div :is="pseudoIcon" class="question__item__pseudoInput" />
					<input :placeholder="t('forms', 'Other')"
						class="question__input"
						:maxlength="maxStringLengths.optionText"
						minlength="1"
						type="text"
						:readonly="edit">
				</li>
				<li v-if="(edit && !isLastEmpty) || hasNoAnswer" class="question__item">
					<div :is="pseudoIcon" class="question__item__pseudoInput" />
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
		</template>
	</Question>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'

import AnswerInput from './AnswerInput.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import GenRandomId from '../../utils/GenRandomId.js'
import logger from '../../utils/Logger.js'

export default {
	name: 'QuestionMultiple',

	components: {
		AnswerInput,
		IconCheckboxBlankOutline,
		IconRadioboxBlank,
		NcActionCheckbox,
		NcCheckboxRadioSwitch,
		NcInputField,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			questionValues: this.values,
			inputOtherAnswer: this.valueToInputOtherAnswer(),
			QUESTION_EXTRASETTINGS_OTHER_PREFIX: 'system-other-answer:',
		}
	},

	computed: {
		placeholderOtherAnswer() {
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

		pseudoIcon() {
			return this.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
		},

		titleId() {
			return `q${this.$attrs.index}_title`
		},

		allowOtherAnswer() {
			return this.extraSettings?.allowOtherAnswer
		},

		valueOtherAnswer() {
			return this.QUESTION_EXTRASETTINGS_OTHER_PREFIX + this.inputOtherAnswer
		},

		hasRequiredOtherAnswerInput() {
			const checkedOtherAnswer = this.questionValues.filter(item => item.startsWith(this.QUESTION_EXTRASETTINGS_OTHER_PREFIX))
			return checkedOtherAnswer[0] !== undefined
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

		inputOtherAnswer() {
			if (this.isUnique) {
				this.questionValues = this.valueOtherAnswer
				this.onChange()
				return
			}

			this.questionValues = this.questionValues.filter(item => !item.startsWith(this.QUESTION_EXTRASETTINGS_OTHER_PREFIX))

			if (this.inputOtherAnswer !== '') {
				this.questionValues.push(this.valueOtherAnswer)
			}

			this.onChange()
		},
	},

	methods: {
		onChange() {
			this.$emit('update:values', this.isUnique ? [this.questionValues] : this.questionValues)
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
			emit('forms:last-updated:set', this.$attrs.formId)
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
				axios.delete(generateOcsUrl('apps/forms/api/v2.1/option/{id}', { id: option.id }))
					.catch(error => {
						logger.error('Error while deleting an option', { error, option })
						showError(t('forms', 'There was an issue deleting this option'))
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

		/**
		 * Update status extra setting allowOtherAnswer and save on DB
		 *
		 * @param {boolean} allowOtherAnswer show/hide field for other answer
		 */
		 onAllowOtherAnswerChange(allowOtherAnswer) {
			return this.onExtraSettingsChange('allowOtherAnswer', allowOtherAnswer)
		},

		valueToInputOtherAnswer() {
			const otherAnswer = this.values.filter(item => item.startsWith(this.QUESTION_EXTRASETTINGS_OTHER_PREFIX))
			return otherAnswer[0] !== undefined ? otherAnswer[0].substring(this.QUESTION_EXTRASETTINGS_OTHER_PREFIX.length) : ''
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

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.question__input {
		width: 100%;
		position: relative;
		inset-inline-start: -30px;
		inset-block-start: 1px;
		margin-inline-end: 14px !important;
		padding-inline-start: 32px !important;
	}

	.question__label {
		flex: 1 1 100%;
		// Overwrite guest page core styles
		text-align: start !important;
		// Some rounding issues lead to this strange number, so label and answerInput show up a the same position, working on different browsers.
		padding-block: 6.5px 0;
		padding-inline: 30px 0;
		line-height: 22px;
		min-height: 34px;
		height: min-content;
		position: relative;

		&::before {
			box-sizing: border-box;
			// Adjust position manually for proper position to text
			position: absolute;
			inset-block-start: 10px;
			width: 16px;
			height: 16px;
			margin-inline: -30px 14px !important;
			margin-block-end: 0;
		}
	}
}

.question__other-answer {
	display: flex;
	gap: 4px 16px;
	flex-wrap: wrap;

	.question__label {
		flex-basis: content;
	}

	.question__input {
		flex: 1;
		min-width: 260px;
	}

	.input-field__input {
		min-height: 44px;
	}
}

.question__other-answer:deep() .input-field__input {
	min-height: 44px;
}

</style>
