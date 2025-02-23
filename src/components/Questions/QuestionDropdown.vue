<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:checked="extraSettings?.shuffleOptions"
				@update:checked="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionButton close-after-click @click="isOptionDialogShown = true">
				<template #icon>
					<IconContentPaste :size="20" />
				</template>
				{{ t('forms', 'Add multiple options') }}
			</NcActionButton>
		</template>
		<NcSelect
			v-if="readOnly"
			:value="selectedOption"
			:name="name || undefined"
			:placeholder="selectOptionPlaceholder"
			:multiple="isMultiple"
			:required="isRequired"
			:options="sortedOptions"
			:searchable="false"
			label="text"
			@input="onInput" />

		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>
			<ol v-else class="question__content">
				<!-- Answer text input edit -->
				<AnswerInput
					v-for="(answer, index) in options"
					:key="
						index /* using index to keep the same vnode after new answer creation */
					"
					ref="input"
					:answer="answer"
					:form-id="formId"
					:index="index"
					:is-unique="!isMultiple"
					:is-dropdown="true"
					:max-option-length="maxStringLengths.optionText"
					@delete="deleteOption"
					@update:answer="updateAnswer"
					@focus-next="focusNextInput"
					@tabbed-out="checkValidOption" />

				<li v-if="!isLastEmpty || hasNoAnswer" class="question__item">
					<input
						ref="pseudoInput"
						class="question__input"
						:aria-label="t('forms', 'Add a new answer')"
						:placeholder="t('forms', 'Add a new answer')"
						:maxlength="maxStringLengths.optionText"
						minlength="1"
						type="text"
						@input="addNewEntry"
						@compositionstart="onCompositionStart"
						@compositionend="onCompositionEnd" />
				</li>
			</ol>
		</template>

		<!-- Add multiple options modal -->
		<OptionInputDialog
			:open.sync="isOptionDialogShown"
			@multiple-answers="handleMultipleOptions" />
	</Question>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'

import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'

import AnswerInput from './AnswerInput.vue'
import OptionInputDialog from '../OptionInputDialog.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import logger from '../../utils/Logger.js'

export default {
	name: 'QuestionDropdown',

	components: {
		AnswerInput,
		IconContentPaste,
		NcActionButton,
		NcActionCheckbox,
		NcLoadingIcon,
		NcSelect,
		OptionInputDialog,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			inputValue: '',
			isOptionDialogShown: false,
			isLoading: false,
		}
	},

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
			return value?.text?.trim?.().length === 0
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
			return !this.readOnly && this.options.length !== 0 && !this.isLastEmpty
		},

		selectedOption() {
			if (!this.values) {
				return null
			}

			const selected = this.values.map((id) =>
				this.options.find((option) => option.id === id),
			)

			return this.isMultiple ? selected : selected[0]
		},
	},

	methods: {
		onInput(option) {
			if (Array.isArray(option)) {
				this.$emit('update:values', [
					...new Set(option.map((opt) => opt.id)),
				])
				return
			}

			// Simple select
			this.$emit('update:values', option ? [option.id] : [])
		},

		/**
		 * Remove any empty options when leaving an option
		 */
		checkValidOption() {
			// When leaving edit mode, filter and delete empty options
			this.options.forEach((option) => {
				if (!option.text) {
					this.deleteOption(option.id)
				}
			})
		},

		/**
		 * Set focus on next AnswerInput
		 *
		 * @param {number} index Index of current option
		 */
		focusNextInput(index) {
			if (index < this.options.length - 1) {
				this.$refs.input[index + 1].focus()
			} else if (!this.isLastEmpty || this.hasNoAnswer) {
				this.$refs.pseudoInput.focus()
			}
		},

		/**
		 * Update the options
		 *
		 * @param {Array} options options to change
		 */
		updateOptions(options) {
			this.$emit('update:options', options)
			emit('forms:last-updated:set', this.formId)
		},

		/**
		 * Update an existing answer locally
		 *
		 * @param {string|number} id the answer id
		 * @param {object} answer the answer to update
		 */
		updateAnswer(id, answer) {
			const options = [...this.options]
			const answerIndex = options.findIndex((option) => option.id === id)
			options[answerIndex] = answer

			this.updateOptions(options)
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
			const optionIndex = options.findIndex((option) => option.id === id)

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
			const optionIndex = this.options.findIndex((opt) => opt.id === option.id)

			if (!option.local) {
				// let's not await, deleting in background
				axios
					.delete(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/questions/{questionId}/options/{optionId}',
							{
								id: this.formId,
								questionId: this.id,
								optionId: option.id,
							},
						),
					)
					.catch((error) => {
						logger.error('Error while deleting an option', {
							option,
							error,
						})
						showError(
							t('forms', 'There was an issue deleting this option'),
						)
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
	min-height: var(--default-clickable-area);

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -12px;
		margin-inline-end: 32px !important;
	}
}
</style>
