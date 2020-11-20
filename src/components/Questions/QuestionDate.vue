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
		:mandatory="mandatory"
		:edit.sync="edit"
		:read-only="readOnly"
		:max-question-length="maxStringLengths.questionText"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		@update:text="onTitleChange"
		@update:mandatory="onMandatoryChange"
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
			return this.answerType.includeTime ? 'datetime' : 'date'
		},

		datetimePickerPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		/**
		 * Calculating the format, that moment should use. With or without time.
		 * @returns {String}
		 */
		getMomentFormat() {
			if (this.datetimePickerType === 'datetime') {
				return 'LLL'
			}
			return 'LL'
		},
	},

	methods: {
		/**
		 * DateTimepicker show date-text
		 * Format depends on component-type date/datetime
		 * @param {Date} date the selected datepicker Date
		 * @returns {String}
		 */
		stringify(date) {
			return moment(date).format(this.getMomentFormat)
		},
		/**
		 * Reinterpret the stringified date
		 * @param {String} dateString Stringified date
		 * @returns {Date}
		 */
		parse(dateString) {
			return moment(dateString, this.getMomentFormat).toDate()
		},

		/**
		 * Store Value
		 * @param {String} dateString The parsed string to store
		 */
		onValueChange(dateString) {
			this.$emit('update:values', [dateString])
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
