<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="active-subquestions" :class="{ 'is-empty': !subQuestions.length }">
		<!-- Announce follow-up questions that appear or disappear on a changed answer -->
		<div class="hidden-visually" aria-live="polite">
			{{ status }}
		</div>
		<TransitionGroup tag="ul" name="branch-list">
			<component
				:is="answerTypes[subQuestion.type].component"
				v-for="(subQuestion, subIndex) in subQuestions"
				:key="subQuestion.id"
				ref="subQuestionRefs"
				v-bind="subQuestion"
				:formId="formId"
				:index="index"
				:subIndex="subIndex + 1"
				:maxStringLengths="maxStringLengths"
				:answerType="answerTypes[subQuestion.type]"
				readOnly
				:values="answers[subQuestion.id] ?? NO_VALUES"
				@update:values="$emit('update:values', subQuestion, $event)"
				@keydown="$emit('keydown', $event)" />
		</TransitionGroup>
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsQuestion } from '../../types/Entities.d.ts'

import { n, t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import answerTypes from '../../models/AnswerTypes.ts'

// Values of all unanswered subquestions, the same array does not render them again on every answer
const NO_VALUES = Object.freeze([])

export default defineComponent({
	name: 'ActiveSubQuestions',

	props: {
		/** The subquestions shown for the current answer of their parent question */
		subQuestions: {
			type: Array as PropType<FormsQuestion[]>,
			required: true,
		},

		/** Number of the parent question in the form */
		index: {
			type: Number,
			required: true,
		},

		formId: {
			type: Number,
			default: null,
		},

		/** Answers of all questions, keyed by question id */
		answers: {
			type: Object as PropType<Record<number, unknown>>,
			required: true,
		},

		maxStringLengths: {
			type: Object as PropType<Record<string, number>>,
			required: true,
		},
	},

	emits: ['update:values', 'keydown'],

	setup() {
		return { answerTypes, NO_VALUES }
	},

	data() {
		return {
			status: '',
		}
	},

	watch: {
		subQuestions(newQuestions: FormsQuestion[], oldQuestions: FormsQuestion[]) {
			const ids = (questions: FormsQuestion[]) =>
				questions.map((question) => question.id).join()
			if (ids(newQuestions) === ids(oldQuestions)) {
				return
			}

			const status =
				newQuestions.length > 0
					? n(
							'forms',
							'%n follow-up question shown',
							'%n follow-up questions shown',
							newQuestions.length,
						)
					: t('forms', 'Follow-up questions removed')
			// Screen readers only announce a changed text, other questions with the same count need a new one
			this.status = ''
			this.$nextTick(() => {
				this.status = status
			})
		},
	},

	methods: {
		async validate(): Promise<boolean> {
			const subQuestionRefs = (this.$refs.subQuestionRefs ?? []) as Array<{
				validate?: () => Promise<boolean>
			}>
			for (const subQuestion of subQuestionRefs) {
				if (subQuestion.validate && !(await subQuestion.validate())) {
					return false
				}
			}
			return true
		},
	},
})
</script>

<style lang="scss" scoped>
.active-subquestions {
	margin-top: 16px;
	padding-left: 16px;
	border-left: 3px solid var(--color-primary-element);

	// Phones have little width to spare, the border still shows the nesting
	@media (max-width: 768px) {
		padding-left: 8px;
	}

	// The live region stays rendered, so removed questions are announced
	&.is-empty {
		margin-top: 0;
		padding: 0;
		border: none;
	}
}

.branch-list-move,
.branch-list-enter-active,
.branch-list-leave-active {
	transition: all var(--animation-slow) ease;
}

.branch-list-enter-from,
.branch-list-leave-to {
	opacity: 0;
	transform: translateX(var(--clickable-area-large));
}

.branch-list-leave-active {
	position: absolute;
}
</style>
