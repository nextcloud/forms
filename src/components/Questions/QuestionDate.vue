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
			<NcDateTimePicker
				:value="time"
				:disabled="!readOnly"
				:formatter="formatter"
				:placeholder="datetimePickerPlaceholder"
				:show-second="false"
				:type="answerType.pickerType"
				:input-attr="inputAttr"
				@change="onValueChange" />
		</div>
	</Question>
</template>

<script>
import moment from '@nextcloud/moment'

import QuestionMixin from '../../mixins/QuestionMixin.js'
import NcDateTimePicker from '@nextcloud/vue/components/NcDateTimePicker'

export default {
	name: 'QuestionDate',

	components: {
		NcDateTimePicker,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			formatter: {
				stringify: this.stringify,
				parse: this.parse,
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
