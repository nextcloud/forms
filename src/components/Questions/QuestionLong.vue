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
	<Question :title="title" :edit.sync="edit" @update:title="onTitleChange">
		<div class="question__content">
			<!-- TODO: properly choose max length -->
			<textarea ref="textarea"
				:aria-label="t('forms', 'A long answer for the question “{title}”', { title })"
				:placeholder="t('forms', 'Long answer text')"
				:readonly="edit"
				:value="values[0]"
				class="question__text"
				maxlength="1024"
				minlength="1"
				@input="onInput"
				@keydown="autoSizeText" />
		</div>
	</Question>
</template>

<script>
import QuestionMixin from '../../mixins/QuestionMixin'

export default {
	name: 'QuestionLong',

	mixins: [QuestionMixin],

	data() {
		return {
			height: 1,
		}
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
	},
}
</script>

<style lang="scss">
// Using type to have a higher order than the input styling of server
.question__text {
	// make sure height calculations are correct
	box-sizing: content-box !important;
	width: 100%;
	min-width: 100%;
	max-width: 100%;
	min-height: 44px;
	max-height: 10rem;
	margin: 0;
	padding: 6px 0;
	border: 0;
	border-bottom: 1px dotted var(--color-border-dark);
	border-radius: 0;
}

</style>
