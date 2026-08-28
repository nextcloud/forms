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
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, ref, watch } from 'vue'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

export default defineComponent({
	name: 'QuestionLong',

	components: {
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values', 'keydown'],

	setup(props, { emit }) {
		const textarea = ref<HTMLTextAreaElement | null>(null)
		const question = useQuestion(props, { emit })
		const values = computed(() => {
			return props.values as Array<
				string | number | readonly string[] | null | undefined
			>
		})

		const submissionInputPlaceholder = computed(() => {
			if (props.readOnly) {
				return props.answerType.submitPlaceholder
			}
			return props.answerType.createPlaceholder
		})

		const textareaValue = computed(() => {
			return (values.value[0] ?? null) as
				string | number | readonly string[] | null | undefined
		})

		const autoSizeText = (): void => {
			const field = textarea.value
			if (!field) {
				return
			}
			field.style.cssText = 'height:auto; padding:0'
			field.style.cssText = `height: ${field.scrollHeight + 28}px`
		}

		watch(
			() => values.value,
			() => {
				nextTick(() => {
					autoSizeText()
				})
			},
			{ immediate: true },
		)

		const validate = async (): Promise<boolean> => {
			if (
				props.isRequired
				&& (values.value.length === 0 || values.value[0] === '')
			) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			question.errorMessage.value = null
			return true
		}

		const onInput = (): void => {
			const field = textarea.value
			if (!field) {
				return
			}
			emit('update:values', [field.value])
		}

		const onKeydownCtrlEnter = (event: KeyboardEvent): void => {
			emit('keydown', event)
		}

		return {
			...question,
			textarea,
			submissionInputPlaceholder,
			textareaValue,
			autoSizeText,
			validate,
			onInput,
			onKeydownCtrlEnter,
		}
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
