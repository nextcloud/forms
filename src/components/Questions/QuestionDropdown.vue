<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license AGPL-3.0-or-later
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
	<Question v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox :checked="extraSettings?.shuffleOptions"
				@update:checked="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
		</template>
		<NcSelect v-if="readOnly"
			v-model="selectedOption"
			:name="name || undefined"
			:placeholder="selectOptionPlaceholder"
			:multiple="isMultiple"
			:required="isRequired"
			:options="sortedOptions"
			:searchable="false"
			label="text"
			@input="onInput" />

		<TransitionList v-if="!readOnly" class="question__content">
			<!-- Answer text input edit -->
			<AnswerInput v-for="(answer, index) in sortedOptions"
				:key="answer.local ? 'option-local' : answer.id"
				ref="input"
				is-dropdown
				:allow-reorder="!extraSettings?.shuffleOptions"
				:answer="answer"
				:index="index"
				:max-index="options.length - 1"
				:is-unique="!isMultiple"
				:max-option-length="maxStringLengths.optionText"
				@delete="deleteOption"
				@focus-next="focusNextInput"
				@move-up="onOptionMoveUp(index)"
				@move-down="onOptionMoveDown(index)"
				@tabbed-out="checkValidOption"
				@update:answer="updateAnswer(index, $event)" />
		</TransitionList>
	</Question>
</template>

<script>
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import AnswerInput from './AnswerInput.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import QuestionMultipleMixin from '../../mixins/QuestionMultipleMixin.ts'
import TransitionList from '../TransitionList.vue'

export default {
	name: 'QuestionDropdown',

	components: {
		AnswerInput,
		NcActionCheckbox,
		NcSelect,
		TransitionList,
	},

	mixins: [QuestionMixin, QuestionMultipleMixin],

	data() {
		return {
			selectedOption: null,
			inputValue: '',
		}
	},

	computed: {
		selectOptionPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		contentValid() {
			return this.answerType.validate(this)
		},

		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim().length === 0
		},

		isMultiple() {
			// This can be extended if we want to include support for <select multiple>
			return false
		},

		hasNoAnswer() {
			return this.options.length === 0
		},

		areNoneChecked() {
			return this.values.length === 0
		},

		shiftDragHandle() {
			return !this.readOnly && this.options.length !== 0
		},
	},

	mounted() {
		// Init selected options from values prop
		if (this.values) {
			const selected = this.values.map(id => this.options.find(option => option.id === id))
			this.selectedOption = this.isMultiple ? selected : selected[0]
		}
	},

	methods: {
		onInput(option) {
			if (Array.isArray(option)) {
				this.$emit('update:values', [...new Set(option.map((opt) => opt.id))])
				return
			}

			// Simple select
			this.$emit('update:values', option ? [option.id] : [])
		},
	},
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
}

.question__item {
	position: relative;
	display: inline-flex;
	min-height: 44px;

	.question__input {
		width: 100%;
		position: relative;
		inset-inline-start: -12px;
		margin-inline-end: 32px !important;
	}
}
</style>
