<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton
		v-if="branches.length === 0"
		ref="addBranchButton"
		class="question-branches__add-first"
		variant="tertiary"
		@click="addBranch">
		<template #icon>
			<NcIconSvgWrapper :svg="IconPlus" />
		</template>
		{{ t('forms', 'Add follow-up questions') }}
	</NcButton>
	<div v-else class="question-branches">
		<h4 class="question-branches__title">
			{{
				text
					? t(
							'forms',
							'Follow-up questions of "{question}"',
							{ question: text },
							{ escape: false, sanitize: false },
						)
					: t('forms', 'Follow-up questions')
			}}
		</h4>
		<p class="question-branches__description">
			{{
				t(
					'forms',
					'Define which follow-up questions appear based on the answer above.',
				)
			}}
		</p>

		<TransitionGroup tag="div" name="branch-list">
			<div
				v-for="(branch, branchIndex) in branches"
				:key="branch.id"
				class="branch"
				role="group"
				:aria-labelledby="`${branchIdPrefix}${branch.id}`">
				<div class="branch__header">
					<span
						:id="`${branchIdPrefix}${branch.id}`"
						class="branch__label">
						{{ getBranchLabel(branch, branchIndex) }}
					</span>
					<NcButton
						variant="tertiary"
						:aria-label="
							t(
								'forms',
								'Delete branch {label}',
								{ label: getBranchLabel(branch, branchIndex) },
								{ escape: false, sanitize: false },
							)
						"
						@click="deleteBranch(branch.id)">
						<template #icon>
							<NcIconSvgWrapper :svg="IconDelete" />
						</template>
					</NcButton>
				</div>

				<div class="branch__conditions">
					<BranchConditionEditor
						:branch="branch"
						:triggerType="type"
						:options="options"
						@update:branch="onBranchUpdate(branchIndex, $event)" />
				</div>

				<div class="branch__subquestions">
					<QuestionList
						ref="subQuestionLists"
						:modelValue="branch.subQuestions"
						:getComponent="getSubQuestionComponent"
						:getAnswerType="getSubQuestionAnswerType"
						:maxStringLengths="maxStringLengths"
						:baseIndex="index"
						isSubQuestionList
						:subIndexOffset="subIndexOffsets[branchIndex]"
						:animation="200"
						:formId="formId"
						showInsert
						:answerTypesFilter="subQuestionAnswerTypes"
						:hasSubtypes="hasSubtypes"
						:isLoadingQuestions="isLoadingQuestions"
						@updateProperty="
							(idx, prop, val) =>
								updateSubQuestion(
									branch.id,
									branch.subQuestions[idx].id,
									prop,
									val,
								)
						"
						@clone="
							(question) => cloneSubQuestion(branch.id, question.id)
						"
						@delete="
							(question) => deleteSubQuestion(branch.id, question.id)
						"
						@moveDown="(idx) => onMoveDown(idx, branchIndex)"
						@moveUp="(idx) => onMoveUp(idx, branchIndex)"
						@update:modelValue="
							(questions) =>
								onQuestionOrderChange(branchIndex, questions)
						"
						@addQuestion="
							(subType, subtype, position) =>
								addSubQuestion(branch.id, subType, position)
						" />

					<NcActions
						:aria-label="
							t(
								'forms',
								'Add subquestion to branch {label}',
								{ label: getBranchLabel(branch, branchIndex) },
								{ escape: false, sanitize: false },
							)
						">
						<template #icon>
							<NcIconSvgWrapper :svg="IconPlus" />
						</template>
						<NcActionButton
							v-for="(subQuestionType, key) in subQuestionAnswerTypes"
							:key="key"
							closeAfterClick
							@click="addSubQuestion(branch.id, key)">
							<template #icon>
								<NcIconSvgWrapper :svg="subQuestionType.icon" />
							</template>
							{{ subQuestionType.label }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</TransitionGroup>
		<NcButton ref="addBranchButton" variant="secondary" @click="addBranch">
			<template #icon>
				<NcIconSvgWrapper :svg="IconPlus" />
			</template>
			{{ t('forms', 'Add branch') }}
		</NcButton>
	</div>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { AnswerTypeConfig } from '../../models/AnswerTypes.ts'
import type {
	FormsConditionalBranch,
	FormsOption,
	FormsQuestion,
	FormsQuestionExtraSettings,
} from '../../types/Entities.d.ts'

import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { defineComponent } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import QuestionList from '../QuestionList.vue'
import BranchConditionEditor from './BranchConditionEditor.vue'
import answerTypes from '../../models/AnswerTypes.ts'
import { INPUT_DEBOUNCE_MS } from '../../models/Constants.ts'
import { persistedSettings } from '../../utils/Branches.ts'
import logger from '../../utils/Logger.ts'
import OcsResponse2Data from '../../utils/OcsResponse2Data.ts'

// Subquestions can be of every type, but get no branches of their own
const subQuestionAnswerTypes = Object.fromEntries(
	[
		'short',
		'long',
		'multiple',
		'multiple_unique',
		'dropdown',
		'date',
		'time',
		'linearscale',
		'color',
		'file',
		'ranking',
	].map((type) => [type, answerTypes[type]]),
)

export default defineComponent({
	name: 'QuestionBranches',

	components: {
		BranchConditionEditor,
		NcActionButton,
		NcActions,
		NcButton,
		NcIconSvgWrapper,
		QuestionList,
	},

	props: {
		id: {
			type: Number,
			required: true,
		},

		formId: {
			type: Number,
			required: true,
		},

		type: {
			type: String,
			required: true,
		},

		text: {
			type: String,
			default: '',
		},

		/** Number of the question in the form */
		index: {
			type: Number,
			required: true,
		},

		options: {
			type: Array as PropType<FormsOption[]>,
			default: () => [],
		},

		extraSettings: {
			type: Object as PropType<FormsQuestionExtraSettings>,
			default: () => ({}),
		},

		maxStringLengths: {
			type: Object as PropType<Record<string, number>>,
			required: true,
		},
	},

	emits: ['update:extraSettings'],

	setup(props) {
		// Saves the settings as they are by then, so a change made meanwhile is not lost
		let saving: Promise<void> = Promise.resolve()
		const save = async (): Promise<void> => {
			try {
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{ id: props.formId, questionId: props.id },
					),
					{
						keyValuePairs: {
							extraSettings: persistedSettings(props.extraSettings),
						},
					},
				)
				emit('forms:last-updated:set', props.formId)
			} catch (error) {
				logger.error('Error while saving question', { error })
				showError(t('forms', 'Error while saving question'))
			}
		}
		const saveBranches = debounce(() => {
			saving = save()
		}, INPUT_DEBOUNCE_MS)

		/**
		 * Save pending changes right away, e.g. a new branch before adding a subquestion to it
		 */
		const flushBranches = async (): Promise<void> => {
			saveBranches.flush()
			await saving
		}

		return {
			t,
			IconPlus,
			IconDelete,
			subQuestionAnswerTypes,
			saveBranches,
			flushBranches,
		}
	},

	data() {
		return {
			isLoadingQuestions: false,
		}
	},

	computed: {
		branches(): FormsConditionalBranch[] {
			return this.extraSettings?.branches ?? []
		},

		/**
		 * Subquestions are numbered across the branches, so each branch starts after the previous ones
		 */
		subIndexOffsets(): number[] {
			let offset = 0
			return this.branches.map((branch) => {
				const start = offset
				offset += branch.subQuestions?.length ?? 0
				return start
			})
		},

		branchIdPrefix(): string {
			return `question-${this.id}-branch-`
		},
	},

	methods: {
		getSubQuestionComponent(question: FormsQuestion) {
			return answerTypes[question.type]?.component
		},

		getSubQuestionAnswerType(question: FormsQuestion) {
			return answerTypes[question.type]
		},

		hasSubtypes(answer: AnswerTypeConfig): boolean {
			return !!answer?.subtypes && Object.keys(answer.subtypes).length > 0
		},

		/**
		 * Change the branches right away and save them debounced
		 *
		 * @param branches The new branches
		 */
		updateBranches(branches: FormsConditionalBranch[]): void {
			this.$emit('update:extraSettings', { ...this.extraSettings, branches })
			this.saveBranches()
		},

		/**
		 * Change the subquestions of a branch, they are saved on their own
		 *
		 * @param branchId The id of the branch
		 * @param change Returns the new subquestions from the current ones
		 */
		changeSubQuestions(
			branchId: string,
			change: (subQuestions: FormsQuestion[]) => FormsQuestion[],
		): void {
			// Use the branch as it is now, it may have changed during a request
			const branchIndex = this.branches.findIndex((b) => b.id === branchId)
			if (branchIndex === -1) {
				return
			}
			const newBranches = [...this.branches]
			newBranches[branchIndex] = {
				...this.branches[branchIndex],
				subQuestions: change(this.branches[branchIndex].subQuestions ?? []),
			}
			this.$emit('update:extraSettings', {
				...this.extraSettings,
				branches: newBranches,
			})
		},

		addBranch(): void {
			this.updateBranches([
				...this.branches,
				{ id: `branch-${Date.now()}`, conditions: [], subQuestions: [] },
			])
			// The first branch replaces the focused button, continue with its condition
			this.$nextTick(() => {
				;(this.$el as HTMLElement | null)
					?.querySelector?.<HTMLElement>(
						'.branch:last-child :is(input, button)',
					)
					?.focus()
			})
		},

		deleteBranch(branchId: string): void {
			this.updateBranches(this.branches.filter((b) => b.id !== branchId))
			// The focused delete button is gone with its branch
			this.$nextTick(() => {
				;(
					this.$refs.addBranchButton as { $el?: HTMLElement } | undefined
				)?.$el?.focus()
			})
		},

		onBranchUpdate(index: number, branch: FormsConditionalBranch): void {
			const newBranches = [...this.branches]
			newBranches[index] = branch
			this.updateBranches(newBranches)
		},

		getBranchLabel(branch: FormsConditionalBranch, index: number): string {
			if (!branch.conditions?.length) {
				return t('forms', 'Branch {number} (no conditions)', {
					number: index + 1,
				})
			}

			const optionTexts = branch.conditions
				.map((c) => this.options.find((o) => o.id === c.optionId)?.text)
				.filter(Boolean)
			if (optionTexts.length > 0) {
				return optionTexts.join(' + ')
			}

			return t('forms', 'Branch {number}', { number: index + 1 })
		},

		async addSubQuestion(
			branchId: string,
			type: string,
			position: number | null = null,
		): Promise<void> {
			// The branch must be stored before it can get subquestions
			await this.flushBranches()
			const body: Record<string, unknown> = {
				type,
				text: '',
				parentQuestionId: this.id,
				branchId,
			}
			if (position !== null) {
				// position: current question position + 2 (0-based index: +1, next position: +1)
				body.position = position + 2
			}

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: this.formId,
					}),
					body,
				)
				const newQuestion = OcsResponse2Data<FormsQuestion>(response)
				this.changeSubQuestions(branchId, (subQuestions) => {
					const newSubQuestions = [...subQuestions]
					newSubQuestions.splice(
						position !== null ? position + 1 : newSubQuestions.length,
						0,
						newQuestion,
					)
					return newSubQuestions
				})
				this.$nextTick(() => {
					const lists = (this.$refs.subQuestionLists ?? []) as Array<{
						focusQuestion: (id: number) => void
					}>
					lists.forEach((list) => list.focusQuestion(newQuestion.id))
				})
			} catch (error) {
				logger.error('Error adding subquestion', { error })
				showError(t('forms', 'Error adding subquestion'))
			}
		},

		onMoveUp(index: number, branchIndex: number): void {
			if (index <= 0) {
				return
			}
			const subQuestions = [...this.branches[branchIndex].subQuestions]
			;[subQuestions[index - 1], subQuestions[index]] = [
				subQuestions[index],
				subQuestions[index - 1],
			]
			this.onQuestionOrderChange(branchIndex, subQuestions)
		},

		onMoveDown(index: number, branchIndex: number): void {
			if (index < this.branches[branchIndex].subQuestions.length - 1) {
				this.onMoveUp(index + 1, branchIndex)
			}
		},

		async onQuestionOrderChange(
			branchIndex: number,
			subQuestions: FormsQuestion[],
		): Promise<void> {
			const branchId = this.branches[branchIndex]?.id
			if (!branchId) {
				return
			}
			this.isLoadingQuestions = true
			this.changeSubQuestions(branchId, () => subQuestions)
			try {
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/subquestions',
						{ id: this.formId, questionId: this.id },
					),
					{
						newOrder: subQuestions.map((question) => question.id),
						branchId,
					},
				)
				emit('forms:last-updated:set', this.formId)
			} catch (error) {
				logger.error('Error while saving form', { error })
				showError(t('forms', 'Error while saving form'))
			} finally {
				this.isLoadingQuestions = false
			}
		},

		async cloneSubQuestion(branchId: string, questionId: number): Promise<void> {
			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions?fromId={questionId}',
						{ id: this.formId, questionId },
					),
				)
				const newQuestion = OcsResponse2Data<FormsQuestion>(response)
				this.changeSubQuestions(branchId, (subQuestions) => [
					...subQuestions,
					newQuestion,
				])
			} catch (error) {
				logger.error('Error adding subquestion', { error })
				showError(t('forms', 'Error adding subquestion'))
			}
		},

		async deleteSubQuestion(
			branchId: string,
			questionId: number,
		): Promise<void> {
			try {
				await axios.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{ id: this.formId, questionId },
					),
				)
				this.changeSubQuestions(branchId, (subQuestions) =>
					subQuestions.filter((q) => q.id !== questionId),
				)
			} catch (error) {
				logger.error('Error deleting subquestion', { error })
				showError(t('forms', 'Error deleting subquestion'))
			}
		},

		updateSubQuestion(
			branchId: string,
			questionId: number,
			property: string,
			value: unknown,
		): void {
			this.changeSubQuestions(branchId, (subQuestions) =>
				subQuestions.map((q) =>
					q.id === questionId ? { ...q, [property]: value } : q,
				),
			)
		},
	},
})
</script>

<style lang="scss" scoped>
.question-branches {
	margin-top: 24px;
	padding: 16px;
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-large);

	&__title {
		margin: 0 0 8px 0;
		font-size: 1.1em;
	}

	&__description {
		margin-bottom: 16px;
		color: var(--color-text-maxcontrast);
	}
}

.question-branches__add-first {
	margin-top: 8px;
}

.branch {
	margin-bottom: 16px;
	padding: 12px;
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 12px;
	}

	&__label {
		font-weight: 500;
	}

	&__conditions {
		margin-bottom: 12px;
	}

	&__subquestions {
		padding-left: 16px;
		border-left: 3px solid var(--color-primary-element-light);

		// Phones have little width to spare, the border still shows the nesting
		@media (max-width: 768px) {
			padding-left: 8px;
		}
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
