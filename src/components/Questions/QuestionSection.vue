<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		ref="rootElement"
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners" />
</template>

<script lang="ts">
import { defineComponent, ref } from 'vue'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'

export default defineComponent({
	name: 'QuestionSection',

	components: {
		Question,
	},

	props: QUESTION_PROPS,
	emits: QUESTION_EMITS,

	setup(props, { emit }) {
		const rootElement = ref<{ $el: HTMLElement } | null>(null)
		const question = useQuestion(props, { emit, rootElement })

		/**
		 * Sections cannot be answered, they are always valid
		 */
		const validate = async (): Promise<boolean> => true

		return {
			...question,
			rootElement,
			validate,
		}
	},
})
</script>
