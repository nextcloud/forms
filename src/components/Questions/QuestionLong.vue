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
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		v-on="commonListeners">
		<div class="question__content">
			<textarea
				ref="textarea"
				:aria-label="
					t('forms', 'A long answer for the question “{text}”', { text })
				"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:required="isRequired"
				:value="values[0]"
				dir="auto"
				class="question__text"
				:maxlength="maxStringLengths.answerText"
				minlength="1"
				:name="name || undefined"
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
			textarea.style.cssText = `height: ${textarea.scrollHeight + 28}px`
		},
		onKeydownCtrlEnter(event) {
			this.$emit('keydown', event)
		},
	},
}
</script>

<style lang="scss" scoped>
.question__text {
	width: 100%;
	resize: none;

	&:disabled {
		// Just overrides Server CSS-Styling for disabled inputs. -> Not Good??
		background-color: var(--color-main-background);
		color: var(--color-main-text);
		width: calc(100% - 32px) !important;
		margin-inline-start: -12px;
	}
}
</style>
