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
			<NcActionCheckbox v-model="dateRange">
				{{ t('forms', 'Use date range') }}
			</NcActionCheckbox>
			<NcActionInput
				v-model="dateMin"
				type="date"
				:label="t('forms', 'Earliest date')"
				hide-label
				:formatter="extraSettingsFormatter"
				is-native-picker
				:max="dateMax">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgTodayIcon"
						:name="t('forms', 'Earliest date')" />
				</template>
			</NcActionInput>
			<NcActionInput
				v-model="dateMax"
				type="date"
				:label="t('forms', 'Latest date')"
				hide-label
				:formatter="extraSettingsFormatter"
				is-native-picker
				:min="dateMin">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgEventIcon"
						:name="t('forms', 'Latest date')" />
				</template>
			</NcActionInput>
		</template>
		<template v-else-if="answerType.pickerType === 'time'" #actions>
			<NcActionCheckbox v-model="timeRange">
				{{ t('forms', 'Use time range') }}
			</NcActionCheckbox>
			<NcActionInput
				v-model="timeMin"
				type="time"
				:label="t('forms', 'Earliest time')"
				hide-label
				:formatter="formatter"
				is-native-picker
				:max="timeMax">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgClockArrowUpIcon"
						:name="t('forms', 'Earliest time')" />
				</template>
			</NcActionInput>
			<NcActionInput
				v-model="timeMax"
				type="time"
				:label="t('forms', 'Latest time')"
				hide-label
				:formatter="formatter"
				is-native-picker
				:min="timeMin">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgClockArrowDownIcon"
						:name="t('forms', 'Latest time')" />
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
import svgClockArrowDownIcon from '../../../img/clock_arrow_down.svg?raw'
import svgClockArrowUpIcon from '../../../img/clock_arrow_up.svg?raw'
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
			svgClockArrowDownIcon,
			svgClockArrowUpIcon,
			svgEventIcon,
			svgTodayIcon,
		}
	},

	computed: {
		datetimePickerPlaceholder() {
			if (this.readOnly) {
				return this.extraSettings?.dateRange || this.extraSettings?.timeRange
					? this.answerType.submitPlaceholderRange
					: this.answerType.submitPlaceholder
			}
			return this.extraSettings?.dateRange || this.extraSettings?.timeRange
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
			if (this.extraSettings?.dateRange || this.extraSettings?.timeRange) {
				return this.values
					? [this.parse(this.values[0]), this.parse(this.values[1])]
					: null
			}
			return this.values ? this.parse(this.values[0]) : null
		},

		/**
		 * The maximum allowable date for the date input field
		 */
		dateMax: {
			get() {
				return this.extraSettings?.dateMax
					? moment(this.extraSettings.dateMax, 'X').toDate()
					: null
			},
			set(value) {
				this.onExtraSettingsChange({
					dateMax: parseInt(moment(value).format('X')),
				})
			},
		},

		/**
		 * The minimum allowable date for the date input field
		 */
		dateMin: {
			get() {
				return this.extraSettings?.dateMin
					? moment(this.extraSettings.dateMin, 'X').toDate()
					: null
			},
			set(value) {
				this.onExtraSettingsChange({
					dateMin: parseInt(moment(value).format('X')),
				})
			},
		},

		dateRange: {
			get() {
				return this.extraSettings?.dateRange ?? false
			},
			set(value) {
				this.onExtraSettingsChange({ dateRange: value === true ?? null })
			},
		},

		/**
		 * The maximum allowable time for the time input field
		 */
		timeMax: {
			get() {
				return this.extraSettings?.timeMax
					? this.parse(this.extraSettings.timeMax)
					: null
			},
			set(value) {
				this.onExtraSettingsChange({
					timeMax: this.stringify(value),
				})
			},
		},

		/**
		 * The minimum allowable time for the time input field
		 */
		timeMin: {
			get() {
				return this.extraSettings?.timeMin
					? this.parse(this.extraSettings.timeMax)
					: null
			},
			set(value) {
				this.onExtraSettingsChange({
					timeMin: this.stringify(value),
				})
			},
		},

		timeRange: {
			get() {
				return this.extraSettings?.timeRange ?? false
			},
			set(value) {
				this.onExtraSettingsChange({ timeRange: value === true ?? null })
			},
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
