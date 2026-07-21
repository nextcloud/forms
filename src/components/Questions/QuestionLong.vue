<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<div class="question__content">
			<textarea
				ref="textarea"
				:aria-labelledby="titleId"
				:aria-describedby="description ? descriptionId : undefined"
				:aria-errormessage="hasError ? errorId : undefined"
				:aria-invalid="hasError ? 'true' : undefined"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:required="isRequired"
				:value="textareaValue"
				dir="auto"
				class="question__text"
				:maxlength="maxStringLengths.answerText"
				minlength="1"
				:name="name || undefined"
				@invalid.prevent="validate"
				@input="onInput"
				@keypress="autoSizeText"
				@keydown.ctrl.enter="onKeydownCtrlEnter" />
		</div>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.ts'

export default defineComponent({
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

		textareaValue(): string | number | readonly string[] | null | undefined {
			return this.values[0] as
				string | number | readonly string[] | null | undefined
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
		async validate(): Promise<boolean> {
			if (
				this.isRequired
				&& (this.values.length === 0 || this.values[0] === '')
			) {
				this.errorMessage = t('forms', 'You must answer this question')
				return false
			}

			this.errorMessage = null
			return true
		},

		onInput(): void {
			const textarea = this.$refs.textarea as HTMLTextAreaElement
			this.$emit('update:values', [textarea.value])
		},

		autoSizeText(): void {
			const textarea = this.$refs.textarea as HTMLTextAreaElement | undefined
			if (!textarea) {
				return
			}
			textarea.style.cssText = 'height:auto; padding:0'
			textarea.style.cssText = `height: ${textarea.scrollHeight + 28}px`
		},

		onKeydownCtrlEnter(event: KeyboardEvent): void {
			this.$emit('keydown', event)
		},
	},
})
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
