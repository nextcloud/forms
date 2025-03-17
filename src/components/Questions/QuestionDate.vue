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
			<NcActionInput
				v-model="dateMin"
				:aria-label="t('forms', 'Pick minimum date')"
				:formatter="extraSettingsFormatter"
				type="date"
				clearable>
				{{ t('forms', 'Pick minimum date') }}
			</NcActionInput>
			<NcActionInput
				v-model="dateMax"
				:aria-label="t('forms', 'Pick maximum date')"
				:formatter="extraSettingsFormatter"
				type="date">
				{{ t('forms', 'Pick maximum date') }}
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
				@change="onValueChange" />
		</div>
	</Question>
</template>

<script>
import moment from '@nextcloud/moment'

import QuestionMixin from '../../mixins/QuestionMixin.js'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'

export default {
	name: 'QuestionDate',

	components: {
		NcActionInput,
		NcDateTimePicker,
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
		}
	},

	computed: {
		datetimePickerPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
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
			return this.values ? this.parse(this.values[0]) : null
		},

		dateMax: {
			get() {
				return moment(this.extraSettings?.dateMax, 'X').toDate() ?? null
			},
			set(value) {
				if (!value) {
					this.onExtraSettingsChange({
						dateMax: null,
					})
				} else {
					this.onExtraSettingsChange({
						dateMax: parseInt(moment(value).format('X')),
					})
				}
			},
		},

		dateMin: {
			get() {
				return moment(this.extraSettings?.dateMin, 'X').toDate() ?? null
			},
			set(value) {
				if (!value) {
					this.onExtraSettingsChange({
						dateMax: null,
					})
				} else {
					this.onExtraSettingsChange({
						dateMin: parseInt(moment(value).format('X')),
					})
				}
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
		 * @param {Date} date The date to store
		 */
		onValueChange(date) {
			this.$emit('update:values', [
				moment(date).format(this.answerType.storageFormat),
			])
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
