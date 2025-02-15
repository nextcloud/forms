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
			<NcActionCheckbox
				:checked="allowOtherAnswer"
				@update:checked="onAllowOtherAnswerChange">
				{{ t('forms', 'Add "other"') }}
			</NcActionCheckbox>

			<!-- For multiple (checkbox) options allow to limit the answers -->
			<template v-if="!isUnique">
				<!-- Allow setting a minimum of options to be checked -->
				<NcActionCheckbox
					:checked="!!extraSettings?.optionsLimitMin"
					@update:checked="
						(checked) => onLimitOptionsMin(checked ? 1 : null)
					">
					{{ t('forms', 'Require a minimum of options to be checked') }}
				</NcActionCheckbox>
				<NcActionInput
					v-if="extraSettings?.optionsLimitMin"
					type="number"
					:label="t('forms', 'Minimum options to be checked')"
					:label-outside="false"
					:show-trailing-button="false"
					:value="extraSettings.optionsLimitMin"
					@update:value="onLimitOptionsMin" />

				<!-- Allow setting a maximum -->
				<NcActionCheckbox
					:checked="!!extraSettings?.optionsLimitMax"
					@update:checked="
						(checked) =>
							onLimitOptionsMax(
								checked ? sortedOptions.length || 1 : null,
							)
					">
					{{ t('forms', 'Require a maximum of options to be checked') }}
				</NcActionCheckbox>
				<NcActionInput
					v-if="extraSettings?.optionsLimitMax"
					type="number"
					:label="t('forms', 'Maximum options to be checked')"
					:label-outside="false"
					:show-trailing-button="false"
					:value="extraSettings.optionsLimitMax"
					@update:value="onLimitOptionsMax" />
			</template>
			<NcActionButton close-after-click @click="isOptionDialogShown = true">
				<template #icon>
					<IconContentPaste :size="20" />
				</template>
				{{ t('forms', 'Add multiple options') }}
			</NcActionButton>
		</template>
		<template v-if="readOnly">
			<fieldset :name="name || undefined" :aria-labelledby="titleId">
				<NcNoteCard v-if="hasError" :id="errorId" type="error">
					{{ errorMessage }}
				</NcNoteCard>
				<NcCheckboxRadioSwitch
					v-for="answer in sortedOptions"
					:key="answer.id"
					:aria-errormessage="hasError ? errorId : undefined"
					:aria-invalid="hasError ? 'true' : undefined"
					:checked="questionValues"
					:value="answer.id.toString()"
					:name="`${id}-answer`"
					:type="isUnique ? 'radio' : 'checkbox'"
					:required="checkRequired(answer.id)"
					@update:checked="onChange"
					@keydown.enter.exact.prevent="onKeydownEnter">
					{{ answer.text }}
				</NcCheckboxRadioSwitch>
				<div v-if="allowOtherAnswer" class="question__other-answer">
					<NcCheckboxRadioSwitch
						:checked="questionValues"
						:aria-errormessage="hasError ? errorId : undefined"
						:aria-invalid="hasError ? 'true' : undefined"
						:value="otherAnswer ?? QUESTION_EXTRASETTINGS_OTHER_PREFIX"
						:name="`${id}-answer`"
						:type="isUnique ? 'radio' : 'checkbox'"
						:required="checkRequired('other-answer')"
						class="question__label"
						@update:checked="onChangeOther"
						@keydown.enter.exact.prevent="onKeydownEnter">
						{{ t('forms', 'Other:') }}
					</NcCheckboxRadioSwitch>
					<NcInputField
						class="question__input"
						:label="placeholderOtherAnswer"
						:required="otherAnswer !== undefined"
						:value.sync="otherAnswerText" />
				</div>
			</fieldset>
		</template>

		<template v-else>
			<template v-if="isLoading">
				<div>
					<NcLoadingIcon :size="64" />
				</div>
			</template>
			<template v-else>
				<ul class="question__content">
					<!-- Answer text input edit -->
					<AnswerInput
						v-for="(answer, index) in sortedOptions"
						:key="
							index /* using index to keep the same vnode after new answer creation */
						"
						ref="input"
						:answer="answer"
						:form-id="formId"
						:index="index"
						:is-unique="isUnique"
						:is-dropdown="false"
						:max-option-length="maxStringLengths.optionText"
						@delete="deleteOption"
						@update:answer="updateAnswer"
						@focus-next="focusNextInput"
						@tabbed-out="checkValidOption" />
					<li v-if="allowOtherAnswer" class="question__item">
						<div :is="pseudoIcon" class="question__item__pseudoInput" />
						<input
							:placeholder="t('forms', 'Other')"
							class="question__input"
							:maxlength="maxStringLengths.optionText"
							minlength="1"
							type="text"
							:readonly="!readOnly" />
					</li>
					<li v-if="!isLastEmpty || hasNoAnswer" class="question__item">
						<div :is="pseudoIcon" class="question__item__pseudoInput" />
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
				</ul>
			</template>
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
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'

import AnswerInput from './AnswerInput.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import logger from '../../utils/Logger.js'
import OptionInputDialog from '../OptionInputDialog.vue'

const QUESTION_EXTRASETTINGS_OTHER_PREFIX = 'system-other-answer:'

export default {
	name: 'QuestionMultiple',

	components: {
		AnswerInput,
		IconCheckboxBlankOutline,
		IconContentPaste,
		IconRadioboxBlank,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcInputField,
		NcLoadingIcon,
		NcNoteCard,
		OptionInputDialog,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			/**
			 * The shown error message
			 */
			errorMessage: null,
			/**
			 * This is used to cache the "other" answer, meaning if the user:
			 * checks "other" types text, unchecks "other" and then re-check "other" the typed text is preserved
			 */
			cachedOtherAnswerText: '',
			QUESTION_EXTRASETTINGS_OTHER_PREFIX,

			isOptionDialogShown: false,
			isLoading: false,
		}
	},

	computed: {
		contentValid() {
			return this.answerType.validate(this)
		},

		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim?.().length === 0
		},

		isUnique() {
			return this.answerType.unique === true
		},

		hasNoAnswer() {
			return this.options.length === 0
		},

		hasError() {
			return !!this.errorMessage
		},

		areNoneChecked() {
			return this.values.length === 0
		},

		shiftDragHandle() {
			return !this.readonly && this.options.length !== 0 && !this.isLastEmpty
		},

		pseudoIcon() {
			return this.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
		},

		placeholderOtherAnswer() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		questionValues() {
			return this.isUnique ? this.values?.[0] : this.values
		},

		titleId() {
			return `q${this.index}_title`
		},

		errorId() {
			return `q${this.index}_error`
		},

		allowOtherAnswer() {
			return this.extraSettings?.allowOtherAnswer ?? false
		},

		/**
		 * The full "other" answer including prefix, undefined if no "other answer"
		 */
		otherAnswer() {
			return this.values.find((v) =>
				v.startsWith(QUESTION_EXTRASETTINGS_OTHER_PREFIX),
			)
		},

		/**
		 * The text value of the "other" answer
		 */
		otherAnswerText: {
			get() {
				return this.cachedOtherAnswerText
			},
			/**
			 * Called when the value of the "other" anwer is changed input
			 * @param {string} value the new text of the "other" answer
			 */
			set(value) {
				this.cachedOtherAnswerText = value
				// Prefix the value
				const prefixedValue = `${QUESTION_EXTRASETTINGS_OTHER_PREFIX}${value}`
				// emit the values and add the "other" answer
				this.$emit(
					'update:values',
					this.isUnique
						? [prefixedValue]
						: [
								...this.values.filter(
									(v) =>
										!v.startsWith(
											QUESTION_EXTRASETTINGS_OTHER_PREFIX,
										),
								),
								prefixedValue,
							],
				)
			},
		},
	},

	watch: {
		// Ensure that the "other" answer is reset after toggling the checkbox
		otherAnswer() {
			this.resetOtherAnswerText()
		},
	},

	mounted() {
		// Ensure the initial "other" answer is set
		this.resetOtherAnswerText()
	},

	methods: {
		async validate() {
			if (!this.isUnique) {
				// Validate limits
				const max = this.extraSettings.optionsLimitMax ?? 0
				const min = this.extraSettings.optionsLimitMin ?? 0
				if (max && this.values.length > max) {
					this.errorMessage = n(
						'forms',
						'You must choose at most one option',
						'You must choose a maximum of %n options',
						max,
					)
					return false
				}
				if (min && this.values.length < min) {
					this.errorMessage = n(
						'forms',
						'You must choose at least one option',
						'You must choose at least %n options',
						min,
					)
					return false
				}
			}

			this.errorMessage = null
			return true
		},

		/**
		 * Resets the local "other" answer text to the one from the options if available
		 */
		resetOtherAnswerText() {
			if (this.otherAnswer) {
				// make sure to use cached value if empty value is passed
				this.cachedOtherAnswerText =
					this.otherAnswer.slice(
						QUESTION_EXTRASETTINGS_OTHER_PREFIX.length,
					) || this.cachedOtherAnswerText
			}
		},

		onChange(value) {
			this.$emit('update:values', this.isUnique ? [value].flat() : value)
		},

		/**
		 * Handle toggling the "other"-answer checkbox / radio switch
		 * @param {string|string[]} value The new value of the answer(s)
		 */
		onChangeOther(value) {
			value = [value].flat()
			const pureValue = value.filter(
				(v) => !v.startsWith(QUESTION_EXTRASETTINGS_OTHER_PREFIX),
			)

			if (value.length > pureValue.length) {
				// make sure to add the cached text on re-enable
				this.onChange([
					...pureValue,
					`${QUESTION_EXTRASETTINGS_OTHER_PREFIX}${this.cachedOtherAnswerText}`,
				])
			} else {
				this.onChange(value)
			}
		},

		/**
		 * Updating the maximum number
		 * @param {number|null} max Maximum options
		 */
		onLimitOptionsMax(max) {
			max = max && Number.parseInt(max.toString(), 10)
			if (this.isUnique || max === null) {
				// For unique (radio) options we cannot set limits, also if null is passed then we need to remove the limit
				this.onExtraSettingsChange({ optionsLimitMax: undefined })
			} else if (max) {
				if ((this.extraSettings.optionsLimitMin ?? 0) > max) {
					showError(
						t(
							'forms',
							'Upper options limit must be greater than the lower limit',
						),
					)
					return
				}
				// If a valid number was passed, update the backend
				this.onExtraSettingsChange({ optionsLimitMax: max })
			}
		},

		/**
		 * Update the minimum of checked options
		 * @param {number|null} min Minimum of checked options
		 */
		onLimitOptionsMin(min) {
			min = min && Number.parseInt(min.toString(), 10)
			if (this.isUnique || min === null) {
				this.onExtraSettingsChange({ optionsLimitMin: undefined })
			} else if (min) {
				if (
					this.extraSettings.optionsLimitMax &&
					min > this.extraSettings.optionsLimitMax
				) {
					showError(
						t(
							'forms',
							'Lower options limit must be smaller than the upper limit',
						),
					)
					return
				}
				this.onExtraSettingsChange({ optionsLimitMin: min })
			}
		},

		/**
		 * Is the provided answer required ?
		 * This is needed for checkboxes as html5
		 * doesn't allow to require at least ONE checked.
		 * So we require the one that are checked or all
		 * if none are checked yet.
		 *
		 * @return {boolean}
		 */
		checkRequired() {
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
		 * This will handle updating the form (emitting the changes) and update last changed property
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
							error,
							option,
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

		/**
		 * Update status extra setting allowOtherAnswer and save on DB
		 *
		 * @param {boolean} allowOtherAnswer show/hide field for other answer
		 */
		onAllowOtherAnswerChange(allowOtherAnswer) {
			return this.onExtraSettingsChange({ allowOtherAnswer })
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

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -34px;
		inset-block-start: 1px;
		margin-inline-end: 10px !important;
		padding-inline-start: 36px !important;
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
		min-height: var(--default-clickable-area);
	}
}

.question__other-answer:deep() .input-field__input {
	min-height: var(--default-clickable-area);
}
</style>
