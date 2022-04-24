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
	<Question v-bind.sync="$attrs"
		:text="text"
		:description="description"
		:is-required="isRequired"
		:edit.sync="edit"
		:read-only="readOnly"
		:max-string-lengths="maxStringLengths"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		@update:text="onTitleChange"
		@update:description="onDescriptionChange"
		@update:isRequired="onRequiredChange"
		@delete="onDelete">
		<div class="question__content">
			<textarea ref="textarea"
				:aria-label="t('forms', 'A long answer for the question “{text}”', { text })"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:required="isRequired"
				:value="values[0]"
				class="question__text"
				:maxlength="maxStringLengths.answerText"
				minlength="1"
				@input="onInput"
				@keypress="autoSizeText"
				@keydown.ctrl.enter="onKeydownCtrlEnter" />
		</div>
	</Question>
</template>

<script>
import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionLong',

	mixins: [QuestionMixin],

	data() {
		return {
			height: 1,
		}
	},

	computed: {
		submissionInputPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},
	},

	mounted() {
		this.autoSizeText()
	},

	methods: {
		onInput() {
			const textarea = this.$refs.textarea
			this.$emit('update:values', [textarea.value])
			this.autoSizeText()
		},
		autoSizeText() {
			const textarea = this.$refs.textarea
			textarea.style.cssText = 'height:auto; padding:0'
			textarea.style.cssText = `height: ${textarea.scrollHeight + 20}px`
		},
		onKeydownCtrlEnter(event) {
			this.$emit('keydown', event)
		},
	},
}
</script>

<style lang="scss" scoped>
.question__text {
	// make sure height calculations are correct
	box-sizing: content-box !important;
	width: 100%;
	min-width: 100%;
	max-width: 100%;
	min-height: 44px;
	margin: 0;
	padding: 6px 0;
	border: 0;
	border-bottom: 1px dotted var(--color-border-dark);
	border-radius: 0;
	resize: none;
	font-size: 14px;

	&:disabled {
		// Just overrides Server CSS-Styling for disabled inputs. -> Not Good??
		background-color: var(--color-main-background);
		color: var(--color-main-text);
	}
}

</style>
