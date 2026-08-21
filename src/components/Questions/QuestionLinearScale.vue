<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<template #actions>
			<NcActionInput
				:modelValue="optionsLowest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Lowest value')"
				labelOutside
				:options="[0, 1]"
				required
				@update:modelValue="onOptionsLowestChange">
				<template #icon>
					<NcIconSvgWrapper :svg="IconPencil" />
				</template>
			</NcActionInput>
			<NcActionInput
				:modelValue="optionsHighest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Highest value')"
				labelOutside
				:options="[2, 3, 4, 5, 6, 7, 8, 9, 10]"
				required
				@update:modelValue="onOptionsHighestChange">
				<template #icon>
					<NcIconSvgWrapper :svg="IconPencil" />
				</template>
			</NcActionInput>
		</template>

		<div
			class="question__content question-linear-scale"
			:class="{
				question__content__edit: !readOnly,
			}">
			<NcTextArea
				v-if="!readOnly"
				ref="lowest"
				:modelValue="optionsLabelLowest"
				class="question-linear-scale__label-input"
				:label="t('forms', 'Label for lowest value')"
				:placeholder="t('forms', 'Label (optional)')"
				resize="none"
				@input="resizeLabel('lowest')"
				@blur="onBlur('lowest')"
				@update:modelValue="onOptionsLabelLowestChange" />
			<div
				v-else-if="optionsLabelLowest !== ''"
				:id="labelId"
				class="question-linear-scale__label question-linear-scale__label-lowest">
				{{ optionsLabelLowest }}
			</div>
			<fieldset
				class="question-linear-scale__options"
				:aria-labelledby="titleId"
				:aria-describedby="description ? descriptionId : undefined">
				<legend class="hidden-visually">
					{{
						t('forms', 'From {firstOption} to {lastOption}', {
							firstOption: optionsLabelLowest,
							lastOption: optionsLabelHighest,
						})
					}}
				</legend>
				<div
					v-for="(option, index) in scaleOptions"
					:key="option"
					class="question-linear-scale__option">
					<label :for="`linear-scale-${id}-${option}`">{{ option }}</label>
					<NcCheckboxRadioSwitch
						:id="`linear-scale-${id}-${option}`"
						:aria-describedby="index === 0 ? labelId : undefined"
						:aria-errormessage="hasError ? errorId : undefined"
						:aria-invalid="hasError ? 'true' : undefined"
						:disabled="!readOnly"
						:modelValue="questionValues"
						:value="option.toString()"
						:name="`${id}-answer`"
						type="radio"
						:required="isRequired"
						@invalid.prevent="validate"
						@update:modelValue="onChange"
						@keydown.enter.exact.prevent="onKeydownEnter" />
				</div>
			</fieldset>
			<NcTextArea
				v-if="!readOnly"
				ref="highest"
				:modelValue="optionsLabelHighest"
				class="question-linear-scale__label-input"
				:label="t('forms', 'Label (optional)')"
				:aria-label="t('forms', 'Label for highest value')"
				resize="none"
				@input="resizeLabel('highest')"
				@blur="onBlur('highest')"
				@update:modelValue="onOptionsLabelHighestChange" />
			<div
				v-else-if="optionsLabelHighest !== ''"
				class="question-linear-scale__label question-linear-scale__label-highest">
				{{ optionsLabelHighest }}
			</div>
		</div>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import IconPencil from '@material-symbols/svg-400/outlined/edit.svg?raw'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, onMounted, ref } from 'vue'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

type LinearScaleExtraSettings = {
	optionsLowest?: number | null
	optionsHighest?: number | null
	optionsLabelLowest?: string | null
	optionsLabelHighest?: string | null
}

export default defineComponent({
	name: 'QuestionLinearScale',

	components: {
		NcIconSvgWrapper,
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcTextArea,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })
		const lowest = ref<{ $refs: { input: HTMLTextAreaElement } } | null>(null)
		const highest = ref<{ $refs: { input: HTMLTextAreaElement } } | null>(null)

		const defaultLowestLabel = t('forms', 'Strongly disagree')
		const defaultHighestLabel = t('forms', 'Strongly agree')

		const optionsLowest = computed<number>({
			get: () => {
				const extraSettings = props.extraSettings as
					LinearScaleExtraSettings | undefined
				return extraSettings?.optionsLowest ?? 1
			},
			set: (value: number) => {
				question.onExtraSettingsChange({
					optionsLowest: value === 1 ? null : value,
				})
			},
		})

		const optionsHighest = computed<number>({
			get: () => {
				const extraSettings = props.extraSettings as
					LinearScaleExtraSettings | undefined
				return extraSettings?.optionsHighest ?? 5
			},
			set: (value: number) => {
				question.onExtraSettingsChange({
					optionsHighest: value === 5 ? null : value,
				})
			},
		})

		const optionsLabelLowest = computed<string>({
			get: () => {
				const extraSettings = props.extraSettings as
					LinearScaleExtraSettings | undefined
				return extraSettings?.optionsLabelLowest ?? defaultLowestLabel
			},
			set: (value: string) => {
				question.onExtraSettingsChange({
					optionsLabelLowest: value === defaultLowestLabel ? null : value,
				})
			},
		})

		const optionsLabelHighest = computed<string>({
			get: () => {
				const extraSettings = props.extraSettings as
					LinearScaleExtraSettings | undefined
				return extraSettings?.optionsLabelHighest ?? defaultHighestLabel
			},
			set: (value: string) => {
				question.onExtraSettingsChange({
					optionsLabelHighest:
						value === defaultHighestLabel ? null : value,
				})
			},
		})

		const scaleOptions = computed<number[]>(() => {
			return Array.from(
				{ length: optionsHighest.value - optionsLowest.value + 1 },
				(_, i) => i + optionsLowest.value,
			)
		})

		const questionValues = computed(() => props.values)

		/**
		 * ID for the label for the lowest option
		 */
		const labelId = computed(() => 'q' + props.index + '__label_lowest')
		const isUnique = computed(() => props.answerType.unique === true)

		/**
		 * Resizes the given label to fit within the specified constraints.
		 *
		 * @param label - The label identifier, either 'lowest' or 'highest', indicating which label to resize.
		 */
		const resizeLabel = (label: 'lowest' | 'highest'): void => {
			let textarea: HTMLTextAreaElement | undefined
			const refTarget = label === 'lowest' ? lowest.value : highest.value
			if (refTarget) {
				textarea = refTarget.$refs.input
			}
			// next tick ensures that the textarea is attached to DOM
			nextTick(() => {
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px; resize: none;`
				}
			})
		}

		onMounted(() => {
			if (!props.readOnly) {
				resizeLabel('lowest')
				resizeLabel('highest')
			}
		})

		const validate = async (): Promise<boolean> => {
			if (props.isRequired && props.values.length === 0) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			question.errorMessage.value = null
			return true
		}

		const onChange = (option: string): void => {
			emit('update:values', [option])
		}

		const onOptionsLowestChange = (value: number): void => {
			optionsLowest.value = value
		}

		const onOptionsHighestChange = (value: number): void => {
			optionsHighest.value = value
		}

		const onOptionsLabelLowestChange = (value: string): void => {
			optionsLabelLowest.value = value
		}

		const onOptionsLabelHighestChange = (value: string): void => {
			optionsLabelHighest.value = value
		}

		/**
		 * Handles the blur event for a label input.
		 *
		 * @param label - The label that is being blurred.
		 *                         It can be either 'lowest' or 'highest' indicating
		 *                         which label input (lowest value or highest value) triggered the blur event.
		 */
		const onBlur = (label: 'lowest' | 'highest'): void => {
			if (label === 'lowest') {
				optionsLabelLowest.value = optionsLabelLowest.value
					.replace(/[\r\n]+/gm, ' ')
					.trim()
			} else if (label === 'highest') {
				optionsLabelHighest.value = optionsLabelHighest.value
					.replace(/[\r\n]+/gm, ' ')
					.trim()
			}
			resizeLabel(label)
		}

		return {
			...question,
			IconPencil,
			t,
			lowest,
			highest,
			scaleOptions,
			isUnique,
			questionValues,
			labelId,
			optionsLowest,
			optionsHighest,
			optionsLabelLowest,
			optionsLabelHighest,
			validate,
			onChange,
			onOptionsLowestChange,
			onOptionsHighestChange,
			onOptionsLabelLowestChange,
			onOptionsLabelHighestChange,
			resizeLabel,
			onBlur,
		}
	},
})
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;

	@media (max-width: 768px) {
		flex-wrap: wrap; // Allow wrapping for smaller screens
	}

	&__edit {
		margin-inline-start: -12px;

		@media (max-width: 768px) {
			margin-inline-end: calc(var(--clickable-area-large) - 2px);
		}
	}

	.question-linear-scale {
		&__label {
			width: 120px;
			align-self: center;
			flex-shrink: 0;

			&-lowest {
				text-align: start;
			}

			&-highest {
				text-align: end;

				@media (max-width: 768px) {
					text-align: start;
				}
			}

			@media (max-width: 768px) {
				width: 100%; // Full width on smaller screens
				padding-block: var(--default-grid-baseline);
			}
		}

		&__label-input {
			width: 120px;
			align-self: center;
			min-height: fit-content;
			flex-shrink: 0;

			@media (max-width: 768px) {
				width: 100%; // Full width on smaller screens
				padding-block: var(--default-grid-baseline);
			}
		}

		&__options {
			width: 100%;
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: space-evenly;
			flex-grow: 1;

			@media (max-width: 768px) {
				flex-direction: column; // Stack options vertically on smaller screens
				align-items: flex-start; // Align items to the left
			}
		}

		&__option {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;

			@media (max-width: 768px) {
				flex-direction: row-reverse;
			}
		}
	}
}
</style>
