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
		<template v-if="answerType.pickerType === 'date'" #actions>
			<NcActionCheckbox
				:modelValue="dateRange"
				@update:modelValue="onDateRangeChange">
				{{ t('forms', 'Use date range') }}
			</NcActionCheckbox>
			<NcActionInput
				type="date"
				isNativePicker
				:modelValue="dateMin"
				:label="t('forms', 'Earliest date')"
				hideLabel
				:formatter="extraSettingsFormatter"
				:max="dateMax"
				@update:modelValue="onDateMinChange">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgTodayIcon"
						:name="t('forms', 'Earliest date')" />
				</template>
			</NcActionInput>
			<NcActionInput
				type="date"
				isNativePicker
				:modelValue="dateMax"
				:label="t('forms', 'Latest date')"
				hideLabel
				:formatter="extraSettingsFormatter"
				:min="dateMin"
				@update:modelValue="onDateMaxChange">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgEventIcon"
						:name="t('forms', 'Latest date')" />
				</template>
			</NcActionInput>
		</template>
		<template v-else-if="answerType.pickerType === 'time'" #actions>
			<NcActionCheckbox
				:modelValue="timeRange"
				@update:modelValue="onTimeRangeChange">
				{{ t('forms', 'Use time range') }}
			</NcActionCheckbox>
			<NcActionInput
				type="time"
				isNativePicker
				:modelValue="timeMin"
				:label="t('forms', 'Earliest time')"
				hideLabel
				:max="timeMax"
				@update:modelValue="onTimeMinChange">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgClockLoader20"
						:name="t('forms', 'Earliest time')" />
				</template>
			</NcActionInput>
			<NcActionInput
				type="time"
				isNativePicker
				:modelValue="timeMax"
				:label="t('forms', 'Latest time')"
				hideLabel
				:min="timeMin"
				@update:modelValue="onTimeMaxChange">
				<template #icon>
					<NcIconSvgWrapper
						:svg="svgClockLoader80"
						:name="t('forms', 'Latest time')" />
				</template>
			</NcActionInput>
		</template>
		<div
			class="question__content"
			role="group"
			:aria-labelledby="titleId"
			:aria-describedby="description ? descriptionId : undefined">
			<NcDateTimePicker
				:modelValue="time"
				:disabled="!readOnly"
				:format="stringify"
				:placeholder="datetimePickerPlaceholder"
				:showSecond="false"
				:type="dateTimePickerType"
				:disabledDate="disabledDates"
				:disabledTime="disabledTimes"
				:aria-required="isRequired"
				:aria-errormessage="hasError ? errorId : undefined"
				:aria-invalid="hasError ? 'true' : undefined"
				clearable
				@update:modelValue="onValueChange" />
		</div>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { defineComponent } from 'vue'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import svgClockLoader20 from '../../../img/clock_loader_20.svg?raw'
import svgClockLoader80 from '../../../img/clock_loader_80.svg?raw'
import svgEventIcon from '../../../img/event.svg?raw'
import svgTodayIcon from '../../../img/today.svg?raw'
import QuestionMixin from '../../mixins/QuestionMixin.ts'

type PickerType =
	'date' | 'datetime' | 'time' | 'date-range' | 'datetime-range' | 'time-range'

type QuestionDateExtraSettings = {
	dateRange?: boolean
	dateMax?: number | null
	dateMin?: number | null
	timeRange?: boolean
	timeMax?: string | null
	timeMin?: string | null
}

export default defineComponent({
	name: 'QuestionDate',

	components: {
		NcActionCheckbox,
		NcActionInput,
		NcDateTimePicker,
		NcIconSvgWrapper,
		Question,
	},

	mixins: [QuestionMixin],
	emits: ['update:values'],

	data() {
		return {
			extraSettingsFormatter: {
				stringify: this.stringifyDate,
				parse: this.parseTimestampToDate,
			},

			svgClockLoader80,
			svgClockLoader20,
			svgEventIcon,
			svgTodayIcon,
			t,
		}
	},

	computed: {
		isRangeQuestion(): boolean {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.dateRange || extraSettings?.timeRange
				? true
				: false
		},

		datetimePickerPlaceholder(): string {
			if (this.readOnly) {
				return this.isRangeQuestion
					? this.answerType.submitPlaceholderRange
					: this.answerType.submitPlaceholder
			}
			return this.isRangeQuestion
				? this.answerType.createPlaceholderRange
				: this.answerType.createPlaceholder
		},

		dateTimePickerType(): PickerType {
			return this.isRangeQuestion
				? (`${this.answerType.pickerType}-range` as PickerType)
				: (this.answerType.pickerType as PickerType)
		},

		time(): Date | [Date, Date] | null {
			if (this.isRangeQuestion) {
				const firstValue = this.values?.[0] as string | undefined
				const secondValue = this.values?.[1] as string | undefined
				return firstValue && secondValue
					? [this.parse(firstValue), this.parse(secondValue)]
					: null
			}
			const value = this.values?.[0] as string | undefined
			return value ? this.parse(value) : null
		},

		/**
		 * The maximum allowable date for the date input field
		 */
		dateMax(): Date | undefined {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.dateMax
				? moment(extraSettings.dateMax, 'X').toDate()
				: undefined
		},

		/**
		 * The minimum allowable date for the date input field
		 */
		dateMin(): Date | undefined {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.dateMin
				? moment(extraSettings.dateMin, 'X').toDate()
				: undefined
		},

		dateRange(): boolean {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.dateRange ?? false
		},

		/**
		 * The maximum allowable time for the time input field
		 */
		timeMax(): Date | undefined {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.timeMax
				? moment(
						extraSettings.timeMax,
						this.answerType.storageFormat,
					).toDate()
				: undefined
		},

		/**
		 * The minimum allowable time for the time input field
		 */
		timeMin(): Date | undefined {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.timeMin
				? moment(
						extraSettings.timeMin,
						this.answerType.storageFormat,
					).toDate()
				: undefined
		},

		timeRange(): boolean {
			const extraSettings = this.extraSettings as
				QuestionDateExtraSettings | undefined
			return extraSettings?.timeRange ?? false
		},
	},

	methods: {
		async validate(): Promise<boolean> {
			if (this.isRequired && this.time === null) {
				this.errorMessage = t('forms', 'You must answer this question')
				return false
			}

			this.errorMessage = null
			return true
		},

		/**
		 * DateTimepicker show text in picker
		 * Format depends on component-type date/datetime
		 *
		 * @param date the selected datepicker Date
		 * @return
		 */
		stringify(date: Date | Date[]): string {
			if (this.isRangeQuestion && Array.isArray(date)) {
				return `${moment(date[0]).format(this.answerType.momentFormat)} - ${moment(date[1]).format(this.answerType.momentFormat)}`
			}
			return moment(date).format(this.answerType.momentFormat)
		},

		/**
		 * Reinterpret a stored date
		 *
		 * @param dateString Stringified date
		 * @return
		 */
		parse(dateString: string): Date {
			return moment(dateString, [
				this.answerType.momentFormat,
				this.answerType.storageFormat,
			]).toDate()
		},

		/**
		 * Handles the change event for the maximum date input.
		 * Updates the maximum allowable date based on the provided value.
		 *
		 * @param value - The new maximum date value. Can be a string or a Date object.
		 */
		onDateMaxChange(value: string | Date): void {
			this.onExtraSettingsChange({
				dateMax: parseInt(moment(value).format('X')),
			})
		},

		/**
		 * Handles the change event for the minimum date input.
		 * Updates the minimum allowable date based on the provided value.
		 *
		 * @param value - The new minimum date value. Can be a string or a Date object.
		 */
		onDateMinChange(value: string | Date): void {
			this.onExtraSettingsChange({
				dateMin: parseInt(moment(value).format('X')),
			})
		},

		/**
		 * Handles the change event for the date range selection.
		 * Updates the extra settings with the new date range value.
		 *
		 * @param value - The new value of the date range selection.
		 *                          If true, the date range is enabled; otherwise, null.
		 */
		onDateRangeChange(value: boolean): void {
			this.onExtraSettingsChange({ dateRange: value === true ? true : null })
		},

		/**
		 * Handles the change event for the maximum time input.
		 * Updates the maximum allowable date based on the provided value.
		 *
		 * @param value - The new maximum date value. Can be a string or a Date object.
		 */
		onTimeMaxChange(value: string | Date | null): void {
			this.onExtraSettingsChange({
				timeMax:
					value === null
					|| (value instanceof Date
						&& value.getTime()
							=== new Date(new Date().setHours(24, 0, 0, 0)).getTime())
						? null
						: moment(value).format(this.answerType.storageFormat),
			})
		},

		/**
		 * Handles the change event for the minimum date input.
		 * Updates the minimum allowable date based on the provided value.
		 *
		 * @param value - The new minimum date value. Can be a string or a Date object.
		 */
		onTimeMinChange(value: string | Date | null): void {
			this.onExtraSettingsChange({
				timeMin:
					value === null
					|| (value instanceof Date
						&& value.getTime()
							=== new Date(new Date().setHours(0, 0, 0, 0)).getTime())
						? null
						: moment(value).format(this.answerType.storageFormat),
			})
		},

		/**
		 * Handles the change event for the date range selection.
		 * Updates the extra settings with the new date range value.
		 *
		 * @param value - The new value of the date range selection.
		 *                          If true, the date range is enabled; otherwise, null.
		 */
		onTimeRangeChange(value: boolean): void {
			this.onExtraSettingsChange({ timeRange: value === true ? true : null })
		},

		/**
		 * Store Value
		 *
		 * @param date The date or date range to store
		 */
		onValueChange(date: Date | [Date, Date] | null): void {
			if (!date) {
				this.$emit('update:values', [])
				return
			}

			if (this.isRangeQuestion && Array.isArray(date)) {
				this.$emit('update:values', [
					moment(date[0]).format(this.answerType.storageFormat),
					moment(date[1]).format(this.answerType.storageFormat),
				])
				return
			} else {
				this.$emit('update:values', [
					moment(date).format(this.answerType.storageFormat),
				])
			}
		},

		/**
		 * Determines if a given date should be disabled.
		 *
		 * @param date - The date to check.
		 * @return - Returns true if the date should be disabled, otherwise false.
		 */
		disabledDates(date: Date): boolean {
			return Boolean(
				(this.dateMin && date < this.dateMin)
				|| (this.dateMax && date > this.dateMax),
			)
		},

		/**
		 * Determines if a given time should be disabled.
		 *
		 * @param time - The time to check.
		 * @return - Returns true if the time should be disabled, otherwise false.
		 */
		disabledTimes(time: Date): boolean {
			return Boolean(
				(this.timeMin && time < this.timeMin)
				|| (this.timeMax && time > this.timeMax),
			)
		},

		/**
		 * Datepicker timestamp to string
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		stringifyDate(datetime: Date): string {
			return moment(datetime).format('L')
		},

		/**
		 * Form expires timestamp to Date of the datepicker
		 *
		 * @param value the expires timestamp
		 * @return
		 */
		parseTimestampToDate(value: number): Date {
			return moment(value, 'X').toDate()
		},
	},
})
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
