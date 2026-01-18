<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="false"
		v-on="commonListeners">
		<template #actions>
			<!-- Trigger type selection in menu -->
			<NcActionButton
				v-for="tt in triggerTypesList"
				:key="tt.type"
				:close-after-click="true"
				@click="setTriggerType(tt.type)">
				<template #icon>
					<component :is="tt.icon" :size="20" />
				</template>
				{{ tt.label }}
			</NcActionButton>
		</template>

		<div class="question-conditional">
			<!-- Trigger Type Selection (Edit Mode) -->
			<div v-if="!readOnly && !triggerType" class="trigger-type-selector">
				<p class="trigger-type-selector__label">
					{{ t('forms', 'Select the trigger question type:') }}
				</p>
				<div class="trigger-type-selector__options">
					<NcButton
						v-for="tt in triggerTypesList"
						:key="tt.type"
						variant="secondary"
						@click="setTriggerType(tt.type)">
						<template #icon>
							<component :is="tt.icon" :size="20" />
						</template>
						{{ tt.label }}
					</NcButton>
				</div>
			</div>

			<!-- Trigger Question -->
			<div v-else-if="triggerType" class="trigger-question">
				<div class="trigger-question__header">
					<component
						:is="currentTriggerIcon"
						:size="20"
						class="trigger-question__icon" />
					<span class="trigger-question__type-label">
						{{
							t('forms', 'Trigger type: {type}', {
								type: currentTriggerLabel,
							})
						}}
					</span>
					<NcButton
						v-if="!readOnly"
						variant="tertiary"
						:aria-label="t('forms', 'Change trigger type')"
						@click="clearTriggerType">
						<template #icon>
							<IconPencil :size="20" />
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
					:is="triggerComponentName"
					v-if="triggerComponentName"
					:id="id"
					ref="triggerQuestion"
					:form-id="formId"
					text=""
					description=""
					:is-required="false"
					:index="0"
					:options="options"
					:extra-settings="triggerExtraSettings"
					:max-string-lengths="maxStringLengths"
					:answer-type="triggerAnswerTypeConfig"
					:read-only="readOnly"
					:values="triggerValues"
					@update:values="onTriggerValueChange"
					@update:options="onOptionsChange" />

				<!-- Branch Management (Edit Mode) -->
				<div v-if="!readOnly" class="branches-editor">
					<h4 class="branches-editor__title">
						{{ t('forms', 'Conditional Branches') }}
					</h4>
					<p class="branches-editor__description">
						{{
							t(
								'forms',
								'Define which subquestions appear based on the answer above.',
							)
						}}
					</p>

					<!-- List of branches -->
					<div
						v-for="(branch, branchIndex) in branches"
						:key="branch.id"
						class="branch">
						<div class="branch__header">
							<span class="branch__label">
								{{ getBranchLabel(branch, branchIndex) }}
							</span>
							<NcButton
								variant="tertiary"
								:aria-label="t('forms', 'Delete branch')"
								@click="deleteBranch(branch.id)">
								<template #icon>
									<IconDelete :size="20" />
								</template>
							</NcButton>
						</div>

						<!-- Branch Conditions -->
						<div class="branch__conditions">
							<BranchConditionEditor
								:branch="branch"
								:trigger-type="triggerType"
								:options="options"
								@update:branch="
									onBranchUpdate(branchIndex, $event)
								" />
						</div>

						<!-- Subquestions for this branch -->
						<div class="branch__subquestions">
							<ul class="branch__subquestions-list">
								<li
									v-for="(
										subQuestion, subIndex
									) in branch.subQuestions"
									:key="subQuestion.id"
									class="subquestion">
									<component
										:is="
											getSubQuestionComponentName(
												subQuestion.type,
											)
										"
										v-bind="subQuestion"
										:form-id="formId"
										:index="subIndex + 1"
										:max-string-lengths="maxStringLengths"
										:answer-type="
											getSubQuestionAnswerTypeConfig(
												subQuestion.type,
											)
										"
										@update:text="
											updateSubQuestion(
												branch.id,
												subQuestion.id,
												'text',
												$event,
											)
										"
										@update:description="
											updateSubQuestion(
												branch.id,
												subQuestion.id,
												'description',
												$event,
											)
										"
										@update:isRequired="
											updateSubQuestion(
												branch.id,
												subQuestion.id,
												'isRequired',
												$event,
											)
										"
										@delete="
											deleteSubQuestion(
												branch.id,
												subQuestion.id,
											)
										" />
								</li>
							</ul>

							<!-- Add subquestion button -->
							<NcActions :aria-label="t('forms', 'Add subquestion')">
								<template #icon>
									<IconPlus :size="20" />
								</template>
								<NcActionButton
									v-for="sqType in subQuestionTypesList"
									:key="sqType.type"
									:close-after-click="true"
									@click="addSubQuestion(branch.id, sqType.type)">
									<template #icon>
										<component :is="sqType.icon" :size="20" />
									</template>
									{{ sqType.label }}
								</NcActionButton>
							</NcActions>
						</div>
					</div>

					<!-- Add new branch button -->
					<NcButton variant="secondary" @click="addBranch">
						<template #icon>
							<IconPlus :size="20" />
						</template>
						{{ t('forms', 'Add branch') }}
					</NcButton>
				</div>

				<!-- Submit Mode: Show only the active branch's subquestions -->
				<div v-else-if="activeBranch" class="active-subquestions">
					<component
						:is="getSubQuestionComponentName(subQuestion.type)"
						v-for="(subQuestion, subIndex) in activeBranch.subQuestions"
						:key="subQuestion.id"
						ref="subQuestions"
						v-bind="subQuestion"
						:form-id="formId"
						:index="subIndex + 1"
						:max-string-lengths="maxStringLengths"
						:answer-type="
							getSubQuestionAnswerTypeConfig(subQuestion.type)
						"
						:read-only="true"
						:values="getSubQuestionValues(subQuestion.id)"
						@update:values="
							onSubQuestionValueChange(subQuestion.id, $event)
						" />
				</div>
			</div>
		</div>
	</Question>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
// Icons
import IconArrowDownDropCircleOutline from 'vue-material-design-icons/ArrowDownDropCircleOutline.vue'
import IconCalendar from 'vue-material-design-icons/CalendarOutline.vue'
import IconCheckboxOutline from 'vue-material-design-icons/CheckboxOutline.vue'
import IconClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import IconFile from 'vue-material-design-icons/FileOutline.vue'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconRadioboxMarked from 'vue-material-design-icons/RadioboxMarked.vue'
import IconTextLong from 'vue-material-design-icons/TextLong.vue'
import IconTextShort from 'vue-material-design-icons/TextShort.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'
import IconLinearScale from '../Icons/IconLinearScale.vue'
import IconPalette from '../Icons/IconPalette.vue'
import BranchConditionEditor from './BranchConditionEditor.vue'
import Question from './Question.vue'
// Question components - imported directly to avoid circular dependency with AnswerTypes.js
import QuestionColor from './QuestionColor.vue'
import QuestionDate from './QuestionDate.vue'
import QuestionDropdown from './QuestionDropdown.vue'
import QuestionFile from './QuestionFile.vue'
import QuestionLinearScale from './QuestionLinearScale.vue'
import QuestionLong from './QuestionLong.vue'
import QuestionMultiple from './QuestionMultiple.vue'
import QuestionShort from './QuestionShort.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import logger from '../../utils/Logger.js'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'

// Local mapping of question types - avoids circular dependency with AnswerTypes.js
const QUESTION_COMPONENTS = {
	short: QuestionShort,
	long: QuestionLong,
	multiple: QuestionMultiple,
	multiple_unique: QuestionMultiple,
	dropdown: QuestionDropdown,
	date: QuestionDate,
	time: QuestionDate,
	linearscale: QuestionLinearScale,
	color: QuestionColor,
	file: QuestionFile,
}

export default {
	name: 'QuestionConditional',

	components: {
		BranchConditionEditor,
		IconArrowDownDropCircleOutline,
		IconCalendar,
		IconCheckboxOutline,
		IconClockOutline,
		IconDelete,
		IconFile,
		IconLinearScale,
		IconPalette,
		IconPencil,
		IconPlus,
		IconRadioboxMarked,
		IconTextLong,
		IconTextShort,
		NcActionButton,
		NcActions,
		NcButton,
		Question,
		QuestionColor,
		QuestionDate,
		QuestionDropdown,
		QuestionFile,
		QuestionLinearScale,
		QuestionLong,
		QuestionMultiple,
		QuestionShort,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			triggerValues: [],
			subQuestionValues: {},
		}
	},

	computed: {
		/**
		 * Trigger types available for conditional questions
		 */
		triggerTypesList() {
			return [
				{
					type: 'multiple_unique',
					label: t('forms', 'Radio buttons'),
					icon: IconRadioboxMarked,
				},
				{
					type: 'dropdown',
					label: t('forms', 'Dropdown'),
					icon: IconArrowDownDropCircleOutline,
				},
				{
					type: 'multiple',
					label: t('forms', 'Checkboxes'),
					icon: IconCheckboxOutline,
				},
				{
					type: 'short',
					label: t('forms', 'Short answer'),
					icon: IconTextShort,
				},
				{ type: 'long', label: t('forms', 'Long text'), icon: IconTextLong },
				{
					type: 'linearscale',
					label: t('forms', 'Linear scale'),
					icon: IconLinearScale,
				},
				{ type: 'date', label: t('forms', 'Date'), icon: IconCalendar },
				{ type: 'time', label: t('forms', 'Time'), icon: IconClockOutline },
				{ type: 'color', label: t('forms', 'Color'), icon: IconPalette },
				{ type: 'file', label: t('forms', 'File'), icon: IconFile },
			]
		},

		/**
		 * Subquestion types (excludes conditional to prevent recursion)
		 */
		subQuestionTypesList() {
			return [
				{
					type: 'short',
					label: t('forms', 'Short answer'),
					icon: IconTextShort,
				},
				{ type: 'long', label: t('forms', 'Long text'), icon: IconTextLong },
				{
					type: 'multiple',
					label: t('forms', 'Checkboxes'),
					icon: IconCheckboxOutline,
				},
				{
					type: 'multiple_unique',
					label: t('forms', 'Radio buttons'),
					icon: IconRadioboxMarked,
				},
				{
					type: 'dropdown',
					label: t('forms', 'Dropdown'),
					icon: IconArrowDownDropCircleOutline,
				},
				{ type: 'date', label: t('forms', 'Date'), icon: IconCalendar },
				{ type: 'time', label: t('forms', 'Time'), icon: IconClockOutline },
				{
					type: 'linearscale',
					label: t('forms', 'Linear scale'),
					icon: IconLinearScale,
				},
				{ type: 'color', label: t('forms', 'Color'), icon: IconPalette },
				{ type: 'file', label: t('forms', 'File'), icon: IconFile },
			]
		},

		triggerType() {
			return this.extraSettings?.triggerType || null
		},

		currentTriggerConfig() {
			return this.triggerTypesList.find((t) => t.type === this.triggerType)
		},

		currentTriggerIcon() {
			return this.currentTriggerConfig?.icon || IconRadioboxMarked
		},

		currentTriggerLabel() {
			return this.currentTriggerConfig?.label || ''
		},

		/**
		 * Check if trigger type uses predefined options (radio, dropdown, checkbox)
		 */
		isOptionBasedTrigger() {
			return ['multiple_unique', 'dropdown', 'multiple'].includes(
				this.triggerType,
			)
		},

		/**
		 * Ensure options is always an array for the trigger component
		 */
		triggerOptions() {
			return Array.isArray(this.options) ? this.options : []
		},

		/**
		 * Get component name for trigger (used with :is)
		 */
		triggerComponentName() {
			if (!this.triggerType) return null
			return QUESTION_COMPONENTS[this.triggerType]
				? this.getComponentNameForType(this.triggerType)
				: null
		},

		/**
		 * Answer type config for trigger question
		 */
		triggerAnswerTypeConfig() {
			if (!this.triggerType) return null
			return this.buildAnswerTypeConfig(this.triggerType)
		},

		triggerExtraSettings() {
			// eslint-disable-next-line @typescript-eslint/no-unused-vars
			const { triggerType, branches, ...rest } = this.extraSettings || {}
			return rest
		},

		branches() {
			return this.extraSettings?.branches || []
		},

		activeBranch() {
			if (!this.triggerValues || this.triggerValues.length === 0) {
				return null
			}
			return this.branches.find((branch) =>
				this.evaluateBranchCondition(branch),
			)
		},

		contentValid() {
			return !!this.triggerType && this.branches.length > 0
		},
	},

	watch: {
		values: {
			immediate: true,
			handler(newValues) {
				if (newValues && newValues.trigger) {
					this.triggerValues = newValues.trigger
				}
				if (newValues && newValues.subQuestions) {
					this.subQuestionValues = { ...newValues.subQuestions }
				}
			},
		},
	},

	methods: {
		/**
		 * Get Vue component name string for a question type
		 *
		 * @param {string} type The question type
		 * @return {string|null} The component name or null
		 */
		getComponentNameForType(type) {
			const componentMap = {
				short: 'QuestionShort',
				long: 'QuestionLong',
				multiple: 'QuestionMultiple',
				multiple_unique: 'QuestionMultiple',
				dropdown: 'QuestionDropdown',
				date: 'QuestionDate',
				time: 'QuestionDate',
				linearscale: 'QuestionLinearScale',
				color: 'QuestionColor',
				file: 'QuestionFile',
			}
			return componentMap[type] || null
		},

		/**
		 * Get component name for subquestion
		 *
		 * @param {string} type The question type
		 * @return {string|null} The component name or null
		 */
		getSubQuestionComponentName(type) {
			return this.getComponentNameForType(type)
		},

		/**
		 * Build answerType config object for a question type
		 *
		 * @param {string} type The question type
		 * @return {object} The answer type configuration
		 */
		buildAnswerTypeConfig(type) {
			const configs = {
				short: {
					titlePlaceholder: t('forms', 'Short answer question title'),
					createPlaceholder: t('forms', 'People can enter a short answer'),
					submitPlaceholder: t('forms', 'Enter your answer'),
					warningInvalid: t('forms', 'This question needs a title!'),
					validate: () => true,
				},

				long: {
					titlePlaceholder: t('forms', 'Long text question title'),
					createPlaceholder: t('forms', 'People can enter a long text'),
					submitPlaceholder: t('forms', 'Enter your answer'),
					warningInvalid: t('forms', 'This question needs a title!'),
					validate: () => true,
				},

				multiple: {
					titlePlaceholder: t('forms', 'Checkbox question title'),
					createPlaceholder: t(
						'forms',
						'People can submit a different answer',
					),

					submitPlaceholder: t('forms', 'Enter your answer'),
					warningInvalid: t(
						'forms',
						'This question needs a title and at least one answer!',
					),

					predefined: true,
					validate: (question) => question.options?.length > 0,
				},

				multiple_unique: {
					titlePlaceholder: t('forms', 'Radio buttons question title'),
					createPlaceholder: t(
						'forms',
						'People can submit a different answer',
					),

					submitPlaceholder: t('forms', 'Enter your answer'),
					warningInvalid: t(
						'forms',
						'This question needs a title and at least one answer!',
					),

					predefined: true,
					unique: true,
					validate: (question) => question.options?.length > 0,
				},

				dropdown: {
					titlePlaceholder: t('forms', 'Dropdown question title'),
					createPlaceholder: t('forms', 'People can pick one option'),
					submitPlaceholder: t('forms', 'Pick an option'),
					warningInvalid: t(
						'forms',
						'This question needs a title and at least one answer!',
					),

					predefined: true,
					validate: (question) => question.options?.length > 0,
				},

				date: {
					titlePlaceholder: t('forms', 'Date question title'),
					createPlaceholder: t('forms', 'People can pick a date'),
					submitPlaceholder: t('forms', 'Pick a date'),
					warningInvalid: t('forms', 'This question needs a title!'),
					pickerType: 'date',
					storageFormat: 'YYYY-MM-DD',
					momentFormat: 'L',
					validate: () => true,
				},

				time: {
					titlePlaceholder: t('forms', 'Time question title'),
					createPlaceholder: t('forms', 'People can pick a time'),
					submitPlaceholder: t('forms', 'Pick a time'),
					warningInvalid: t('forms', 'This question needs a title!'),
					pickerType: 'time',
					storageFormat: 'HH:mm',
					momentFormat: 'LT',
					validate: () => true,
				},

				linearscale: {
					titlePlaceholder: t('forms', 'Linear scale question title'),
					warningInvalid: t('forms', 'This question needs a title!'),
					predefined: true,
					validate: () => true,
				},

				color: {
					titlePlaceholder: t('forms', 'Color question title'),
					createPlaceholder: t('forms', 'People can pick a color'),
					submitPlaceholder: t('forms', 'Pick a color'),
					warningInvalid: t('forms', 'This question needs a title!'),
					validate: () => true,
				},

				file: {
					titlePlaceholder: t('forms', 'File question title'),
					warningInvalid: t('forms', 'This question needs a title!'),
					validate: () => true,
				},
			}
			return configs[type] || { validate: () => true }
		},

		/**
		 * Get answer type config for subquestion
		 *
		 * @param {string} type The question type
		 * @return {object} The answer type configuration
		 */
		getSubQuestionAnswerTypeConfig(type) {
			return this.buildAnswerTypeConfig(type)
		},

		setTriggerType(type) {
			const newExtraSettings = {
				...this.extraSettings,
				triggerType: type,
				branches: this.branches.length > 0 ? this.branches : [],
			}
			this.onExtraSettingsChange(newExtraSettings)
		},

		clearTriggerType() {
			const newExtraSettings = {
				...this.extraSettings,
				triggerType: null,
			}
			this.onExtraSettingsChange(newExtraSettings)
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
			this.onExtraSettingsChange({ branches: newBranches })
		},

		deleteBranch(branchId) {
			const newBranches = this.branches.filter((b) => b.id !== branchId)
			this.onExtraSettingsChange({ branches: newBranches })
		},

		onBranchUpdate(index, branch) {
			const newBranches = [...this.branches]
			newBranches[index] = branch
			this.onExtraSettingsChange({ branches: newBranches })
		},

		getBranchLabel(branch, index) {
			if (!branch.conditions || branch.conditions.length === 0) {
				return t('forms', 'Branch {number} (no conditions)', {
					number: index + 1,
				})
			}

			if (
				['multiple_unique', 'dropdown', 'multiple'].includes(
					this.triggerType,
				)
			) {
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

		async addSubQuestion(branchId, type) {
			const branch = this.branches.find((b) => b.id === branchId)
			if (!branch) return

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/questions', {
						id: this.formId,
					}),
					{
						type,
						text: '',
						parentQuestionId: this.id,
						branchId,
					},
				)
				const newQuestion = OcsResponse2Data(response)

				const branchIndex = this.branches.findIndex((b) => b.id === branchId)
				const newBranches = [...this.branches]
				newBranches[branchIndex] = {
					...branch,
					subQuestions: [...(branch.subQuestions || []), newQuestion],
				}
				this.onExtraSettingsChange({ branches: newBranches })
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
				const branch = this.branches[branchIndex]
				const newBranches = [...this.branches]
				newBranches[branchIndex] = {
					...branch,
					subQuestions: branch.subQuestions.filter(
						(q) => q.id !== questionId,
					),
				}
				this.onExtraSettingsChange({ branches: newBranches })
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
			this.onExtraSettingsChange({ branches: newBranches })
		},

		getSubQuestionValues(questionId) {
			return this.subQuestionValues[questionId] || []
		},

		onSubQuestionValueChange(questionId, values) {
			this.subQuestionValues = {
				...this.subQuestionValues,
				[questionId]: values,
			}
			this.emitValues()
		},

		emitValues() {
			this.$emit('update:values', {
				trigger: this.triggerValues,
				subQuestions: this.subQuestionValues,
			})
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

			if (this.triggerType === 'multiple') {
				// Multi-select: all condition option IDs must be selected
				for (const c of branch.conditions) {
					const optionIds = c.optionIds
					if (!Array.isArray(optionIds) || optionIds.length === 0) {
						continue
					}
					const allSelected = optionIds.every((id) =>
						triggerValuesAsStrings.includes(String(id)),
					)
					if (allSelected) {
						return true
					}
				}
				return false
			}

			if (['short', 'long'].includes(this.triggerType)) {
				const text = this.triggerValues[0] || ''
				return branch.conditions.some((c) => {
					if (c.type === 'regex') {
						return this.safeRegexMatch(c.value, text)
					}
					if (c.type === 'string_contains') {
						return text.includes(c.value || '')
					}
					if (c.type === 'string_equals') {
						return text === c.value
					}
					return false
				})
			}

			if (this.triggerType === 'linearscale') {
				const numValue = parseFloat(this.triggerValues[0]) || 0
				return branch.conditions.some((c) => {
					if (c.type === 'value_equals') {
						return numValue === parseFloat(c.value)
					}
					if (c.type === 'value_range') {
						const min = c.min ?? Number.MIN_SAFE_INTEGER
						const max = c.max ?? Number.MAX_SAFE_INTEGER
						return numValue >= min && numValue <= max
					}
					return false
				})
			}

			if (this.triggerType === 'color') {
				const colorValue = (this.triggerValues[0] || '').toLowerCase()
				return branch.conditions.some(
					(c) => colorValue === (c.value || '').toLowerCase(),
				)
			}

			if (this.triggerType === 'file') {
				const hasFile =
					this.triggerValues.length > 0 && this.triggerValues[0] !== ''
				return branch.conditions.some(
					(c) => (c.fileUploaded ?? true) === hasFile,
				)
			}

			if (['date', 'time'].includes(this.triggerType)) {
				const dateValue = this.triggerValues[0] || ''
				if (!dateValue) {
					return false
				}
				return branch.conditions.some((c) => {
					const min = c.min
					const max = c.max
					if (min && dateValue < min) {
						return false
					}
					if (max && dateValue > max) {
						return false
					}
					return true
				})
			}

			return false
		},

		/**
		 * Safely execute a regex match to prevent ReDoS attacks
		 *
		 * @param {string} pattern The regex pattern
		 * @param {string} subject The string to match against
		 * @return {boolean} True if pattern matches
		 */
		safeRegexMatch(pattern, subject) {
			if (!pattern || subject.length > 10000) {
				return false
			}
			try {
				const regex = new RegExp(pattern)
				return regex.test(subject)
			} catch {
				return false
			}
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
}
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
	}

	&__subquestions-list {
		list-style: none;
		padding: 0;
		margin: 0;
	}
}

.subquestion {
	margin-bottom: 8px;
}

.active-subquestions {
	margin-top: 16px;
	padding-left: 16px;
	border-left: 3px solid var(--color-primary-element);
}
</style>
