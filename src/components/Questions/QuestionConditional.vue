<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		ref="rootElement"
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:contentValid="contentValid"
		:shiftDragHandle="false"
		v-on="commonListeners">
		<div class="question-conditional">
			<!-- Trigger Type Selection (Edit Mode) -->
			<div
				v-if="!readOnly && (!triggerType || isChangingTriggerType)"
				ref="triggerTypeSelector"
				class="trigger-type-selector">
				<p class="trigger-type-selector__label">
					{{ t('forms', 'Select the trigger question type:') }}
				</p>
				<p v-if="isChangingTriggerType" class="trigger-type-selector__hint">
					{{
						t(
							'forms',
							'The conditions of all branches are reset, the subquestions are kept.',
						)
					}}
				</p>
				<div class="trigger-type-selector__options">
					<div
						v-for="group in triggerTypeGroups"
						:key="group.label"
						class="trigger-type-selector__group">
						<p class="trigger-type-selector__group-label">
							{{ group.label }}
						</p>
						<div class="trigger-type-selector__group-options">
							<NcButton
								v-for="tt in group.types"
								:key="tt.type"
								variant="secondary"
								@click="setTriggerType(tt.type)">
								<template #icon>
									<NcIconSvgWrapper :svg="tt.icon" />
								</template>
								{{ tt.label }}
							</NcButton>
						</div>
					</div>
				</div>
				<NcButton
					v-if="isChangingTriggerType"
					class="trigger-type-selector__cancel"
					variant="tertiary"
					@click="keepTriggerType">
					{{ t('forms', 'Keep the current trigger type') }}
				</NcButton>
			</div>

			<!-- Trigger Question -->
			<div v-else-if="triggerType" class="trigger-question">
				<div v-if="!readOnly" class="trigger-question__header">
					<NcIconSvgWrapper
						:svg="currentTriggerIcon"
						class="trigger-question__icon" />
					<span class="trigger-question__type-label">
						{{
							t('forms', 'Trigger type: {type}', {
								type: currentTriggerLabel,
							})
						}}
					</span>
					<NcButton
						ref="changeTriggerTypeButton"
						variant="tertiary"
						:aria-label="t('forms', 'Change trigger type')"
						@click="clearTriggerType">
						<template #icon>
							<NcIconSvgWrapper :svg="IconPencil" />
						</template>
					</NcButton>
				</div>

				<!-- Instructions for option-based triggers -->
				<p
					v-if="!readOnly && isOptionBasedTrigger"
					class="trigger-question__instructions">
					{{
						t(
							'forms',
							'Add the answer options below. These will be used as trigger conditions.',
						)
					}}
				</p>

				<!-- Render the appropriate trigger question component -->
				<component
					:is="triggerComponent"
					v-if="triggerComponent"
					:id="id"
					ref="triggerQuestion"
					:formId="formId"
					:text="text"
					:description="description"
					:isRequired="isRequired"
					:index="index"
					:options="options"
					:extraSettings="triggerExtraSettings"
					:maxStringLengths="maxStringLengths"
					:answerType="triggerAnswerTypeConfig"
					:readOnly="readOnly"
					:isTriggerQuestion="true"
					:values="triggerValues"
					@update:values="onTriggerValueChange"
					@keydown="$emit('keydown', $event)"
					@update:options="onOptionsChange"
					@update:extraSettings="updateExtraSettings" />

				<!-- Branch Management (Edit Mode) -->
				<div v-if="!readOnly" class="branches-editor">
					<h3 class="branches-editor__title">
						{{
							text
								? t(
										'forms',
										'Branches of "{question}"',
										{ question: text },
										{ escape: false, sanitize: false },
									)
								: t('forms', 'Conditional Branches')
						}}
					</h3>
					<p class="branches-editor__description">
						{{
							t(
								'forms',
								'Define which subquestions appear based on the answer above.',
							)
						}}
					</p>

					<!-- List of branches -->
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
											{
												label: getBranchLabel(
													branch,
													branchIndex,
												),
											},
											{ escape: false, sanitize: false },
										)
									"
									@click="deleteBranch(branch.id)">
									<template #icon>
										<NcIconSvgWrapper
											:svg="IconDelete"></NcIconSvgWrapper>
									</template>
								</NcButton>
							</div>

							<!-- Branch Conditions -->
							<div class="branch__conditions">
								<BranchConditionEditor
									:branch="branch"
									:triggerType="triggerType"
									:options="options"
									@update:branch="
										onBranchUpdate(branchIndex, $event)
									" />
							</div>

							<!-- Subquestions for this branch -->
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
									:showInsert="true"
									:answerTypesFilter="subQuestionAnswerTypesFilter"
									:hasSubtypes="hasSubtypes"
									:isLoadingQuestions="isLoadingQuestions"
									@updateProperty="
										(idx, prop, val) =>
											updateSubQuestion(
												branch.id,
												branches[branchIndex].subQuestions[
													idx
												].id,
												prop,
												val,
											)
									"
									@clone="
										(question) =>
											cloneSubQuestion(branch.id, question.id)
									"
									@delete="
										(question) =>
											deleteSubQuestion(branch.id, question.id)
									"
									@moveDown="(idx) => onMoveDown(idx, branchIndex)"
									@moveUp="(idx) => onMoveUp(idx, branchIndex)"
									@update:modelValue="
										(questions) =>
											onQuestionOrderChange(
												branchIndex,
												questions,
											)
									"
									@addQuestion="
										(type, subtype, position) =>
											addSubQuestion(branch.id, type, position)
									" />

								<!-- Add subquestion button -->
								<NcActions
									:aria-label="
										t(
											'forms',
											'Add subquestion to branch {label}',
											{
												label: getBranchLabel(
													branch,
													branchIndex,
												),
											},
											{ escape: false, sanitize: false },
										)
									">
									<template #icon>
										<NcIconSvgWrapper :svg="IconPlus" />
									</template>
									<NcActionButton
										v-for="sqType in subQuestionTypesList"
										:key="sqType.type"
										:closeAfterClick="true"
										@click="
											addSubQuestion(branch.id, sqType.type)
										">
										<template #icon>
											<NcIconSvgWrapper :svg="sqType.icon" />
										</template>
										{{ sqType.label }}
									</NcActionButton>
								</NcActions>
							</div>
						</div>
					</TransitionGroup>
					<!-- Add new branch button -->
					<NcButton
						ref="addBranchButton"
						variant="secondary"
						@click="addBranch">
						<template #icon>
							<NcIconSvgWrapper :svg="IconPlus" />
						</template>
						{{ t('forms', 'Add branch') }}
					</NcButton>
				</div>

				<!-- Submit Mode: Show only the active branch's subquestions -->
				<div v-else class="active-subquestions">
					<!-- Announce subquestions that appear or disappear on a trigger change -->
					<div class="hidden-visually" aria-live="polite">
						{{ subQuestionsStatus }}
					</div>
					<TransitionGroup tag="ul" name="branch-list">
						<component
							:is="getSubQuestionComponent(subQuestion)"
							v-for="(subQuestion, subIndex) in activeSubQuestions"
							:key="subQuestion.id"
							ref="subQuestions"
							v-bind="subQuestion"
							:formId="formId"
							:index="index"
							:subIndex="subIndex + 1"
							:maxStringLengths="maxStringLengths"
							:answerType="getSubQuestionAnswerType(subQuestion)"
							:readOnly="true"
							:values="getSubQuestionValues(subQuestion.id)"
							@update:values="
								onSubQuestionValueChange(subQuestion.id, $event)
							"
							@keydown="$emit('keydown', $event)" />
					</TransitionGroup>
				</div>
			</div>
		</div>
		<slot name="insert" />
	</Question>
</template>

<script>
// Icons
import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconPencil from '@material-symbols/svg-400/outlined/edit.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { n, t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { defineComponent, ref, toRaw } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import BranchConditionEditor from '../Conditional/BranchConditionEditor.vue'
import QuestionList from '../QuestionList.vue'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'
// AnswerTypes.ts imports this component as well, so it is only read when rendering, not on import
import answerTypes from '../../models/AnswerTypes.ts'
import logger from '../../utils/Logger.ts'
import OcsResponse2Data from '../../utils/OcsResponse2Data.ts'

// Types of subquestions, conditional questions can not be nested
const SUBQUESTION_TYPES = [
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
]

// Values of all unanswered subquestions, the same array does not render them again on every answer
const NO_VALUES = Object.freeze([])

export default defineComponent({
	name: 'QuestionConditional',

	components: {
		BranchConditionEditor,
		NcActionButton,
		NcActions,
		NcButton,
		NcIconSvgWrapper,
		Question,
		QuestionList,
	},

	inheritAttrs: false,
	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, 'update:options'],

	setup(props, { emit }) {
		const rootElement = ref(null)
		const question = useQuestion(props, { emit, rootElement })
		return {
			...question,
			rootElement,
			t,
			IconPlus,
			IconDelete,
			IconPencil,
		}
	},

	data() {
		return {
			isLoadingQuestions: false,
			isChangingTriggerType: false,
			subQuestionsStatus: '',
			triggerValues: [],
			subQuestionValues: {},
		}
	},

	computed: {
		triggerTypeGroups() {
			const groups = [
				{
					label: t('forms', 'Choice'),
					types: ['multiple_unique', 'dropdown'],
				},
			]

			return groups.map((group) => ({
				...group,
				types: group.types.map((type) =>
					this.subQuestionTypesList.find((item) => item.type === type),
				),
			}))
		},

		/**
		 * Subquestion types (excludes conditional to prevent recursion)
		 */
		subQuestionTypesList() {
			return SUBQUESTION_TYPES.map((type) => ({
				type,
				icon: answerTypes[type].icon,
				label: answerTypes[type].label,
			}))
		},

		triggerType() {
			return this.extraSettings?.triggerType || null
		},

		currentTriggerIcon() {
			return answerTypes[this.triggerType]?.icon ?? ''
		},

		currentTriggerLabel() {
			return answerTypes[this.triggerType]?.label ?? ''
		},

		/**
		 * Check if trigger type uses predefined options (radio, dropdown)
		 */
		isOptionBasedTrigger() {
			return ['multiple_unique', 'dropdown'].includes(this.triggerType)
		},

		triggerComponent() {
			return answerTypes[this.triggerType]?.component ?? null
		},

		triggerAnswerTypeConfig() {
			return answerTypes[this.triggerType] ?? null
		},

		triggerExtraSettings() {
			// eslint-disable-next-line @typescript-eslint/no-unused-vars
			const { triggerType, branches, ...rest } = this.extraSettings || {}
			return rest
		},

		branches() {
			return this.extraSettings?.branches || []
		},

		getSubQuestionComponent() {
			return (question) => answerTypes[question.type]?.component
		},

		getSubQuestionAnswerType() {
			return (question) => answerTypes[question.type]
		},

		/**
		 * Build answerTypes filter for subquestion insert menu
		 * Filters out 'conditional' to prevent recursion
		 */
		subQuestionAnswerTypesFilter() {
			const filter = {}
			for (const sqType of this.subQuestionTypesList) {
				filter[sqType.type] = {
					icon: sqType.icon,
					label: sqType.label,
				}
			}
			return filter
		},

		/**
		 * Check if an answer type has subtypes
		 */
		hasSubtypes() {
			return (answer) =>
				answer && answer.subtypes && Object.keys(answer.subtypes).length > 0
		},

		activeBranches() {
			// An unanswered trigger activates no branch
			if (!this.triggerValues || this.triggerValues.length === 0) {
				return null
			}
			return this.branches.filter((branch) =>
				this.evaluateBranchCondition(branch),
			)
		},

		activeSubQuestions() {
			// Like other questions, subquestions without a title or without options are not shown
			const isShown = (question) =>
				!!question.text?.trim()
				&& (answerTypes[question.type]?.validate?.(question) ?? true)
			return (this.activeBranches ?? [])
				.flatMap((branch) => branch.subQuestions ?? [])
				.filter(isShown)
		},

		/**
		 * Subquestions are numbered across the branches, so each branch starts after the previous ones
		 */
		subIndexOffsets() {
			let offset = 0
			return this.branches.map((branch) => {
				const start = offset
				offset += branch.subQuestions?.length ?? 0
				return start
			})
		},

		branchIdPrefix() {
			return `conditional-${this.id}-branch-`
		},

		contentValid() {
			return !!this.triggerType && this.branches.length > 0
		},
	},

	watch: {
		activeSubQuestions(newQuestions, oldQuestions) {
			const ids = (questions) =>
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
			this.subQuestionsStatus = ''
			this.$nextTick(() => {
				this.subQuestionsStatus = status
			})
		},

		values: {
			immediate: true,
			handler(newValues) {
				// The values emitted last leave out the answers of hidden branches, keep them
				if (newValues && toRaw(newValues) === this.emittedValues) {
					return
				}

				this.triggerValues = newValues?.trigger ?? []
				this.subQuestionValues = { ...(newValues?.subQuestions ?? {}) }

				// Restored answers can belong to subquestions that are not shown (anymore)
				const shownIds = new Set(
					this.activeSubQuestions.map((question) => String(question.id)),
				)
				if (
					Object.keys(this.subQuestionValues).some(
						(id) => !shownIds.has(id),
					)
				) {
					this.emitValues()
				}
			},
		},
	},

	methods: {
		setTriggerType(type) {
			const previousType = this.triggerType
			this.isChangingTriggerType = false
			// The type selection is replaced by the trigger
			this.focusChangeTriggerType()
			if (type === previousType) {
				return
			}

			// Settings of the previous trigger type would be invalid for the new one,
			// undefined keys are left out when saving
			// eslint-disable-next-line @typescript-eslint/no-unused-vars
			const { triggerType, branches, ...previousSettings } =
				this.extraSettings || {}
			const clearedSettings = Object.fromEntries(
				Object.keys(previousSettings).map((key) => [key, undefined]),
			)

			this.updateExtraSettings({
				...clearedSettings,
				triggerType: type,
				// Conditions depend on the trigger type, the subquestions are kept
				branches: previousType
					? this.branches.map((branch) => ({ ...branch, conditions: [] }))
					: this.branches,
			})
		},

		clearTriggerType() {
			this.isChangingTriggerType = true
			this.$nextTick(() => {
				this.$refs.triggerTypeSelector?.querySelector('button')?.focus()
			})
		},

		keepTriggerType() {
			this.isChangingTriggerType = false
			this.focusChangeTriggerType()
		},

		focusChangeTriggerType() {
			this.$nextTick(() => {
				this.$refs.changeTriggerTypeButton?.$el.focus()
			})
		},

		/**
		 * Change the extra settings right away and save them debounced
		 *
		 * Saving stores the settings as they are by then, so a change made meanwhile is not lost.
		 *
		 * @param {object} settings The changed extra settings
		 */
		updateExtraSettings(settings) {
			this.$emit('update:extraSettings', {
				...this.extraSettings,
				...settings,
			})
			this.onExtraSettingsChange({})
		},

		onTriggerValueChange(values) {
			this.triggerValues = values
			this.emitValues()
		},

		onOptionsChange(options) {
			this.$emit('update:options', options)
		},

		addBranch() {
			const newBranch = {
				id: `branch-${Date.now()}`,
				conditions: [],
				subQuestions: [],
			}
			const newBranches = [...this.branches, newBranch]
			this.updateExtraSettings({ branches: newBranches })
		},

		deleteBranch(branchId) {
			const newBranches = this.branches.filter((b) => b.id !== branchId)
			this.updateExtraSettings({ branches: newBranches })
			// The focused delete button is gone with its branch
			this.$nextTick(() => {
				this.$refs.addBranchButton?.$el.focus()
			})
		},

		onBranchUpdate(index, branch) {
			const newBranches = [...this.branches]
			newBranches[index] = branch
			this.updateExtraSettings({ branches: newBranches })
		},

		getBranchLabel(branch, index) {
			if (!branch.conditions || branch.conditions.length === 0) {
				return t('forms', 'Branch {number} (no conditions)', {
					number: index + 1,
				})
			}

			if (this.isOptionBasedTrigger) {
				const optionIds = branch.conditions.map((c) => c.optionId)
				const optionTexts = optionIds
					.map((id) => this.options.find((o) => o.id === id)?.text)
					.filter(Boolean)
				if (optionTexts.length > 0) {
					return optionTexts.join(' + ')
				}
			}

			return t('forms', 'Branch {number}', { number: index + 1 })
		},

		async addSubQuestion(branchId, type, position = null) {
			if (!this.branches.some((b) => b.id === branchId)) return

			const body = {
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
				const newQuestion = OcsResponse2Data(response)

				// Use the branch as it is now, it may have changed during the request
				const branchIndex = this.branches.findIndex((b) => b.id === branchId)
				if (branchIndex === -1) return
				const branch = this.branches[branchIndex]
				const newSubQuestions = [...(branch.subQuestions || [])]
				if (position !== null) {
					newSubQuestions.splice(position + 1, 0, newQuestion)
				} else {
					newSubQuestions.push(newQuestion)
				}
				const newBranches = [...this.branches]
				newBranches[branchIndex] = {
					...branch,
					subQuestions: newSubQuestions,
				}
				this.$emit('update:extraSettings', {
					...this.extraSettings,
					branches: newBranches,
				})
				this.$nextTick(() => {
					this.$refs.subQuestionLists?.forEach((list) =>
						list.focusQuestion(newQuestion.id),
					)
				})
			} catch (error) {
				logger.error('Error adding subquestion', { error })
				showError(t('forms', 'Error adding subquestion'))
			}
		},

		onMoveUp(index, branchIndex) {
			const subQuestions = [...this.branches[branchIndex].subQuestions]

			if (index > 0) {
				;[subQuestions[index - 1], subQuestions[index]] = [
					subQuestions[index],
					subQuestions[index - 1],
				]
			}
			this.onQuestionOrderChange(branchIndex, subQuestions)
		},

		onMoveDown(index, branchIndex) {
			// only if not the last one
			if (index < this.branches[branchIndex].subQuestions.length - 1) {
				this.onMoveUp(index + 1, branchIndex)
			}
		},

		async onQuestionOrderChange(branchIndex, subQuestions) {
			this.isLoadingQuestions = true
			const branch = this.branches[branchIndex]
			const newBranches = [...this.branches]
			newBranches[branchIndex] = { ...branch, subQuestions }
			this.$emit('update:extraSettings', {
				...this.extraSettings,
				branches: newBranches,
			})
			const newOrder = subQuestions.map((question) => question.id)
			try {
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/subquestions',
						{
							id: this.formId,
							questionId: this.id,
						},
					),
					{
						newOrder,
						branchId: branch.id,
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

		async cloneSubQuestion(branchId, questionId) {
			if (!this.branches.some((b) => b.id === branchId)) return

			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions?fromId={questionId}',
						{
							id: this.formId,
							questionId,
						},
					),
				)
				const newQuestion = OcsResponse2Data(response)

				// Use the branch as it is now, it may have changed during the request
				const branchIndex = this.branches.findIndex((b) => b.id === branchId)
				if (branchIndex === -1) return
				const branch = this.branches[branchIndex]
				const newBranches = [...this.branches]
				newBranches[branchIndex] = {
					...branch,
					subQuestions: [...(branch.subQuestions || []), newQuestion],
				}
				this.$emit('update:extraSettings', {
					...this.extraSettings,
					branches: newBranches,
				})
			} catch (error) {
				logger.error('Error adding subquestion', { error })
				showError(t('forms', 'Error adding subquestion'))
			}
		},

		async deleteSubQuestion(branchId, questionId) {
			try {
				await axios.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}',
						{
							id: this.formId,
							questionId,
						},
					),
				)

				const branchIndex = this.branches.findIndex((b) => b.id === branchId)
				if (branchIndex === -1) return
				const branch = this.branches[branchIndex]
				const newBranches = [...this.branches]
				newBranches[branchIndex] = {
					...branch,
					subQuestions: branch.subQuestions.filter(
						(q) => q.id !== questionId,
					),
				}
				this.$emit('update:extraSettings', {
					...this.extraSettings,
					branches: newBranches,
				})
			} catch (error) {
				logger.error('Error deleting subquestion', { error })
				showError(t('forms', 'Error deleting subquestion'))
			}
		},

		updateSubQuestion(branchId, questionId, property, value) {
			const branchIndex = this.branches.findIndex((b) => b.id === branchId)
			const branch = this.branches[branchIndex]

			const newBranches = [...this.branches]
			newBranches[branchIndex] = {
				...branch,
				subQuestions: branch.subQuestions.map((q) =>
					q.id === questionId ? { ...q, [property]: value } : q,
				),
			}
			this.$emit('update:extraSettings', {
				...this.extraSettings,
				branches: newBranches,
			})
		},

		getSubQuestionValues(questionId) {
			return this.subQuestionValues[questionId] ?? NO_VALUES
		},

		onSubQuestionValueChange(questionId, values) {
			this.subQuestionValues = {
				...this.subQuestionValues,
				[questionId]: values,
			}
			this.emitValues()
		},

		emitValues() {
			// Only send the answers of the subquestions that are shown, answers of other
			// branches are kept here in case the trigger is changed back
			const shownIds = new Set(
				this.activeSubQuestions.map((question) => String(question.id)),
			)
			this.emittedValues = {
				trigger: this.triggerValues,
				subQuestions: Object.fromEntries(
					Object.entries(this.subQuestionValues).filter(([id]) =>
						shownIds.has(id),
					),
				),
			}
			this.$emit('update:values', this.emittedValues)
		},

		evaluateBranchCondition(branch) {
			if (!branch.conditions || branch.conditions.length === 0) {
				return false
			}

			// Convert triggerValues to strings for consistent comparison
			const triggerValuesAsStrings = this.triggerValues.map((v) => String(v))

			if (['multiple_unique', 'dropdown'].includes(this.triggerType)) {
				return branch.conditions.some((c) =>
					triggerValuesAsStrings.includes(String(c.optionId)),
				)
			}

			return false
		},

		async validate() {
			if (this.$refs.triggerQuestion?.validate) {
				const triggerValid = await this.$refs.triggerQuestion.validate()
				if (!triggerValid) return false
			}

			if (this.$refs.subQuestions) {
				for (const subQuestion of this.$refs.subQuestions) {
					if (subQuestion.validate) {
						const valid = await subQuestion.validate()
						if (!valid) return false
					}
				}
			}

			return true
		},
	},
})
</script>

<style lang="scss" scoped>
.question-conditional {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.trigger-type-selector {
	padding: 16px;
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-large);

	&__label {
		margin-bottom: 12px;
		font-weight: bold;
	}

	&__options {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	&__group-label {
		margin-bottom: 4px;
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
	}

	&__group-options {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}
}

.trigger-question {
	&__header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 12px;
		padding: 8px;
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius);
	}

	&__icon {
		color: var(--color-primary-element);
	}

	&__type-label {
		font-weight: 500;
		flex: 1;
	}

	&__instructions {
		margin: 0 0 12px 0;
		padding: 8px 12px;
		background-color: var(--color-background-hover);
		border-radius: var(--border-radius);
		color: var(--color-text-maxcontrast);
		font-size: 0.9em;
	}
}

.branches-editor {
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

.active-subquestions {
	margin-top: 16px;
	padding-left: 16px;
	border-left: 3px solid var(--color-primary-element);

	// Phones have little width to spare, the border still shows the nesting
	@media (max-width: 768px) {
		padding-left: 8px;
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
