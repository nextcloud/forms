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
		<template v-if="answerType.pickerType === 'date'" #actions>
			<NcActionCheckbox
				:model-value="dateRange"
				@update:model-value="onDateRangeChange">
				{{ t('forms', 'Use date range') }}
			</NcActionCheckbox>
			<NcActionInput
				type="date"
				is-native-picker
				:model-value="dateMin"
				:label="t('forms', 'Earliest date')"
				hide-label
				:formatter="extraSettingsFormatter"
				:max="dateMax"
				@update:model-value="onDateMinChange">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgTodayIcon"
						:name="t('forms', 'Earliest date')" />
				</template>
			</NcActionInput>
			<NcActionInput
				type="date"
				is-native-picker
				:model-value="dateMax"
				:label="t('forms', 'Latest date')"
				hide-label
				:formatter="extraSettingsFormatter"
				:min="dateMin"
				@update:model-value="onDateMaxChange">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgEventIcon"
						:name="t('forms', 'Latest date')" />
				</template>
			</NcActionInput>
		</template>
		<div class="question__content">
			<NcDateTimePicker
				:value="time"
				:disabled="!readOnly"
				:formatter="formatter"
				:placeholder="datetimePickerPlaceholder"
				:show-second="false"
				:type="answerType.pickerType"
				:disabled-date="disabledDates"
				:input-attr="inputAttr"
				:range="extraSettings?.dateRange"
				range-separator=" - "
				@change="onValueChange" />
		</div>
	</Question>
</template>

<script>
import svgEventIcon from '../../../img/event.svg?raw'
import svgTodayIcon from '../../../img/today.svg?raw'

import moment from '@nextcloud/moment'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionDate',

	components: {
		NcActionCheckbox,
		NcActionInput,
		NcDateTimePicker,
		NcIconSvgWrapper,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			formatter: {
				stringify: this.stringify,
				parse: this.parse,
			},
			extraSettingsFormatter: {
				stringify: this.stringifyDate,
				parse: this.parseTimestampToDate,
			},
			svgEventIcon,
			svgTodayIcon,
		}
	},

	computed: {
		datetimePickerPlaceholder() {
			if (this.readOnly) {
				return this.extraSettings?.dateRange
					? this.answerType.submitPlaceholderRange
					: this.answerType.submitPlaceholder
			}
			return this.extraSettings?.dateRange
				? this.answerType.createPlaceholderRange
				: this.answerType.createPlaceholder
		},

		/**
		 * All non-exposed props onto datepicker input-element.
		 *
		 * @return {object}
		 */
		inputAttr() {
			return {
				required: this.isRequired,
				name: this.name || undefined,
			}
		},

		time() {
			if (this.extraSettings?.dateRange) {
				return this.values
					? [this.parse(this.values[0]), this.parse(this.values[1])]
					: null
			}
			return this.values ? this.parse(this.values[0]) : null
		},

		/**
		 * The maximum allowable date for the date input field
		 */
		dateMax() {
			return this.extraSettings?.dateMax
				? moment(this.extraSettings.dateMax, 'X').toDate()
				: null
		},

		/**
		 * The minimum allowable date for the date input field
		 */
		dateMin() {
			return this.extraSettings?.dateMin
				? moment(this.extraSettings.dateMin, 'X').toDate()
				: null
		},

		dateRange() {
			return this.extraSettings?.dateRange ?? false
		},
	},

	methods: {
		/**
		 * DateTimepicker show text in picker
		 * Format depends on component-type date/datetime
		 *
		 * @param {Date} date the selected datepicker Date
		 * @return {string}
		 */
		stringify(date) {
			return moment(date).format(this.answerType.momentFormat)
		},
		/**
		 * Reinterpret a stored date
		 *
		 * @param {string} dateString Stringified date
		 * @return {Date}
		 */
		parse(dateString) {
			return moment(dateString, [
				this.answerType.momentFormat,
				this.answerType.storageFormat,
			]).toDate()
		},

		/**
		 * Handles the change event for the maximum date input.
		 * Updates the maximum allowable date based on the provided value.
		 *
		 * @param {string | Date} value - The new maximum date value. Can be a string or a Date object.
		 */
		onDateMaxChange(value) {
			this.onExtraSettingsChange({
				dateMax: parseInt(moment(value).format('X')),
			})
		},

		/**
		 * Handles the change event for the minimum date input.
		 * Updates the minimum allowable date based on the provided value.
		 *
		 * @param {string | Date} value - The new minimum date value. Can be a string or a Date object.
		 */
		onDateMinChange(value) {
			this.onExtraSettingsChange({
				dateMin: parseInt(moment(value).format('X')),
			})
		},

		/**
		 * Handles the change event for the date range selection.
		 * Updates the extra settings with the new date range value.
		 *
		 * @param {boolean} value - The new value of the date range selection.
		 *                          If true, the date range is enabled; otherwise, null.
		 */
		onDateRangeChange(value) {
			this.onExtraSettingsChange({ dateRange: value === true ?? null })
		},

		/**
		 * Store Value
		 *
		 * @param {Date|Array<Date>} date The date or date range to store
		 */
		onValueChange(date) {
			if (this.extraSettings?.dateRange) {
				this.$emit('update:values', [
					moment(date[0]).format(this.answerType.storageFormat),
					moment(date[1]).format(this.answerType.storageFormat),
				])
			} else {
				this.$emit('update:values', [
					moment(date).format(this.answerType.storageFormat),
				])
			}
		},

		/**
		 * Determines if a given date should be disabled.
		 *
		 * @param {Date} date - The date to check.
		 * @return {boolean} - Returns true if the date should be disabled, otherwise false.
		 */
		disabledDates(date) {
			return (
				(this.dateMin && date < this.dateMin) ||
				(this.dateMax && date > this.dateMax)
			)
		},

		/**
		 * Datepicker timestamp to string
		 *
		 * @param {Date} datetime the datepicker Date
		 * @return {string}
		 */
		stringifyDate(datetime) {
			return moment(datetime).format('L')
		},

		/**
		 * Form expires timestamp to Date of the datepicker
		 *
		 * @param {number} value the expires timestamp
		 * @return {Date}
		 */
		parseTimestampToDate(value) {
			return moment(value, 'X').toDate()
		},
	},
}
</script>

<style lang="scss" scoped>
.mx-datepicker {
	width: 100%;
	max-width: 300px;

	&.disabled {
		inset-inline-start: -12px;
	}

	:deep(.mx-input) {
		height: var(--default-clickable-area) !important;
	}
}
</style>
