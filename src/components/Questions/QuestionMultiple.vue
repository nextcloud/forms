<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		ref="rootElement"
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:contentValid="contentValid"
		:shiftDragHandle="shiftDragHandle"
		:errorMessage="errorMessage"
		:infoMessage="infoMessage"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:modelValue="extraSettings?.shuffleOptions"
				@update:modelValue="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionCheckbox
				:modelValue="allowOtherAnswer"
				@update:modelValue="onAllowOtherAnswerChange">
				{{ t('forms', 'Add "other"') }}
			</NcActionCheckbox>

			<!-- For multiple (checkbox) options allow to limit the answers -->
			<template v-if="!isUnique">
				<!-- Allow setting a minimum of options to be checked -->
				<NcActionCheckbox
					:modelValue="!!extraSettings.optionsLimitMin"
					@update:modelValue="
						(checked) => onLimitOptionsMin(checked ? 1 : null)
					">
					{{ t('forms', 'Require a minimum of options to be checked') }}
				</NcActionCheckbox>
				<NcActionInput
					v-if="extraSettings.optionsLimitMin"
					type="number"
					:label="t('forms', 'Minimum options to be checked')"
					:labelOutside="false"
					:showTrailingButton="false"
					:modelValue="extraSettings.optionsLimitMin"
					@update:modelValue="onLimitOptionsMin" />

				<!-- Allow setting a maximum -->
				<NcActionCheckbox
					:modelValue="!!extraSettings.optionsLimitMax"
					@update:modelValue="
						(checked) =>
							onLimitOptionsMax(checked ? choices.length || 1 : null)
					">
					{{ t('forms', 'Require a maximum of options to be checked') }}
				</NcActionCheckbox>
				<NcActionInput
					v-if="extraSettings.optionsLimitMax"
					type="number"
					:label="t('forms', 'Maximum options to be checked')"
					:labelOutside="false"
					:showTrailingButton="false"
					:modelValue="extraSettings.optionsLimitMax"
					@update:modelValue="onLimitOptionsMax" />
			</template>
			<NcActionButton closeAfterClick @click="isOptionDialogShown = true">
				<template #icon>
					<NcIconSvgWrapper :svg="IconContentPaste" />
				</template>
				{{ t('forms', 'Add multiple options') }}
			</NcActionButton>
		</template>
		<template v-if="readOnly">
			<fieldset
				:name="name || undefined"
				:aria-labelledby="titleId"
				:aria-describedby="description ? descriptionId : undefined">
				<NcCheckboxRadioSwitch
					v-for="answer in choices"
					:key="answer.id"
					:aria-describedby="hasInfo ? infoId : undefined"
					:aria-errormessage="hasError ? errorId : undefined"
					:aria-invalid="hasError ? 'true' : undefined"
					:modelValue="questionValues"
					:value="answer.id.toString()"
					:name="`${id}-answer`"
					:type="isUnique ? 'radio' : 'checkbox'"
					:required="checkRequired"
					@invalid.prevent="validate"
					@update:modelValue="onChange"
					@keydown.enter.exact.prevent="onKeydownEnter">
					{{ answer.text }}
				</NcCheckboxRadioSwitch>
				<div v-if="allowOtherAnswer" class="question__other-answer">
					<NcCheckboxRadioSwitch
						:modelValue="questionValues"
						:aria-errormessage="hasError ? errorId : undefined"
						:aria-invalid="hasError ? 'true' : undefined"
						:value="otherAnswer ?? QUESTION_EXTRASETTINGS_OTHER_PREFIX"
						:name="`${id}-answer`"
						:type="isUnique ? 'radio' : 'checkbox'"
						:required="checkRequired"
						class="question__label"
						@invalid.prevent="validate"
						@update:modelValue="onChangeOther"
						@keydown.enter.exact.prevent="onKeydownEnter">
						{{ t('forms', 'Other:') }}
					</NcCheckboxRadioSwitch>
					<NcInputField
						class="question__input"
						:label="placeholderOtherAnswer"
						:required="otherAnswer !== undefined"
						:modelValue="cachedOtherAnswerText"
						@update:modelValue="onOtherAnswerTextChange" />
				</div>
			</fieldset>
		</template>

		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>
			<Draggable
				v-else
				v-model="choices"
				class="question__content"
				:animation="300"
				direction="vertical"
				handle=".option__drag-handle"
				invertSwap
				target=".sort-target"
				@update="dirtyOptionsType = 'choice'"
				@start="onDragStart"
				@end="onDragEnd">
				<TransitionGroup
					tag="ul"
					:name="isDragging ? undefined : 'options-list-transition'"
					class="sort-target">
					<AnswerInput
						v-for="(answer, index) in choices"
						:key="answer.local ? 'option-local' : answer.id"
						ref="input"
						:answer="answer"
						:formId="formId"
						:index="index"
						:isUnique="isUnique"
						:maxIndex="options.length - 1"
						:maxOptionLength="maxStringLengths.optionText"
						optionType="choice"
						@createAnswer="onCreateAnswer"
						@update:answer="updateAnswer"
						@delete="deleteOption"
						@focusNext="focusNextInput"
						@moveUp="onOptionMoveUp(index, OptionType.Choice)"
						@moveDown="onOptionMoveDown(index, OptionType.Choice)"
						@tabbedOut="checkValidOption" />
				</TransitionGroup>
			</Draggable>
			<li
				v-if="allowOtherAnswer"
				key="option-add-other"
				class="question__item">
				<NcIconSvgWrapper
					:svg="pseudoIcon"
					inline
					class="question__item__pseudoInput" />
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
			v-model:open="isOptionDialogShown"
			@multipleAnswers="handleMultipleOptions" />
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import type { FormsOption } from '../../models/Entities.d.ts'

import IconCheckboxBlankOutline from '@material-symbols/svg-400/outlined/check_box_outline_blank.svg?raw'
import IconContentPaste from '@material-symbols/svg-400/outlined/content_paste.svg?raw'
import IconRadioboxBlank from '@material-symbols/svg-400/outlined/radio_button_unchecked.svg?raw'
import { showError } from '@nextcloud/dialogs'
import { n, t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, onMounted, ref, watch } from 'vue'
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import OptionInputDialog from '../OptionInputDialog.vue'
import AnswerInput from './AnswerInput.vue'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'
import {
	QUESTION_MULTIPLE_EMITS,
	useQuestionMultiple,
} from '../../composables/useQuestionMultiple.ts'
import {
	OptionType,
	QUESTION_EXTRASETTINGS_OTHER_PREFIX,
} from '../../models/Constants.ts'

type QuestionMultipleExtraSettings = {
	allowOtherAnswer?: boolean
	optionsLimitMax?: number
	optionsLimitMin?: number
	shuffleOptions?: boolean
}

export default defineComponent({
	name: 'QuestionMultiple',

	components: {
		AnswerInput,
		Draggable,
		NcIconSvgWrapper,
		NcActionButton,
		NcActionCheckbox,
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcInputField,
		NcLoadingIcon,
		OptionInputDialog,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [
		...QUESTION_EMITS,
		...QUESTION_MULTIPLE_EMITS,
		'update:values',
		'update:isRequired',
	],

	setup(props, { emit }) {
		const rootElement = ref<{ $el?: HTMLElement } | null>(null)
		const input = ref<
			Array<{
				focus?: () => void
				$props?: { optionType?: string; index?: number }
			} | null>
		>([])
		const isLoading = ref(false)
		const questionMultiple = useQuestionMultiple(props, {
			emit,
			input,
			isLoading,
		})
		/**
		 * This is used to cache the "other" answer, meaning if the user:
		 * checks "other" types text, unchecks "other" and then re-check "other" the typed text is preserved
		 */
		const cachedOtherAnswerText = ref('')
		const isDragging = ref(false)
		const isOptionDialogShown = ref(false)

		const isUnique = computed(() => props.answerType.unique === true)
		const values = computed(() => props.values as string[])
		const extraSettings = computed<QuestionMultipleExtraSettings>(() => {
			return (
				(props.extraSettings as QuestionMultipleExtraSettings | undefined)
				?? {}
			)
		})

		const shiftDragHandle = computed(() => {
			return (
				!props.readOnly
				&& props.options.length !== 0
				&& !questionMultiple.isLastEmpty.value
			)
		})

		const pseudoIcon = computed(() => {
			return isUnique.value ? IconRadioboxBlank : IconCheckboxBlankOutline
		})

		const placeholderOtherAnswer = computed(() => {
			if (props.readOnly) {
				return props.answerType.submitPlaceholder
			}
			return props.answerType.createPlaceholder
		})

		const questionValues = computed<string | string[] | undefined>(() => {
			return isUnique.value ? values.value?.[0] : values.value
		})

		const allowOtherAnswer = computed(() => {
			return extraSettings.value.allowOtherAnswer ?? false
		})

		/**
		 * The full "other" answer including prefix, undefined if no "other answer"
		 */
		const otherAnswer = computed<string | undefined>(() => {
			return values.value.find((v) =>
				v.startsWith(QUESTION_EXTRASETTINGS_OTHER_PREFIX),
			)
		})

		const choices = computed<FormsOption[]>({
			get() {
				return questionMultiple.sortOptionsOfType(
					props.options,
					OptionType.Choice,
				)
			},

			set(value: FormsOption[]) {
				questionMultiple.updateOptionsOrder(value, OptionType.Choice)
			},
		})

		const availableOptions = computed(() => {
			return (
				choices.value.filter(({ text }) => text.trim() !== '').length
				+ (allowOtherAnswer.value ? 1 : 0)
			)
		})

		const infoMessage = computed<string | null>(() => {
			const min = extraSettings.value.optionsLimitMin ?? 0
			const max = extraSettings.value.optionsLimitMax ?? 0

			if (!min && !max) {
				return null
			}

			if (min && max) {
				if (min === max) {
					return n(
						'forms',
						'Choose exactly one option',
						'Choose exactly %n options',
						min,
					)
				}

				return t('forms', 'Choose between {min} and {max} options', {
					min,
					max,
				})
			}

			if (min) {
				return n(
					'forms',
					'Choose at least one option',
					'Choose at least %n options',
					min,
				)
			}

			return n(
				'forms',
				'Choose at most one option',
				'Choose at most %n options',
				max,
			)
		})
		const question = useQuestion(props, { emit, infoMessage, rootElement })

		/**
		 * Is the provided answer required ?
		 * This is needed for checkboxes as html5
		 * doesn't allow to require at least ONE checked.
		 * So we require the one that are checked or all
		 * if none are checked yet.
		 *
		 * @return
		 */
		const checkRequired = computed(() => {
			// false, if question not required
			if (!props.isRequired) {
				return false
			}

			// true for Radiobuttons
			if (isUnique.value) {
				return true
			}

			// For checkboxes, only required if no other is checked
			return questionMultiple.areNoneChecked.value
		})

		/**
		 * Resets the local "other" answer text to the one from the options if available
		 */
		const resetOtherAnswerText = (): void => {
			if (otherAnswer.value) {
				// make sure to use cached value if empty value is passed
				cachedOtherAnswerText.value =
					otherAnswer.value.slice(
						QUESTION_EXTRASETTINGS_OTHER_PREFIX.length,
					) || cachedOtherAnswerText.value
			}
		}

		const validate = async (): Promise<boolean> => {
			if (props.isRequired && questionMultiple.areNoneChecked.value) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			if (!isUnique.value) {
				// Validate limits
				const max = extraSettings.value.optionsLimitMax ?? 0
				const min = extraSettings.value.optionsLimitMin ?? 0
				if (max && values.value.length > max) {
					question.errorMessage.value = n(
						'forms',
						'You must choose at most one option',
						'You must choose at most %n options',
						max,
					)
					return false
				}
				if (min && values.value.length < min) {
					question.errorMessage.value = n(
						'forms',
						'You must choose at least one option',
						'You must choose at least %n options',
						min,
					)
					return false
				}
			}

			question.errorMessage.value = null
			return true
		}

		const onDragStart = (): void => {
			isDragging.value = true
		}

		const onDragEnd = (): void => {
			nextTick(() => {
				isDragging.value = false
			})
		}

		const onChange = (value: string | string[]): void => {
			const normalizedValue = Array.isArray(value) ? value : [value]
			emit(
				'update:values',
				isUnique.value ? normalizedValue.slice(0, 1) : normalizedValue,
			)
		}

		/**
		 * Handle toggling the "other"-answer checkbox / radio switch
		 *
		 * @param value The new value of the answer(s)
		 */
		const onChangeOther = (value: string | string[]): void => {
			const normalizedValue = Array.isArray(value) ? value : [value]
			const pureValue = normalizedValue.filter(
				(v) => !v.startsWith(QUESTION_EXTRASETTINGS_OTHER_PREFIX),
			)

			if (normalizedValue.length > pureValue.length) {
				// make sure to add the cached text on re-enable
				onChange([
					...pureValue,
					`${QUESTION_EXTRASETTINGS_OTHER_PREFIX}${cachedOtherAnswerText.value}`,
				])
			} else {
				onChange(normalizedValue)
			}
		}

		/**
		 * Updating the maximum number
		 *
		 * @param max Maximum options
		 */
		const onLimitOptionsMax = (max: number | string | null): void => {
			const parsedMax =
				max === null ? null : Number.parseInt(max.toString(), 10)
			if (isUnique.value || max === null) {
				// For unique (radio) options we cannot set limits, also if null is passed then we need to remove the limit
				question.onExtraSettingsChange({ optionsLimitMax: undefined })
			} else if (parsedMax) {
				if (parsedMax > availableOptions.value) {
					showError(
						t(
							'forms',
							'Upper options limit must not exceed the number of available options',
						),
					)
					question.onExtraSettingsChange({
						optionsLimitMax: availableOptions.value || undefined,
					})
					return
				}

				if ((extraSettings.value.optionsLimitMin ?? 0) > parsedMax) {
					showError(
						t(
							'forms',
							'Upper options limit must be greater than the lower limit',
						),
					)
					return
				}
				// If a valid number was passed, update the backend
				question.onExtraSettingsChange({ optionsLimitMax: parsedMax })
			}
		}

		/**
		 * Update the minimum of checked options
		 *
		 * @param min Minimum of checked options
		 */
		const onLimitOptionsMin = (min: number | string | null): void => {
			const parsedMin =
				min === null ? null : Number.parseInt(min.toString(), 10)
			if (isUnique.value || min === null) {
				question.onExtraSettingsChange({ optionsLimitMin: undefined })
			} else if (parsedMin) {
				if (parsedMin > availableOptions.value - 1) {
					showError(
						t(
							'forms',
							'Lower options limit must be smaller than the number of available options',
						),
					)
					question.onExtraSettingsChange({
						optionsLimitMin: availableOptions.value - 1 || undefined,
					})
					return
				}

				if (
					extraSettings.value.optionsLimitMax
					&& parsedMin > extraSettings.value.optionsLimitMax
				) {
					showError(
						t(
							'forms',
							'Lower options limit must be smaller than the upper limit',
						),
					)
					return
				}
				question.onExtraSettingsChange({ optionsLimitMin: parsedMin })
				if (parsedMin > 0) {
					emit('update:isRequired', true)
				}
			}
		}

		/**
		 * Update status extra setting allowOtherAnswer and save on DB
		 *
		 * @param allowOtherAnswerValue show/hide field for other answer
		 */
		const onAllowOtherAnswerChange = (allowOtherAnswerValue: boolean): void => {
			question.onExtraSettingsChange({
				allowOtherAnswer: allowOtherAnswerValue,
			})
		}

		/**
		 * Handles the change event for the "Other" answer text input.
		 *
		 * @param value - The new value entered for the "Other" answer.
		 *
		 * This method performs the following actions:
		 * 1. Updates the cached value of the "Other" answer text (`cachedOtherAnswerText`).
		 * 2. Prefixes the input value with a predefined constant (`QUESTION_EXTRASETTINGS_OTHER_PREFIX`).
		 * 3. Emits an `update:values` event with the updated list of values:
		 *    - If `isUnique` is true, the emitted values will only include the prefixed "Other" answer.
		 *    - If `isUnique` is false, the emitted values will include all existing values
		 *      (excluding any that start with the "Other" prefix) and the new prefixed "Other" answer.
		 */
		const onOtherAnswerTextChange = (value: string | number): void => {
			cachedOtherAnswerText.value = String(value)
			// Prefix the value
			const prefixedValue = `${QUESTION_EXTRASETTINGS_OTHER_PREFIX}${String(value)}`
			// emit the values and add the "other" answer
			emit(
				'update:values',
				isUnique.value
					? [prefixedValue]
					: [
							...values.value.filter(
								(v) =>
									!v.startsWith(
										QUESTION_EXTRASETTINGS_OTHER_PREFIX,
									),
							),
							prefixedValue,
						],
			)
		}

		watch(otherAnswer, () => {
			// Ensure that the "other" answer is reset after toggling the checkbox
			resetOtherAnswerText()
		})

		onMounted(() => {
			// Ensure the initial "other" answer is set
			resetOtherAnswerText()
		})

		return {
			...question,
			...questionMultiple,
			allowOtherAnswer,
			availableOptions,
			cachedOtherAnswerText,
			checkRequired,
			choices,
			infoMessage,
			input,
			isDragging,
			isLoading,
			isOptionDialogShown,
			isUnique,
			IconCheckboxBlankOutline,
			IconContentPaste,
			IconRadioboxBlank,
			onAllowOtherAnswerChange,
			onChange,
			onChangeOther,
			onDragEnd,
			onDragStart,
			onLimitOptionsMax,
			onLimitOptionsMin,
			onOtherAnswerTextChange,
			OptionType,
			otherAnswer,
			placeholderOtherAnswer,
			pseudoIcon,
			QUESTION_EXTRASETTINGS_OTHER_PREFIX,
			questionValues,
			resetOtherAnswerText,
			rootElement,
			shiftDragHandle,
			t,
			validate,
		}
	},
})
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
		margin-inline-start: 2px;
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
	transform: translateX(var(--default-clickable-area));
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.options-list-transition-leave-active {
	position: absolute;
}
</style>
