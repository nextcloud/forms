<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		ref="rootElement"
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		:infoMessage="hasError ? undefined : (limitHint ?? undefined)"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:modelValue="showRules"
				@update:modelValue="
					(shown) => toggleRules(shown, onExtraSettingsChange)
				">
				{{ t('forms', 'Answer rules') }}
			</NcActionCheckbox>
		</template>
		<div class="question__content">
			<textarea
				ref="textarea"
				:aria-labelledby="readOnly ? titleId : undefined"
				:aria-describedby="describedBy"
				:aria-errormessage="hasError ? errorId : undefined"
				:aria-invalid="hasError ? 'true' : undefined"
				:placeholder="submissionInputPlaceholder"
				:disabled="!readOnly"
				:required="isRequired"
				:value="textareaValue"
				dir="auto"
				class="question__text"
				:minlength="extraSettings.lengthMin || 1"
				:name="name || undefined"
				@invalid.prevent="validate"
				@input="onInput"
				@compositionend="onInput"
				@keypress="autoSizeText" />
			<p
				v-if="readOnly && hasLengthLimit"
				class="question__counter"
				aria-hidden="true">
				{{ lengthCounter }}
			</p>
		</div>
		<AnswerRules
			v-if="!readOnly && showRules"
			:kinds="['length']"
			:extraSettings="extraSettings"
			:answerLengthMax="answerLengthMax"
			@change="onExtraSettingsChange" />
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import type { AnswerLimitSettings } from '../../composables/useAnswerLimits.ts'

import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, defineComponent, nextTick, ref, watch } from 'vue'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import AnswerRules from './AnswerRules.vue'
import Question from './Question.vue'
import { useAnswerLimits } from '../../composables/useAnswerLimits.ts'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'
import { INPUT_DEBOUNCE_MS } from '../../models/Constants.ts'

export default defineComponent({
	name: 'QuestionLong',

	components: {
		AnswerRules,
		NcActionCheckbox,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:values'],

	setup(props, { emit }) {
		const textarea = ref<HTMLTextAreaElement | null>(null)
		const rootElement = ref<{ $el?: HTMLElement } | null>(null)
		const values = computed(() => {
			return props.values as Array<
				string | number | readonly string[] | null | undefined
			>
		})
		const extraSettings = computed(
			() => (props.extraSettings as AnswerLimitSettings | undefined) ?? {},
		)
		const answer = computed(() => String(values.value[0] ?? ''))
		const answerLengthMax = (props.maxStringLengths as Record<string, number>)
			.answerText
		const limits = useAnswerLimits(extraSettings, answer, answerLengthMax)
		const question = useQuestion(props, {
			emit,
			infoMessage: limits.limitHint,
			rootElement,
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

			const limitError = limits.limitError(answer.value)
			if (limitError) {
				question.errorMessage.value = limitError
				return false
			}

			question.errorMessage.value = null
			return true
		}

		const debounceValidate = debounce(async () => {
			await validate()
		}, INPUT_DEBOUNCE_MS)

		const onInput = (event: Event): void => {
			const field = textarea.value
			if (!field) {
				return
			}
			if ((event as InputEvent).isComposing) {
				return
			}
			limits.fitLength(field)
			emit('update:values', [field.value])
			void debounceValidate()
		}

		return {
			...question,
			...limits,
			answerLengthMax,
			t,
			extraSettings,
			rootElement,
			textarea,
			submissionInputPlaceholder,
			textareaValue,
			autoSizeText,
			validate,
			onInput,
		}
	},
})
</script>

<style lang="scss" scoped>
.question__counter {
	color: var(--color-text-maxcontrast);
	text-align: end;
}

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
