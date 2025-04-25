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
						:model-value="cachedOtherAnswerText"
						@update:model-value="onOtherAnswerTextChange" />
				</div>
			</fieldset>
		</template>

		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>
			<Draggable
				v-else
				v-model="sortedOptions"
				class="question__content"
				animation="200"
				direction="vertical"
				handle=".option__drag-handle"
				invert-swap
				tag="ul"
				@change="saveOptionsOrder"
				@start="isDragging = true"
				@end="isDragging = false">
				<TransitionGroup
					:name="
						isDragging
							? 'no-external-transition-on-drag'
							: 'options-list-transition'
					">
					<!-- Answer text input edit -->
					<AnswerInput
						v-for="(answer, index) in sortedOptions"
						:key="answer.local ? 'option-local' : answer.id"
						ref="input"
						:answer="answer"
						:form-id="formId"
						:index="index"
						:is-unique="isUnique"
						:max-index="options.length - 1"
						:max-option-length="maxStringLengths.optionText"
						@create-answer="onCreateAnswer"
						@update:answer="updateAnswer"
						@delete="deleteOption"
						@focus-next="focusNextInput"
						@move-up="onOptionMoveUp(index)"
						@move-down="onOptionMoveDown(index)"
						@tabbed-out="checkValidOption" />
				</TransitionGroup>
			</Draggable>
			<li
				v-if="allowOtherAnswer"
				key="option-add-other"
				class="question__item">
				<div :is="pseudoIcon" class="question__item__pseudoInput" />
				<input
					:placeholder="t('forms', 'Other')"
					class="question__input"
					:disabled="!readOnly"
					:maxlength="maxStringLengths.optionText"
					minlength="1"
					type="text"
					:readonly="!readOnly" />
			</li>
		</template>

		<!-- Add multiple options modal -->
		<OptionInputDialog
			:open.sync="isOptionDialogShown"
			@multiple-answers="handleMultipleOptions" />
	</Question>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import Draggable from 'vuedraggable'
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
import OptionInputDialog from '../OptionInputDialog.vue'
import QuestionMultipleMixin from '../../mixins/QuestionMultipleMixin.ts'

const QUESTION_EXTRASETTINGS_OTHER_PREFIX = 'system-other-answer:'

export default {
	name: 'QuestionMultiple',

	components: {
		AnswerInput,
		Draggable,
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

	mixins: [QuestionMixin, QuestionMultipleMixin],

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

			isDragging: false,
			isOptionDialogShown: false,
			isLoading: false,
		}
	},

	computed: {
		isUnique() {
			return this.answerType.unique === true
		},

		hasError() {
			return !!this.errorMessage
		},

		shiftDragHandle() {
			return !this.readOnly && this.options.length !== 0 && !this.isLastEmpty
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
					this.extraSettings.optionsLimitMax
					&& min > this.extraSettings.optionsLimitMax
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
		 * Update status extra setting allowOtherAnswer and save on DB
		 *
		 * @param {boolean} allowOtherAnswer show/hide field for other answer
		 */
		onAllowOtherAnswerChange(allowOtherAnswer) {
			return this.onExtraSettingsChange({ allowOtherAnswer })
		},

		/**
		 * Handles the change event for the "Other" answer text input.
		 *
		 * @param {string} value - The new value entered for the "Other" answer.
		 *
		 * This method performs the following actions:
		 * 1. Updates the cached value of the "Other" answer text (`cachedOtherAnswerText`).
		 * 2. Prefixes the input value with a predefined constant (`QUESTION_EXTRASETTINGS_OTHER_PREFIX`).
		 * 3. Emits an `update:values` event with the updated list of values:
		 *    - If `isUnique` is true, the emitted values will only include the prefixed "Other" answer.
		 *    - If `isUnique` is false, the emitted values will include all existing values
		 *      (excluding any that start with the "Other" prefix) and the new prefixed "Other" answer.
		 */
		onOtherAnswerTextChange(value) {
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
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
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

.options-list-transition-move,
.options-list-transition-enter-active,
.options-list-transition-leave-active {
	transition: all var(--animation-slow) ease;
}

.options-list-transition-enter-from,
.options-list-transition-leave-to {
	opacity: 0;
	transform: translateX(44px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.options-list-transition-leave-active {
	position: absolute;
}
</style>
