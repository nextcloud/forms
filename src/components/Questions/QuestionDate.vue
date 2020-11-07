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
				:type="DatetimePickerType"
				:show-second="false"
				:disabled="!readOnly"
				:placeholder="DatetimePickerPlaceholder" />
		</div>
	</Question>
</template>

<script>
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
		}
	},

	computed: {
		// Allow picking time or not, depending on variable in answerType.
		DatetimePickerType() {
			return this.answerType.time ? 'datetime' : 'date'
		},

		DatetimePickerPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},
	},

	watch: {
		time(value) {
			this.$emit('update:values', [value])
		},
	},
}
</script>
