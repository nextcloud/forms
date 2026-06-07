<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<Draggable
		v-model="localQuestions"
		:animation="animation"
		target=".sort-target"
		direction="vertical"
		invertSwap
		handle=".question__drag-handle"
		@change="onOrderChange"
		@start="onDragStart"
		@end="onDragEnd">
		<TransitionGroup
			tag="ul"
			:name="isDragging ? undefined : transitionName"
			class="sort-target">
			<component
				:is="getComponent(question)"
				v-for="(question, index) in localQuestions"
				:key="question.id"
				:ref="(el) => setQuestionRef(el, question)"
				v-bind="question"
				:canMoveDown="index < localQuestions.length - 1"
				:canMoveUp="index > 0"
				:answerType="getAnswerType(question)"
				:index="baseIndex + index + 1"
				:maxStringLengths="maxStringLengths"
				:formId="formId"
				@update:text="(val) => $emit('updateProperty', index, 'text', val)"
				@update:description="
					(val) => $emit('updateProperty', index, 'description', val)
				"
				@update:isRequired="
					(val) => $emit('updateProperty', index, 'isRequired', val)
				"
				@update:name="(val) => $emit('updateProperty', index, 'name', val)"
				@update:extraSettings="
					(val) => $emit('updateProperty', index, 'extraSettings', val)
				"
				@update:options="
					(val) => $emit('updateProperty', index, 'options', val)
				"
				@clone="onClone(question, index)"
				@delete="onDelete(question)"
				@moveDown="onMoveDown(index)"
				@moveUp="onMoveUp(index)">
				<template
					v-if="showInsert && index < localQuestions.length - 1"
					#insert>
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
							:position="baseIndex + index"
							:isLoadingQuestions="isLoadingQuestions"
							:answerTypesFilter="answerTypesFilter"
							:hasSubtypes="hasSubtypes"
							@update:open="
								(v) =>
									$emit(
										'update:insertMenuOpenedIndex',
										v ? index : null,
									)
							"
							@addQuestion="forwardAddQuestion" />
					</div>
				</template>
			</component>
		</TransitionGroup>
	</Draggable>
</template>

<script>
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import AddQuestionMenu from '../AddQuestionMenu.vue'

export default {
	name: 'QuestionList',

	components: {
		AddQuestionMenu,
		Draggable,
	},

	props: {
		modelValue: {
			type: Array,
			required: true,
		},

		getComponent: {
			type: Function,
			required: true,
		},

		getAnswerType: {
			type: Function,
			required: true,
		},

		maxStringLengths: {
			type: Object,
			required: true,
		},

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
			type: Object,
			default: null,
		},

		hasSubtypes: {
			type: Function,
			default: null,
		},

		isLoadingQuestions: {
			type: Boolean,
			default: false,
		},

		isMobile: {
			type: Boolean,
			default: false,
		},

		insertMenuOpenedIndex: {
			type: Number,
			default: null,
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
		'orderChange',
		'dragStart',
		'dragEnd',
		'update:insertMenuOpenedIndex',
		'addQuestion',
	],

	data() {
		return {
			isDragging: false,
			questionRefsMap: {},
		}
	},

	computed: {
		localQuestions: {
			get() {
				return this.modelValue
			},

			set(val) {
				this.$emit('update:modelValue', val)
			},
		},
	},

	methods: {
		setQuestionRef(el, question) {
			if (el) {
				this.questionRefsMap[question.id] = el
			} else {
				delete this.questionRefsMap[question.id]
			}
		},

		focusQuestion(id) {
			this.questionRefsMap[id]?.focus()
		},

		onClone(question, index) {
			this.$emit('clone', question, index)
		},

		onDelete(question) {
			this.$emit('delete', question)
		},

		onMoveDown(index) {
			this.$emit('moveDown', index)
		},

		onMoveUp(index) {
			this.$emit('moveUp', index)
		},

		onDragStart() {
			this.isDragging = true
			this.$emit('dragStart')
		},

		onDragEnd() {
			this.$nextTick(() => {
				this.isDragging = false
			})
			this.$emit('dragEnd')
		},

		onOrderChange() {
			this.$emit('orderChange')
		},

		forwardAddQuestion(type, subtype, position) {
			this.$emit('addQuestion', type, subtype, position)
		},
	},
}
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
