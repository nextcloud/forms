<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<div class="question__content">
			<input
				ref="input"
				:aria-labelledby="titleId"
				:aria-describedby="description ? descriptionId : undefined"
				:aria-errormessage="hasError ? errorId : undefined"
				:aria-invalid="hasError ? 'true' : undefined"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:name="name || undefined"
				:required="isRequired"
				:value="inputValue"
				class="question__input"
				dir="auto"
				:maxlength="maxStringLengths.answerText"
				minlength="1"
				:type="validationObject.inputType"
				:step="validationObject.inputType === 'number' ? 'any' : undefined"
				@invalid.prevent="validate"
				@input="onInput"
				@keydown.enter.exact.prevent="onKeydownEnter" />
			<NcActions
				v-if="!readOnly"
				:id="validationTypeMenuId"
				v-model:open="isValidationTypeMenuOpen"
				:aria-label="
					t('forms', 'Input types (currently: {type})', {
						type: validationObject.label,
					})
				"
				:container="`#${validationTypeMenuId}`"
				class="validation-type-menu__toggle"
				variant="tertiary-no-background">
				<template #icon>
					<NcIconSvgWrapper :svg="validationObject.icon" />
				</template>
				<NcActionRadio
					v-for="(
						validationTypeObject, validationTypeName
					) in validationTypes"
					:key="validationTypeName"
					:modelValue="validationType"
					:name="`${id}_validationMenu`"
					:value="validationTypeName"
					@update:modelValue="onChangeValidationType(validationTypeName)">
					{{ validationTypeObject.label }}
				</NcActionRadio>
				<NcActionInput
					v-if="validationType === 'regex'"
					ref="regexInput"
					:label="t('forms', 'Regular expression for input validation')"
					:modelValue="validationRegex"
					@input="onInputRegex"
					@submit="onSubmitRegex">
					<template #icon>
						<NcIconSvgWrapper :svg="IconRegex" />
					</template>
					/^[a-z]{3}$/i
					<!-- ^ Some example RegExp for the placeholder text -->
				</NcActionInput>
			</NcActions>
		</div>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import IconRegex from '@material-symbols/svg-400/outlined/regular_expression.svg?raw'
import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, defineComponent, ref } from 'vue'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'
import { INPUT_DEBOUNCE_MS } from '../../models/Constants.ts'
import validationTypes from '../../models/ValidationTypes.ts'
import { splitRegex, validateExpression } from '../../utils/RegularExpression.ts'

type QuestionShortExtraSettings = {
	validationRegex?: string
	validationType?: string
}

export default defineComponent({
	name: 'QuestionShort',

	components: {
		NcIconSvgWrapper,
		NcActions,
		NcActionInput,
		NcActionRadio,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })
		const input = ref<HTMLInputElement | null>(null)
		const regexInput = ref<{
			$el: {
				querySelector: (selector: string) => HTMLInputElement | null
			}
		} | null>(null)
		const isValidationTypeMenuOpen = ref(false)
		const values = computed(() => {
			return props.values as Array<
				string | number | readonly string[] | null | undefined
			>
		})
		const extraSettings = computed(() => {
			return (
				(props.extraSettings as QuestionShortExtraSettings | undefined) ?? {}
			)
		})

		/**
		 * Name of the current validation type, fallsback to 'text'
		 */
		const validationType = computed(() => {
			return extraSettings.value.validationType || 'text'
		})

		/**
		 * Current user input validation type
		 */
		const validationObject = computed(
			() => validationTypes[validationType.value],
		)

		/**
		 * Id of the validation type menu
		 */
		const validationTypeMenuId = computed(
			() => 'q' + props.index + '__validation_menu',
		)

		/**
		 * The regular expression
		 */
		const validationRegex = computed(() => {
			return extraSettings.value.validationRegex || ''
		})

		const submissionInputPlaceholder = computed(() => {
			if (!props.readOnly) {
				return (
					validationObject.value.createPlaceholder
					|| props.answerType.createPlaceholder
				)
			}
			return (
				validationObject.value.submitPlaceholder
				|| props.answerType.submitPlaceholder
			)
		})

		const inputValue = computed(() => {
			return (values.value[0] ?? null) as
				string | number | readonly string[] | null | undefined
		})

		const validate = async (): Promise<boolean> => {
			const field = input.value
			if (!field) {
				return true
			}
			const value = field.value

			// Clear the previous custom error before checking native validity.
			field.setCustomValidity('')

			if (props.isRequired && field.validity.valueMissing) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			const isCustomValid =
				!value
				|| validationObject.value.validate(
					value,
					splitRegex(validationRegex.value),
				)

			if (!field.validity.valid || !isCustomValid) {
				field.setCustomValidity(validationObject.value.errorMessage)
				question.errorMessage.value = validationObject.value.errorMessage
				return false
			}

			question.errorMessage.value = null
			return true
		}

		const debounceValidate = debounce(async () => {
			await validate()
		}, INPUT_DEBOUNCE_MS)

		const onInput = (): void => {
			const field = input.value
			if (!field) {
				return
			}
			emit('update:values', [field.value])
			void debounceValidate()
		}

		/**
		 * Change input type
		 *
		 * @param validationType new input type
		 */
		const onChangeValidationType = (validationType: string): void => {
			if (validationType === 'regex') {
				// Make sure to also submit a regex (even if empty)
				question.onExtraSettingsChange({
					validationType,
					validationRegex: validationRegex.value,
				})
			} else {
				// For all other types except regex we close the menu (for regex we keep it open to allow entering a regex)
				isValidationTypeMenuOpen.value = false
				question.onExtraSettingsChange({
					validationType:
						validationType === 'text' ? undefined : validationType,
				})
			}
		}

		/**
		 * Validate and save regex if valid
		 *
		 * Ensures the regex is enclosed with delimters, as required for PCRE,
		 * and regex is only using modifiers supported by JS *and* PHP
		 *
		 * @param event input event
		 * @return true if the regex is valid
		 */
		const onInputRegex = (event: InputEvent | SubmitEvent): boolean => {
			if ('isComposing' in event && event.isComposing) {
				return false
			}

			const regexField = regexInput.value?.$el.querySelector('input')
			if (!regexField) {
				return false
			}
			const validationRegex = regexField.value

			// remove potential previous validity
			regexField.setCustomValidity('')

			if (!validateExpression(validationRegex)) {
				regexField.setCustomValidity(
					t('forms', 'Invalid regular expression'),
				)
				return false
			}

			question.onExtraSettingsChange({ validationRegex })
			return true
		}

		/**
		 * Same as `onInputRegex` but for convinience also closes the menu
		 *
		 * @param event regex submit event
		 */
		const onSubmitRegex = (event: SubmitEvent): void => {
			if (onInputRegex(event)) {
				isValidationTypeMenuOpen.value = false
			}
		}

		return {
			...question,
			IconRegex,
			t,
			validationTypes,
			isValidationTypeMenuOpen,
			submissionInputPlaceholder,
			validationObject,
			validationType,
			validationTypeMenuId,
			validationRegex,
			inputValue,
			input,
			regexInput,
			validate,
			onInput,
			onChangeValidationType,
			onInputRegex,
			onSubmitRegex,
		}
	},
})
</script>

<style lang="scss" scoped>
.question__input {
	width: 100%;
	min-height: var(--default-clickable-area);

	&:disabled {
		width: calc(100% - var(--default-clickable-area)) !important;
		margin-inline-start: -12px;
	}
}

.validation-type-menu__toggle {
	position: relative;
	inset-inline-end: calc(4px + var(--default-clickable-area));
	inset-block-start: 4px;
}

:deep(input:invalid) {
	// nextcloud/server#36548
	border-color: var(--color-error) !important;
}
</style>
