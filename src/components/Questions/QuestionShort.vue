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
import { translate as t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { defineComponent } from 'vue'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionRadio from '@nextcloud/vue/components/NcActionRadio'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.ts'
import { INPUT_DEBOUNCE_MS } from '../../models/Constants.ts'
import validationTypes from '../../models/ValidationTypes.ts'
import { splitRegex, validateExpression } from '../../utils/RegularExpression.ts'

export default defineComponent({
	name: 'QuestionShort',

	components: {
		NcIconSvgWrapper,
		NcActions,
		NcActionInput,
		NcActionRadio,
		Question,
	},

	mixins: [QuestionMixin],
	emits: ['update:values'],

	setup() {
		return {
			IconRegex,
			t,
		}
	},

	data() {
		return {
			validationTypes,
			isValidationTypeMenuOpen: false,
		}
	},

	computed: {
		submissionInputPlaceholder() {
			if (!this.readOnly) {
				return (
					this.validationObject.createPlaceholder
					|| this.answerType.createPlaceholder
				)
			}
			return (
				this.validationObject.submitPlaceholder
				|| this.answerType.submitPlaceholder
			)
		},

		/**
		 * Current user input validation type
		 */
		validationObject() {
			return validationTypes[this.validationType]
		},

		/**
		 * Name of the current validation type, fallsback to 'text'
		 */
		validationType() {
			return (
				(this.extraSettings as { validationType?: string } | undefined)
					?.validationType || 'text'
			)
		},

		/**
		 * Id of the validation type menu
		 */
		validationTypeMenuId() {
			return 'q' + this.index + '__validation_menu'
		},

		/**
		 * The regular expression
		 */
		validationRegex() {
			return (
				(this.extraSettings as { validationRegex?: string } | undefined)
					?.validationRegex || ''
			)
		},

		inputValue(): string | number | readonly string[] | null | undefined {
			return this.values[0] as
				string | number | readonly string[] | null | undefined
		},
	},

	methods: {
		async validate(): Promise<boolean> {
			const input = this.$refs.input as unknown as HTMLInputElement
			const value = input.value

			// Clear the previous custom error before checking native validity.
			input.setCustomValidity('')

			if (this.isRequired && input.validity.valueMissing) {
				this.errorMessage = t('forms', 'You must answer this question')
				return false
			}

			const isCustomValid =
				!value
				|| this.validationObject.validate(
					value,
					splitRegex(this.validationRegex),
				)

			if (!input.validity.valid || !isCustomValid) {
				input.setCustomValidity(this.validationObject.errorMessage)
				this.errorMessage = this.validationObject.errorMessage
				return false
			}

			this.errorMessage = null
			return true
		},

		debounceValidate: debounce(async function (this: {
			validate: () => Promise<boolean>
		}) {
			this.validate()
		}, INPUT_DEBOUNCE_MS),

		onInput(): void {
			const input = this.$refs.input as unknown as HTMLInputElement
			const value = input.value
			this.$emit('update:values', [value])
			this.debounceValidate()
		},

		/**
		 * Change input type
		 *
		 * @param validationType new input type
		 */
		onChangeValidationType(validationType: string): void {
			if (validationType === 'regex') {
				// Make sure to also submit a regex (even if empty)
				this.onExtraSettingsChange({
					validationType,
					validationRegex: this.validationRegex,
				})
			} else {
				// For all other types except regex we close the menu (for regex we keep it open to allow entering a regex)
				this.isValidationTypeMenuOpen = false
				this.onExtraSettingsChange({
					validationType:
						validationType === 'text' ? undefined : validationType,
				})
			}
		},

		/**
		 * Validate and save regex if valid
		 *
		 * Ensures the regex is enclosed with delimters, as required for PCRE,
		 * and regex is only using modifiers supported by JS *and* PHP
		 *
		 * @param event input event
		 * @return true if the regex is valid
		 */
		onInputRegex(event: InputEvent | SubmitEvent): boolean {
			if ('isComposing' in event && event.isComposing) {
				return false
			}

			const input = (
				this.$refs.regexInput as unknown as {
					$el: {
						querySelector: (selector: string) => HTMLInputElement | null
					}
				}
			).$el.querySelector('input')
			if (!input) {
				return false
			}
			const validationRegex = input.value

			// remove potential previous validity
			input.setCustomValidity('')

			if (!validateExpression(validationRegex)) {
				input.setCustomValidity(t('forms', 'Invalid regular expression'))
				return false
			}

			this.onExtraSettingsChange({ validationRegex })
			return true
		},

		/**
		 * Same as `onInputRegex` but for convinience also closes the menu
		 *
		 * @param event regex submit event
		 */
		onSubmitRegex(event: SubmitEvent): void {
			if (this.onInputRegex(event)) {
				this.isValidationTypeMenuOpen = false
			}
		},
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
