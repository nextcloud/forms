<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="branch-condition-editor">
		<!-- Option-based conditions (radio, dropdown, checkbox) -->
		<template v-if="isOptionBasedTrigger">
			<div class="condition-options">
				<!-- Single select for radio/dropdown -->
				<NcSelect
					v-if="isSingleSelect"
					:inputLabel="conditionLabel"
					:modelValue="selectedOptions"
					:options="optionsList"
					:placeholder="t('forms', 'Select an option')"
					:multiple="false"
					label="text"
					@update:modelValue="onSingleOptionSelect" />

				<!-- Multi select for checkboxes -->
				<NcSelect
					v-else
					:inputLabel="conditionLabel"
					:modelValue="selectedOptions"
					:options="optionsList"
					:placeholder="t('forms', 'Select options combination')"
					:multiple="true"
					label="text"
					@update:modelValue="onMultipleOptionsSelect" />
			</div>
		</template>

		<!-- Text-based conditions (short, long) -->
		<template v-else-if="isTextBasedTrigger">
			<div class="condition-row">
				<NcSelect
					v-model="conditionType"
					:options="textConditionTypes"
					:placeholder="t('forms', 'Condition type')"
					:ariaLabelCombobox="t('forms', 'Condition type')"
					label="label"
					:reduce="(opt) => opt.value"
					class="condition-type-select" />
				<NcTextField
					v-model="textConditionValue"
					:label="conditionValuePlaceholder"
					:error="isRegexInvalid"
					:helperText="
						isRegexInvalid
							? t(
									'forms',
									'Invalid regular expression, use the format /pattern/',
								)
							: ''
					"
					class="condition-value-input" />
			</div>
		</template>

		<!-- Value-based conditions (linearscale, color) -->
		<template v-else-if="isValueBasedTrigger">
			<div class="condition-row">
				<template v-if="triggerType === 'linearscale'">
					<NcSelect
						v-model="conditionType"
						:options="valueConditionTypes"
						:placeholder="t('forms', 'Condition type')"
						:ariaLabelCombobox="t('forms', 'Condition type')"
						label="label"
						:reduce="(opt) => opt.value"
						class="condition-type-select" />
					<template
						v-if="
							conditionType === 'value_equals'
							|| conditionType === 'value_not_equals'
						">
						<NcTextField
							v-model.number="conditionValue"
							type="number"
							:label="t('forms', 'Value')"
							class="condition-value-input" />
					</template>
					<template v-else-if="conditionType === 'value_range'">
						<NcTextField
							v-model.number="conditionMin"
							type="number"
							:label="t('forms', 'Minimum value')"
							class="condition-range-input" />
						<span class="condition-range-separator">-</span>
						<NcTextField
							v-model.number="conditionMax"
							type="number"
							:label="t('forms', 'Maximum value')"
							class="condition-range-input" />
					</template>
					<template v-else-if="conditionType === 'value_min'">
						<NcTextField
							v-model.number="conditionMin"
							type="number"
							:label="t('forms', 'Minimum value')"
							class="condition-value-input" />
					</template>
					<template v-else-if="conditionType === 'value_max'">
						<NcTextField
							v-model.number="conditionMax"
							type="number"
							:label="t('forms', 'Maximum value')"
							class="condition-value-input" />
					</template>
				</template>
				<template v-else-if="triggerType === 'color'">
					<NcColorPicker v-model="conditionValue">
						<NcButton
							:aria-label="
								t('forms', 'Select color, current: {color}', {
									color: conditionValue || t('forms', 'none'),
								})
							">
							<template #icon>
								<div
									class="color-preview"
									aria-hidden="true"
									:style="{
										backgroundColor: conditionValue || '#000000',
									}" />
							</template>
							{{ conditionValue || t('forms', 'Select color') }}
						</NcButton>
					</NcColorPicker>
				</template>
			</div>
		</template>

		<!-- Date/time-based conditions -->
		<template v-else-if="isDateBasedTrigger">
			<div class="condition-row">
				<NcDateTimePicker
					v-model="conditionDateMin"
					:type="datePickerType"
					:placeholder="t('forms', 'From')"
					:ariaLabel="t('forms', 'From')"
					class="condition-date-input" />
				<span class="condition-range-separator">-</span>
				<NcDateTimePicker
					v-model="conditionDateMax"
					:type="datePickerType"
					:placeholder="t('forms', 'To')"
					:ariaLabel="t('forms', 'To')"
					class="condition-date-input" />
			</div>
		</template>

		<!-- File-based conditions -->
		<template v-else-if="triggerType === 'file'">
			<div class="condition-row">
				<NcCheckboxRadioSwitch
					:modelValue="fileUploadedCondition"
					@update:modelValue="onFileConditionChange">
					{{ t('forms', 'File is uploaded') }}
				</NcCheckboxRadioSwitch>
			</div>
		</template>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { validateExpression } from '../../utils/RegularExpression.ts'

export default {
	name: 'BranchConditionEditor',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcColorPicker,
		NcDateTimePicker,
		NcSelect,
		NcTextField,
	},

	props: {
		/**
		 * The branch object containing conditions
		 */
		branch: {
			type: Object,
			required: true,
		},

		/**
		 * The trigger question type
		 */
		triggerType: {
			type: String,
			required: true,
		},

		/**
		 * Options for option-based triggers (radio, dropdown, checkbox)
		 */
		options: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update:branch'],

	data() {
		return {
			// Text being typed, an invalid regular expression is not saved
			textDraft: null,
		}
	},

	computed: {
		/**
		 * Check if trigger is option-based (radio, dropdown, checkbox)
		 */
		isOptionBasedTrigger() {
			return ['multiple_unique', 'dropdown', 'multiple'].includes(
				this.triggerType,
			)
		},

		/**
		 * Check if trigger is single-select (radio, dropdown)
		 */
		isSingleSelect() {
			return ['multiple_unique', 'dropdown'].includes(this.triggerType)
		},

		/**
		 * Check if trigger is text-based (short, long)
		 */
		isTextBasedTrigger() {
			return ['short', 'long'].includes(this.triggerType)
		},

		/**
		 * Check if trigger is value-based (linearscale, color)
		 */
		isValueBasedTrigger() {
			return ['linearscale', 'color'].includes(this.triggerType)
		},

		/**
		 * Check if trigger is date-based (date, datetime, time)
		 */
		isDateBasedTrigger() {
			return ['date', 'datetime', 'time'].includes(this.triggerType)
		},

		/**
		 * Label for option-based conditions
		 */
		conditionLabel() {
			if (this.isSingleSelect) {
				return t('forms', 'Show when selected:')
			}
			return t('forms', 'Show when all selected:')
		},

		/**
		 * Options list for NcSelect
		 */
		optionsList() {
			return this.options.map((opt) => ({
				id: opt.id,
				text: opt.text || t('forms', 'Option {id}', { id: opt.id }),
			}))
		},

		/**
		 * Currently selected options based on branch conditions
		 */
		selectedOptions() {
			if (!this.branch.conditions || this.branch.conditions.length === 0) {
				return this.isSingleSelect ? null : []
			}

			let selectedIds
			if (this.isSingleSelect) {
				// Single select uses optionId in each condition
				selectedIds = this.branch.conditions.map((c) => c.optionId)
			} else {
				// Multi select uses optionIds array in first condition
				selectedIds = this.branch.conditions[0]?.optionIds || []
			}
			const selected = this.optionsList.filter((opt) =>
				selectedIds.includes(opt.id),
			)

			return this.isSingleSelect ? selected[0] || null : selected
		},

		/**
		 * Text condition type options
		 */
		textConditionTypes() {
			const types = [
				{ value: 'string_equals', label: t('forms', 'Equals') },
				{ value: 'string_contains', label: t('forms', 'Contains') },
				{ value: 'regex', label: t('forms', 'Matches regex') },
			]
			// Long text doesn't support string_equals
			if (this.triggerType === 'long') {
				return types.filter((t) => t.value !== 'string_equals')
			}
			return types
		},

		/**
		 * Value condition type options (for linear scale)
		 */
		valueConditionTypes() {
			return [
				{ value: 'value_equals', label: t('forms', 'Equals') },
				{ value: 'value_not_equals', label: t('forms', 'Not Equals') },
				{ value: 'value_range', label: t('forms', 'In range') },
				{ value: 'value_min', label: t('forms', 'Higher than') },
				{ value: 'value_max', label: t('forms', 'Lower than') },
			]
		},

		/**
		 * Current condition type from branch
		 */
		conditionType: {
			get() {
				return this.branch.conditions?.[0]?.type || this.defaultConditionType
			},

			set(value) {
				this.updateCondition({ type: value })
			},
		},

		/**
		 * Default condition type based on trigger
		 */
		defaultConditionType() {
			if (this.isTextBasedTrigger) return 'string_contains'
			if (this.triggerType === 'linearscale') return 'value_equals'
			if (this.isDateBasedTrigger) return 'date_range'
			return null
		},

		/**
		 * Current condition value
		 */
		conditionValue: {
			get() {
				return this.branch.conditions?.[0]?.value || ''
			},

			set(value) {
				this.updateCondition({ value })
			},
		},

		/**
		 * Value of a text condition, regular expressions are only saved when valid
		 */
		textConditionValue: {
			get() {
				return this.textDraft ?? this.conditionValue
			},

			set(value) {
				this.textDraft = value
				if (this.conditionType !== 'regex' || validateExpression(value)) {
					this.conditionValue = value
				}
			},
		},

		isRegexInvalid() {
			return (
				this.conditionType === 'regex'
				&& !validateExpression(this.textConditionValue)
			)
		},

		/**
		 * Storage format of date and time conditions, the same as for answers
		 */
		dateStorageFormat() {
			return this.triggerType === 'time' ? 'HH:mm' : 'YYYY-MM-DD'
		},

		conditionDateMin: {
			get() {
				return this.parseDate(this.conditionMin)
			},

			set(value) {
				this.conditionMin = this.formatDate(value)
			},
		},

		conditionDateMax: {
			get() {
				return this.parseDate(this.conditionMax)
			},

			set(value) {
				this.conditionMax = this.formatDate(value)
			},
		},

		/**
		 * Condition min value (for ranges)
		 */
		conditionMin: {
			get() {
				return this.branch.conditions?.[0]?.min || ''
			},

			set(value) {
				this.updateCondition({ min: value })
			},
		},

		/**
		 * Condition max value (for ranges)
		 */
		conditionMax: {
			get() {
				return this.branch.conditions?.[0]?.max || ''
			},

			set(value) {
				this.updateCondition({ max: value })
			},
		},

		/**
		 * File uploaded condition
		 */
		fileUploadedCondition() {
			return this.branch.conditions?.[0]?.fileUploaded ?? true
		},

		/**
		 * Placeholder for condition value input
		 */
		conditionValuePlaceholder() {
			if (this.conditionType === 'regex') {
				return t('forms', 'Regular expression, e.g. /^yes$/i')
			}
			if (this.conditionType === 'string_equals') {
				return t('forms', 'Exact text to match')
			}
			return t('forms', 'Text to search for')
		},

		/**
		 * Date picker type based on trigger type
		 */
		datePickerType() {
			if (this.triggerType === 'time') return 'time'
			if (this.triggerType === 'datetime') return 'datetime'
			return 'date'
		},
	},

	methods: {
		/**
		 * @param {string} value stored date or time
		 * @return {Date|null} the date for the picker
		 */
		parseDate(value) {
			if (!value) {
				return null
			}
			const date = moment(value, this.dateStorageFormat, true)
			return date.isValid() ? date.toDate() : null
		},

		/**
		 * @param {Date|null} value date from the picker
		 * @return {string} the date in the storage format of answers
		 */
		formatDate(value) {
			return value ? moment(value).format(this.dateStorageFormat) : ''
		},

		/**
		 * Handle single option selection (radio/dropdown)
		 *
		 * @param {object|null} option The selected option or null
		 */
		onSingleOptionSelect(option) {
			const conditions = option ? [{ optionId: option.id }] : []
			this.emitUpdate({ conditions })
		},

		/**
		 * Handle multiple option selection (checkbox)
		 *
		 * @param {Array} options The selected options array
		 */
		onMultipleOptionsSelect(options) {
			const optionIds = options.map((opt) => opt.id)
			const conditions = optionIds.length > 0 ? [{ optionIds }] : []
			this.emitUpdate({ conditions })
		},

		/**
		 * Handle file condition change
		 *
		 * @param {boolean} checked Whether file is uploaded condition is checked
		 */
		onFileConditionChange(checked) {
			const conditions = [{ fileUploaded: checked }]
			this.emitUpdate({ conditions })
		},

		/**
		 * Update a condition property
		 *
		 * @param {object} updates The condition properties to update
		 */
		updateCondition(updates) {
			const currentCondition = this.branch.conditions?.[0] || {}
			const newCondition = {
				...currentCondition,
				type: this.conditionType,
				...updates,
			}
			this.emitUpdate({ conditions: [newCondition] })
		},

		/**
		 * Emit branch update
		 *
		 * @param {object} updates The branch properties to update
		 */
		emitUpdate(updates) {
			this.$emit('update:branch', {
				...this.branch,
				...updates,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.branch-condition-editor {
	padding: 8px 0;
}

.condition-options {
	max-width: 400px;
}

.condition-row {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.condition-type-select {
	min-width: 150px;
}

.condition-value-input {
	flex: 1;
	min-width: 200px;
}

.condition-range-input {
	width: 100px;
}

.condition-range-separator {
	color: var(--color-text-maxcontrast);
}

.condition-date-input {
	flex: 1;
}

.color-preview {
	width: 24px;
	height: 24px;
	border-radius: var(--border-radius);
	border: 1px solid var(--color-border-dark);
}
</style>
