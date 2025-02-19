<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		v-on="commonListeners">
		<div class="question__content">
			<input
				ref="input"
				:aria-label="
					t('forms', 'A short answer for the question “{text}”', {
						text,
					})
				"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:name="name || undefined"
				:required="isRequired"
				:value="values[0]"
				class="question__input"
				dir="auto"
				:maxlength="maxStringLengths.answerText"
				minlength="1"
				:type="validationObject.inputType"
				:step="validationObject.inputType === 'number' ? 'any' : undefined"
				@input="onInput"
				@keydown.enter.exact.prevent="onKeydownEnter" />
			<NcActions
				v-if="!readOnly"
				:id="validationTypeMenuId"
				:aria-label="
					t('forms', 'Input types (currently: {type})', {
						type: validationObject.label,
					})
				"
				:container="`#${validationTypeMenuId}`"
				:open.sync="isValidationTypeMenuOpen"
				class="validation-type-menu__toggle"
				type="tertiary-no-background">
				<template #icon>
					<component :is="validationObject.icon" :size="20" />
				</template>
				<NcActionRadio
					v-for="(
						validationTypeObject, validationTypeName
					) in validationTypes"
					:key="validationTypeName"
					:checked="validationType === validationTypeName"
					:name="validationTypeName"
					@update:checked="onChangeValidationType(validationTypeName)">
					{{ validationTypeObject.label }}
				</NcActionRadio>
				<NcActionInput
					v-if="validationType === 'regex'"
					ref="regexInput"
					:label="t('forms', 'Regular expression for input validation')"
					:value="validationRegex"
					@input="onInputRegex"
					@submit="onSubmitRegex">
					<template #icon>
						<IconRegex :size="20" />
					</template>
					/^[a-z]{3}$/i
					<!-- ^ Some example RegExp for the placeholder text -->
				</NcActionInput>
			</NcActions>
		</div>
	</Question>
</template>

<script>
import { splitRegex, validateExpression } from '../../utils/RegularExpression.js'

import validationTypes from '../../models/ValidationTypes.js'
import QuestionMixin from '../../mixins/QuestionMixin.js'

import IconRegex from 'vue-material-design-icons/Regex.vue'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionRadio from '@nextcloud/vue/dist/Components/NcActionRadio.js'

export default {
	name: 'QuestionShort',

	components: {
		IconRegex,
		NcActions,
		NcActionInput,
		NcActionRadio,
	},

	mixins: [QuestionMixin],

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
					this.validationObject.createPlaceholder ||
					this.answerType.createPlaceholder
				)
			}
			return (
				this.validationObject.submitPlaceholder ||
				this.answerType.submitPlaceholder
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
			return this.extraSettings?.validationType || 'text'
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
			return this.extraSettings?.validationRegex || ''
		},
	},

	methods: {
		onInput() {
			/** @type {HTMLObjectElement} */
			const input = this.$refs.input
			/** @type {string} */
			const value = input.value

			input.setCustomValidity('')

			// Only check non empty values, this question might not be required, if not already invalid
			if (value) {
				// Then check native browser validation (might be better then our)
				// If the browsers validation succeeds either the browser does not implement a validation
				// or it is valid, so we double check by running our custom validation.
				if (
					!input.checkValidity() ||
					!this.validationObject.validate(
						value,
						splitRegex(this.validationRegex),
					)
				) {
					input.setCustomValidity(this.validationObject.errorMessage)
				}
			}

			this.$emit('update:values', [value])
		},

		/**
		 * Change input type
		 *
		 * @param {string} validationType new input type
		 */
		onChangeValidationType(validationType) {
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
		 * @param {InputEvent|SubmitEvent} event input event
		 * @return {boolean} true if the regex is valid
		 */
		onInputRegex(event) {
			if (event?.isComposing) {
				return false
			}

			const input = this.$refs.regexInput.$el.querySelector('input')
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
		 * @param {SubmitEvent} event regex submit event
		 */
		onSubmitRegex(event) {
			if (this.onInputRegex(event)) {
				this.isValidationTypeMenuOpen = false
			}
		},
	},
}
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
