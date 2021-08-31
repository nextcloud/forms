<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author Simon Vieille <contact@deblan.fr>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Question
		v-bind.sync="$attrs"
		:text="text"
		:is-required="isRequired"
		:edit.sync="edit"
		:read-only="readOnly"
		:max-question-length="maxStringLengths.questionText"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		@update:text="onTitleChange"
		@update:isRequired="onRequiredChange"
		@delete="onDelete">
		<div class="question__content">
			<DatetimePicker
				v-model="time"
				value-type="format"
				:disabled="!readOnly"
				:formatter="formatter"
				:placeholder="datetimePickerPlaceholder"
				:show-second="false"
				:type="datetimePickerType"
				:input-attr="inputAttr"
				@change="onValueChange" />
		</div>
	</Question>
</template>

<script>
import moment from '@nextcloud/moment'

import QuestionMixin from '../../mixins/QuestionMixin'
import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'

export default {
	name: 'QuestionDate',

	components: {
		DatetimePicker,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			time: null,
			formatter: {
				stringify: this.stringify,
				parse: this.parse,
			},
		}
	},

	computed: {
		// Allow picking time or not, depending on variable in answerType.
		datetimePickerType() {
			if (this.answerType.includeTime) {
				return 'datetime'
			}
			if (this.answerType.onlyTime) {
				return 'time'
			}
			return 'date'
		},

		datetimePickerPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		/**
		 * Calculating the format, that moment should use. With or without time.
		 *
		 * @return {string}
		 */
		getMomentFormat() {
			if (this.datetimePickerType === 'datetime') {
				return 'LLL'
			}
			if (this.datetimePickerType === 'time') {
				return 'LT'
			}
			return 'LL'
		},

		/**
		 * Calculating the format, that moment should use for storing the values to the Database
		 *
		 * @return {string}
		 */
		getStorageFormat() {
			if (this.datetimePickerType === 'datetime') {
				return 'YYYY-MM-DD HH:mm'
			}
			if (this.datetimePickerType === 'time') {
				return 'HH:mm'
			}
			return 'YYYY-MM-DD'
		},

		/**
		 * All non-exposed props onto datepicker input-element.
		 *
		 * @return {object}
		 */
		inputAttr() {
			return {
				required: this.isRequired,
			}
		},
	},

	methods: {
		/**
		 * DateTimepicker show date-text
		 * Format depends on component-type date/datetime
		 *
		 * @param {Date} date the selected datepicker Date
		 * @return {string}
		 */
		stringify(date) {
			return moment(date).format(this.getMomentFormat)
		},
		/**
		 * Reinterpret the stringified date
		 *
		 * @param {string} dateString Stringified date
		 * @return {Date}
		 */
		parse(dateString) {
			return moment(dateString, this.getMomentFormat).toDate()
		},

		/**
		 * Store Value
		 *
		 * @param {string} dateString The parsed string to store
		 */
		onValueChange(dateString) {
			this.$emit('update:values', [moment(this.parse(dateString)).format(this.getStorageFormat)])
		},
	},
}
</script>

<style lang="scss" scoped>
.mx-datepicker {
	// Enlarging a bit (originally 210px) to have enough space for placeholder
	width: 250px;
}
</style>
