<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
			<input ref="input"
				:aria-label="t('forms', 'A short answer for the question “{text}”', { text })"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:required="mandatory"
				:value="values[0]"
				class="question__input"
				:maxlength="maxStringLengths.answerText"
				minlength="1"
				type="text"
				@input="onInput"
				@keydown.enter.exact.prevent="onKeydownEnter">
		</div>
	</Question>
</template>

<script>
import QuestionMixin from '../../mixins/QuestionMixin'

export default {
	name: 'QuestionShort',

	mixins: [QuestionMixin],

	computed: {
		submissionInputPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},
	},

	methods: {
		onInput() {
			const input = this.$refs.input
			this.$emit('update:values', [input.value])
		},

		getType() {
			return 'text'
		}
	},
}
</script>

<style lang="scss" scoped>
// Using type to have a higher order than the input styling of server
.question__input {
	width: 100%;
	min-height: 44px;
	margin: 0;
	padding: 6px 0;
	border: 0;
	border-bottom: 1px dotted var(--color-border-dark);
	border-radius: 0;

	&:disabled {
		// Just overrides Server CSS-Styling for disabled inputs. -> Not Good??
		background-color: var(--color-main-background);
		color: var(--color-main-text);
	}
}

</style>
