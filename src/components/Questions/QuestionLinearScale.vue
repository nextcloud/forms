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
		<template #actions>
			<NcActionInput
				type="multiselect"
				:options="[0, 1]"
				:v-model="lowestOption">
				{{ t('forms', 'Lowest value') }}
			</NcActionInput>
			<NcActionInput
				type="multiselect"
				:options="[2, 3, 4, 5, 6, 7, 8, 9, 10]"
				:v-model="highestOption">
				{{ t('forms', 'Highest value"') }}
			</NcActionInput>
			<NcActionInput>
				{{ t('forms', 'Label for lowest value"') }}
			</NcActionInput>
			<NcActionInput>
				{{ t('forms', 'Label for highest value"') }}
			</NcActionInput>
		</template>

		<fieldset :name="name || undefined" :aria-labelledby="titleId">
			<NcNoteCard v-if="hasError" :id="errorId" type="error">
				{{ errorMessage }}
			</NcNoteCard>
			<NcCheckboxRadioSwitch
				v-for="option in scaleOptions"
				:key="option"
				:disabled="!readOnly"
				:aria-errormessage="hasError ? errorId : undefined"
				:aria-invalid="hasError ? 'true' : undefined"
				:checked="questionValues"
				:value="option.toString()"
				:name="`${id}-answer`"
				button-variant
				button-variant-grouped="horizontal"
				type="radio"
				:required="checkRequired(option)"
				@update:checked="onChange"
				@keydown.enter.exact.prevent="onKeydownEnter">
				{{ option }}
			</NcCheckboxRadioSwitch>
		</fieldset>
	</Question>
</template>

<script>
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionLinearScale',

	components: {
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcNoteCard,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			/**
			 * The shown error message
			 */
			errorMessage: null,

			isLoading: false,

			lowestOption: 1,
			highestOption: 5,
		}
	},

	computed: {
		scaleOptions() {
			return Array.from(
				{ length: this.highestOption - this.lowestOption + 1 },
				(_, i) => i + this.lowestOption,
			)
		},

		isUnique() {
			return this.answerType.unique === true
		},

		hasError() {
			return !!this.errorMessage
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

		onChange(value) {
			this.$emit('update:values', this.isUnique ? [value].flat() : value)
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
	},
}
</script>

<style lang="scss" scoped>
</style>
