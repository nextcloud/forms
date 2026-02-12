<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		v-on="commonListeners">
		<div class="question__content">
			<textarea
				ref="textarea"
				:aria-label="
					t('forms', 'A long answer for the question “{text}”', {
						text,
					})
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
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionLong',

	components: {
		Question,
	},

	mixins: [QuestionMixin],
	emits: ['update:values', 'keydown'],

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

	watch: {
		values: {
			handler() {
				this.$nextTick(() => {
					this.autoSizeText()
				})
			},

			immediate: true,
		},
	},

	methods: {
		onInput() {
			const textarea = this.$refs.textarea
			this.$emit('update:values', [textarea.value])
		},

		autoSizeText() {
			const textarea = this.$refs.textarea
			if (!textarea) {
				return
			}
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
		width: calc(100% - var(--default-clickable-area)) !important;
		margin-inline-start: -12px;
	}
}
</style>
