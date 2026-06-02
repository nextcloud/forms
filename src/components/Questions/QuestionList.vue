<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Draggable
		:modelValue="modelValue"
		:animation="animation"
		target=".sort-target"
		direction="vertical"
		invertSwap
		handle=".question__drag-handle"
		@update:modelValue="$emit('update:modelValue', $event)"
		@start="onDragStart"
		@end="onDragEnd">
		<TransitionGroup
			tag="ul"
			:name="isDragging ? undefined : transitionName"
			class="sort-target">
			<component
				:is="getComponent(question)"
				v-for="(question, index) in modelValue"
				:key="question.id"
				:ref="registerQuestionRef(question)"
				v-bind="question"
				:canMoveDown="index < modelValue.length - 1"
				:canMoveUp="index > 0"
				:answerType="getAnswerType(question)"
				:index="baseIndex + index + 1"
				:maxStringLengths="maxStringLengths"
				:formId="formId ?? question.formId"
				@update:text="$emit('updateProperty', index, 'text', $event)"
				@update:description="
					$emit('updateProperty', index, 'description', $event)
				"
				@update:isRequired="
					$emit('updateProperty', index, 'isRequired', $event)
				"
				@update:name="$emit('updateProperty', index, 'name', $event)"
				@update:extraSettings="
					$emit('updateProperty', index, 'extraSettings', $event)
				"
				@update:options="$emit('updateProperty', index, 'options', $event)"
				@clone="$emit('clone', question, index)"
				@delete="$emit('delete', question)"
				@moveDown="$emit('moveDown', index)"
				@moveUp="$emit('moveUp', index)">
				<template v-if="showInsert && index < modelValue.length - 1" #insert>
					<div
						class="question-insert"
						:class="[
							{ 'is-open': insertMenuOpenedIndex === index },
							{ 'is-mobile': isMobile },
						]">
						<AddQuestionMenu
							:menuName="insertMenuName"
							:aria-label="
								t(
									'forms',
									'Insert question after question {index}',
									{ index: baseIndex + index + 1 },
								)
							"
							variant="tertiary"
							:position="index"
							:isLoadingQuestions="isLoadingQuestions"
							:answerTypesFilter="answerTypesFilter"
							:hasSubtypes="hasSubtypes"
							wide
							@update:open="
								(v) => (insertMenuOpenedIndex = v ? index : null)
							"
							@addQuestion="forwardAddQuestion" />
					</div>
				</template>
			</component>
		</TransitionGroup>
	</Draggable>
</template>

<script lang="ts">
import type { Component, ComponentPublicInstance, PropType } from 'vue'
import type { AnswerTypeConfig } from '../../models/AnswerTypes.ts'
import type { FormsQuestion } from '../../types/Entities.d.ts'

import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue'
import { defineComponent, nextTick, ref } from 'vue'
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import AddQuestionMenu from '../AddQuestionMenu.vue'

interface QuestionRefInstance extends ComponentPublicInstance {
	focus?: () => void
}

export default defineComponent({
	name: 'QuestionList',

	components: {
		AddQuestionMenu,
		Draggable,
	},

	props: {
		modelValue: {
			type: Array as PropType<FormsQuestion[]>,
			required: true,
		},

		getComponent: {
			type: Function as PropType<(question: FormsQuestion) => Component>,
			required: true,
		},

		getAnswerType: {
			type: Function as PropType<
				(question: FormsQuestion) => Partial<AnswerTypeConfig>
			>,

			required: true,
		},

		maxStringLengths: {
			type: Object as PropType<Record<string, number>>,
			required: true,
		},

		/** Offsets display numbering; emitted action indices remain list-local. */
		baseIndex: {
			type: Number,
			default: 0,
		},

		transitionName: {
			type: String,
			default: 'question-list',
		},

		animation: {
			type: Number,
			default: 300,
		},

		showInsert: {
			type: Boolean,
			default: false,
		},

		insertMenuName: {
			type: String,
			default: '',
		},

		answerTypesFilter: {
			type: Object as PropType<Record<string, AnswerTypeConfig>>,
			required: true,
		},

		hasSubtypes: {
			type: Function as PropType<(answer: AnswerTypeConfig) => boolean>,
			required: true,
		},

		isLoadingQuestions: {
			type: Boolean,
			default: false,
		},

		formId: {
			type: Number,
			default: null,
		},
	},

	emits: [
		'update:modelValue',
		'updateProperty',
		'clone',
		'delete',
		'moveDown',
		'moveUp',
		'addQuestion',
	],

	setup(props, { emit }) {
		const isDragging = ref(false)
		const insertMenuOpenedIndex = ref<number | null>(null)
		const questionRefsMap = new Map<number, QuestionRefInstance>()

		const registerQuestionRef = (
			question: FormsQuestion,
		): ((el: Element | ComponentPublicInstance | null) => void) => {
			return (el) => {
				if (el) {
					questionRefsMap.set(question.id, el as QuestionRefInstance)
				} else {
					questionRefsMap.delete(question.id)
				}
			}
		}

		const focusQuestion = (id: number): void => {
			questionRefsMap.get(id)?.focus?.()
		}

		const onDragStart = (): void => {
			isDragging.value = true
		}

		const onDragEnd = (): void => {
			nextTick(() => {
				isDragging.value = false
			})
		}

		/**
		 * @param type The question type.
		 * @param subtype The question subtype.
		 * @param position The zero-based index of the question to insert after.
		 */
		const forwardAddQuestion = (
			type: string,
			subtype: string | null,
			position: number | null,
		): void => {
			emit('addQuestion', type, subtype, position)
		}

		return {
			isDragging,
			insertMenuOpenedIndex,
			registerQuestionRef,
			focusQuestion,
			onDragStart,
			onDragEnd,
			forwardAddQuestion,
			isMobile: useIsMobile(),
			t,
		}
	},
})
</script>

<style lang="scss" scoped>
.sort-target {
	list-style: none;
	padding: 0;
	margin: 0;
}

.question-list-move,
.question-list-enter-active,
.question-list-leave-active {
	transition: all var(--animation-slow) ease;
}

.question-list-enter-from,
.question-list-leave-to {
	opacity: 0;
	transform: translateX(var(--clickable-area-large));
}

.question-list-leave-active {
	position: absolute;
}

.question-insert {
	position: relative;
	margin-block-end: -34px;
	inset-block-end: -16px;
	margin-inline-start: -12px;
	width: calc(100% - var(--default-clickable-area));
	display: flex;
	justify-content: center;
	opacity: 0;
	transition: opacity 0.12s ease;
}

.question-insert.is-mobile {
	opacity: 0.3;
}

.question:hover > .question-insert,
.question-insert:focus-within,
.question-insert.is-open {
	opacity: 1;
}
</style>
