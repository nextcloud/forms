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
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { computed, defineComponent } from 'vue'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import svgClockLoader20 from '../../../img/clock_loader_20.svg?raw'
import svgClockLoader80 from '../../../img/clock_loader_80.svg?raw'
import svgEventIcon from '../../../img/event.svg?raw'
import svgTodayIcon from '../../../img/today.svg?raw'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

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

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })
		const values = computed(() => {
			return props.values as Array<string | undefined>
		})
		const extraSettings = computed(() => {
			return (
				(props.extraSettings as QuestionDateExtraSettings | undefined) ?? {}
			)
		})

		const isRangeQuestion = computed(() => {
			return extraSettings.value.dateRange || extraSettings.value.timeRange
				? true
				: false
		})

		/**
		 * Datepicker timestamp to string
		 *
		 * @param datetime the datepicker Date
		 * @return
		 */
		const stringifyDate = (datetime: Date): string => {
			return moment(datetime).format('L')
		}

		/**
		 * Form expires timestamp to Date of the datepicker
		 *
		 * @param value the expires timestamp
		 * @return
		 */
		const parseTimestampToDate = (value: number): Date => {
			return moment(value, 'X').toDate()
		}

		/**
		 * DateTimepicker show text in picker
		 * Format depends on component-type date/datetime
		 *
		 * @param date the selected datepicker Date
		 * @return
		 */
		const stringify = (date: Date | Date[]): string => {
			if (isRangeQuestion.value && Array.isArray(date)) {
				return `${moment(date[0]).format(props.answerType.momentFormat)} - ${moment(date[1]).format(props.answerType.momentFormat)}`
			}
			return moment(date).format(props.answerType.momentFormat)
		}

		/**
		 * Reinterpret a stored date
		 *
		 * @param dateString Stringified date
		 * @return
		 */
		const parse = (dateString: string): Date => {
			return moment(dateString, [
				props.answerType.momentFormat,
				props.answerType.storageFormat,
			]).toDate()
		}

		const datetimePickerPlaceholder = computed(() => {
			if (props.readOnly) {
				return isRangeQuestion.value
					? props.answerType.submitPlaceholderRange
					: props.answerType.submitPlaceholder
			}
			return isRangeQuestion.value
				? props.answerType.createPlaceholderRange
				: props.answerType.createPlaceholder
		})

		const dateTimePickerType = computed<PickerType>(() => {
			return isRangeQuestion.value
				? (`${props.answerType.pickerType}-range` as PickerType)
				: (props.answerType.pickerType as PickerType)
		})

		const time = computed<Date | [Date, Date] | null>(() => {
			if (isRangeQuestion.value) {
				const firstValue = values.value[0]
				const secondValue = values.value[1]
				return firstValue && secondValue
					? [parse(firstValue), parse(secondValue)]
					: null
			}
			const value = values.value[0]
			return value ? parse(value) : null
		})

		/**
		 * The maximum allowable date for the date input field
		 */
		const dateMax = computed<Date | undefined>(() => {
			return extraSettings.value.dateMax
				? moment(extraSettings.value.dateMax, 'X').toDate()
				: undefined
		})

		/**
		 * The minimum allowable date for the date input field
		 */
		const dateMin = computed<Date | undefined>(() => {
			return extraSettings.value.dateMin
				? moment(extraSettings.value.dateMin, 'X').toDate()
				: undefined
		})

		const dateRange = computed(() => {
			return extraSettings.value.dateRange ?? false
		})

		/**
		 * The maximum allowable time for the time input field
		 */
		const timeMax = computed<Date | undefined>(() => {
			return extraSettings.value.timeMax
				? moment(
						extraSettings.value.timeMax,
						props.answerType.storageFormat,
					).toDate()
				: undefined
		})

		/**
		 * The minimum allowable time for the time input field
		 */
		const timeMin = computed<Date | undefined>(() => {
			return extraSettings.value.timeMin
				? moment(
						extraSettings.value.timeMin,
						props.answerType.storageFormat,
					).toDate()
				: undefined
		})

		const timeRange = computed(() => {
			return extraSettings.value.timeRange ?? false
		})

		const validate = async (): Promise<boolean> => {
			if (props.isRequired && time.value === null) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			question.errorMessage.value = null
			return true
		}

		/**
		 * Handles the change event for the maximum date input.
		 * Updates the maximum allowable date based on the provided value.
		 *
		 * @param value - The new maximum date value. Can be a string or a Date object.
		 */
		const onDateMaxChange = (value: string | Date): void => {
			question.onExtraSettingsChange({
				dateMax: parseInt(moment(value).format('X')),
			})
		}

		/**
		 * Handles the change event for the minimum date input.
		 * Updates the minimum allowable date based on the provided value.
		 *
		 * @param value - The new minimum date value. Can be a string or a Date object.
		 */
		const onDateMinChange = (value: string | Date): void => {
			question.onExtraSettingsChange({
				dateMin: parseInt(moment(value).format('X')),
			})
		}

		/**
		 * Handles the change event for the date range selection.
		 * Updates the extra settings with the new date range value.
		 *
		 * @param value - The new value of the date range selection.
		 *                          If true, the date range is enabled; otherwise, null.
		 */
		const onDateRangeChange = (value: boolean): void => {
			question.onExtraSettingsChange({
				dateRange: value === true ? true : null,
			})
		}

		/**
		 * Handles the change event for the maximum time input.
		 * Updates the maximum allowable date based on the provided value.
		 *
		 * @param value - The new maximum date value. Can be a string or a Date object.
		 */
		const onTimeMaxChange = (value: string | Date | null): void => {
			question.onExtraSettingsChange({
				timeMax:
					value === null
					|| (value instanceof Date
						&& value.getTime()
							=== new Date(new Date().setHours(24, 0, 0, 0)).getTime())
						? null
						: moment(value).format(props.answerType.storageFormat),
			})
		}

		/**
		 * Handles the change event for the minimum date input.
		 * Updates the minimum allowable date based on the provided value.
		 *
		 * @param value - The new minimum date value. Can be a string or a Date object.
		 */
		const onTimeMinChange = (value: string | Date | null): void => {
			question.onExtraSettingsChange({
				timeMin:
					value === null
					|| (value instanceof Date
						&& value.getTime()
							=== new Date(new Date().setHours(0, 0, 0, 0)).getTime())
						? null
						: moment(value).format(props.answerType.storageFormat),
			})
		}

		/**
		 * Handles the change event for the date range selection.
		 * Updates the extra settings with the new date range value.
		 *
		 * @param value - The new value of the date range selection.
		 *                          If true, the date range is enabled; otherwise, null.
		 */
		const onTimeRangeChange = (value: boolean): void => {
			question.onExtraSettingsChange({
				timeRange: value === true ? true : null,
			})
		}

		/**
		 * Store Value
		 *
		 * @param date The date or date range to store
		 */
		const onValueChange = (date: Date | [Date, Date] | null): void => {
			if (!date) {
				emit('update:values', [])
				return
			}

			if (isRangeQuestion.value && Array.isArray(date)) {
				emit('update:values', [
					moment(date[0]).format(props.answerType.storageFormat),
					moment(date[1]).format(props.answerType.storageFormat),
				])
				return
			}

			emit('update:values', [
				moment(date).format(props.answerType.storageFormat),
			])
		}

		/**
		 * Determines if a given date should be disabled.
		 *
		 * @param date - The date to check.
		 * @return - Returns true if the date should be disabled, otherwise false.
		 */
		const disabledDates = (date: Date): boolean => {
			return Boolean(
				(dateMin.value && date < dateMin.value)
				|| (dateMax.value && date > dateMax.value),
			)
		}

		/**
		 * Determines if a given time should be disabled.
		 *
		 * @param time - The time to check.
		 * @return - Returns true if the time should be disabled, otherwise false.
		 */
		const disabledTimes = (time: Date): boolean => {
			return Boolean(
				(timeMin.value && time < timeMin.value)
				|| (timeMax.value && time > timeMax.value),
			)
		}

		return {
			...question,
			extraSettingsFormatter: {
				stringify: stringifyDate,
				parse: parseTimestampToDate,
			},

			svgClockLoader80,
			svgClockLoader20,
			svgEventIcon,
			svgTodayIcon,
			t,
			isRangeQuestion,
			datetimePickerPlaceholder,
			dateTimePickerType,
			time,
			dateMax,
			dateMin,
			dateRange,
			timeMax,
			timeMin,
			timeRange,
			validate,
			stringify,
			parse,
			onDateMaxChange,
			onDateMinChange,
			onDateRangeChange,
			onTimeMaxChange,
			onTimeMinChange,
			onTimeRangeChange,
			onValueChange,
			disabledDates,
			disabledTimes,
			stringifyDate,
			parseTimestampToDate,
		}
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
