<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		v-on="commonListeners">
		<template v-if="readOnly">
			<fieldset :name="name || undefined" :aria-labelledby="titleId">
				<NcNoteCard v-if="hasError" :id="errorId" type="error">
					{{ errorMessage }}
				</NcNoteCard>

				<table class="answer-grid">
					<thead>
						<tr>
							<th class="first-column"></th>

							<th
								v-for="column of columns"
								:key="column.local ? 'option-local' : column.id">
								{{ column.text }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="row of rows"
							:key="row.local ? 'option-local' : row.id">
							<td class="first-column">{{ row.text }}</td>
							<td
								v-for="column of columns"
								:key="column.local ? 'option-local' : column.id">
								<template v-if="questionType === 'radio'">
									<NcCheckboxRadioSwitch
										:aria-errormessage="
											hasError ? errorId : undefined
										"
										:aria-invalid="hasError ? 'true' : undefined"
										:model-value="values[row.id]"
										:value="column.id.toString()"
										:name="`${row.id}-answer`"
										type="radio"
										@update:modelValue="
											onChangeCheckboxRadio(row.id, $event)
										" />
								</template>

								<template v-if="questionType === 'checkbox'">
									<NcCheckboxRadioSwitch
										:aria-errormessage="
											hasError ? errorId : undefined
										"
										:aria-invalid="hasError ? 'true' : undefined"
										:model-value="values[row.id] || []"
										:value="column.id.toString()"
										:name="`${row.id}-answer`"
										type="checkbox"
										@update:modelValue="
											onChangeCheckboxRadio(row.id, $event)
										" />
								</template>

								<template v-if="questionType === 'number'">
									<NcInputField
										type="number"
										:model-value="plainValues[row.id][column.id]"
										@input="
											onChangeTextNumber(
												row.id,
												column.id,
												$event.target.value,
											)
										" />
								</template>
							</td>
						</tr>
					</tbody>
				</table>
			</fieldset>
		</template>

		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>

			<template v-else>
				<div>{{ t('forms', 'Columns') }}</div>
				<Draggable
					v-model="columns"
					class="question__content"
					animation="200"
					direction="vertical"
					handle=".option__drag-handle"
					invert-swap
					tag="ul"
					@change="saveOptionsOrder('column')"
					@start="isDragging = true"
					@end="isDragging = false">
					<TransitionGroup
						:name="
							isDragging
								? 'no-external-transition-on-drag'
								: 'options-list-transition'
						">
						<!-- Column input edit -->
						<AnswerInput
							v-for="(answer, index) in columns"
							:key="answer.local ? 'option-local' : answer.id"
							ref="input"
							:answer="answer"
							:form-id="formId"
							:index="index"
							:is-unique="isUnique"
							:max-index="columns.length - 2"
							:max-option-length="maxStringLengths.optionText"
							option-type="column"
							@create-answer="onCreateAnswer"
							@update:answer="updateAnswer"
							@delete="deleteOption"
							@focus-next="focusNextInput"
							@move-up="onOptionMoveUp(index, 'column')"
							@move-down="onOptionMoveDown(index, 'column')"
							@tabbed-out="checkValidOption('column')" />
					</TransitionGroup>
				</Draggable>

				<div>{{ t('forms', 'Rows') }}</div>
				<Draggable
					v-model="rows"
					class="question__content"
					animation="200"
					direction="vertical"
					handle=".option__drag-handle"
					invert-swap
					tag="ul"
					@change="saveOptionsOrder('row')"
					@start="isDragging = true"
					@end="isDragging = false">
					<TransitionGroup
						:name="
							isDragging
								? 'no-external-transition-on-drag'
								: 'options-list-transition'
						">
						<!-- Row input edit -->
						<AnswerInput
							v-for="(answer, index) in rows"
							:key="answer.local ? 'option-local' : answer.id"
							ref="input"
							:answer="answer"
							:form-id="formId"
							:index="index"
							:is-unique="isUnique"
							:max-index="rows.length - 2"
							:max-option-length="maxStringLengths.optionText"
							option-type="row"
							@create-answer="onCreateAnswer"
							@update:answer="updateAnswer"
							@delete="deleteOption"
							@focus-next="focusNextInput"
							@move-up="onOptionMoveUp(index, 'row')"
							@move-down="onOptionMoveDown(index, 'row')"
							@tabbed-out="checkValidOption('row')" />
					</TransitionGroup>
				</Draggable>
			</template>
		</template>
	</Question>
</template>

<script>
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import Draggable from 'vuedraggable'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import AnswerInput from './AnswerInput.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import QuestionMultipleMixin from '../../mixins/QuestionMultipleMixin.ts'
import { GridCellType, OptionType } from '../../models/Constants.ts'

export default {
	name: 'QuestionGrid',

	components: {
		AnswerInput,
		Draggable,
		NcCheckboxRadioSwitch,
		NcInputField,
		NcLoadingIcon,
		NcNoteCard,
	},

	mixins: [QuestionMixin, QuestionMultipleMixin],

	data() {
		return {
			/**
			 * The shown error message
			 */
			errorMessage: null,

			isDragging: false,
			isLoading: false,
			questionTypes: [
				{ label: t('forms', 'Radio'), id: GridCellType.Radio },
				{ label: t('forms', 'Checkbox'), id: GridCellType.Checkbox },
				{ label: t('forms', 'Number'), id: GridCellType.Number },
				{ label: t('forms', 'Text'), id: GridCellType.Text },
			],
		}
	},

	computed: {
		isUnique() {
			return this.answerType.unique === true
		},

		hasError() {
			return !!this.errorMessage
		},

		shiftDragHandle() {
			return !this.readOnly && this.options.length !== 0 && !this.isLastEmpty
		},

		titleId() {
			return `q${this.index}_title`
		},

		errorId() {
			return `q${this.index}_error`
		},

		questionType() {
			return this.extraSettings?.questionType ?? GridCellType.Radio
		},

		columns: {
			get() {
				return this.sortOptionsOfType(this.options, OptionType.Column)
			},

			set(value) {
				this.updateOptionsOrder(value, OptionType.Column)
			},
		},

		rows: {
			get() {
				return this.sortOptionsOfType(this.options, OptionType.Row)
			},

			set(value) {
				this.updateOptionsOrder(value, OptionType.Row)
			},
		},

		plainValues() {
			const values = {}
			for (const row of this.rows) {
				for (const column of this.columns) {
					values[row.id] = values[row.id] || {}
					values[row.id][column.id] =
						this.values[row.id]?.[column.id] ?? ''
				}
			}

			return values
		},
	},

	methods: {
		async validate() {
			if (!this.isUnique) {
				// Validate limits
				const max = this.extraSettings.optionsLimitMax ?? 0
				const min = this.extraSettings.optionsLimitMin ?? 0
				if (max && this.values.length > max) {
					this.errorMessage = n(
						'forms',
						'You must choose at most one option',
						'You must choose a maximum of %n options',
						max,
					)
					return false
				}
				if (min && this.values.length < min) {
					this.errorMessage = n(
						'forms',
						'You must choose at least one option',
						'You must choose at least %n options',
						min,
					)
					return false
				}
			}

			this.errorMessage = null
			return true
		},

		onChangeCheckboxRadio(rowId, value) {
			const values = { ...this.values }
			values[rowId] = value

			this.$emit('update:values', values)
		},

		onChangeTextNumber(rowId, columnId, value) {
			const values = { ...this.plainValues }
			values[rowId][columnId] = value

			this.$emit('update:values', values)
		},

		/**
		 * Is the provided answer required ?
		 * This is needed for checkboxes as html5
		 * doesn't allow to require at least ONE checked.
		 * So we require the one that are checked or all
		 * if none are checked yet.
		 *
		 * @return {boolean}
		 */
		checkRequired() {
			// false, if question not required
			if (!this.isRequired) {
				return false
			}

			// true for Radiobuttons
			if (this.isUnique) {
				return true
			}

			// For checkboxes, only required if no other is checked
			return this.areNoneChecked
		},
	},
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.question__item {
	position: relative;
	display: inline-flex;
	min-height: var(--default-clickable-area);

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -34px;
		inset-block-start: 1px;
		margin-inline-end: 10px !important;
		padding-inline-start: 36px !important;
	}

	.question__label {
		flex: 1 1 100%;
		// Overwrite guest page core styles
		text-align: start !important;
		// Some rounding issues lead to this strange number, so label and answerInput show up a the same position, working on different browsers.
		padding-block: 6.5px 0;
		padding-inline: 30px 0;
		line-height: 22px;
		min-height: 34px;
		height: min-content;
		position: relative;

		&::before {
			box-sizing: border-box;
			// Adjust position manually for proper position to text
			position: absolute;
			inset-block-start: 10px;
			width: 16px;
			height: 16px;
			margin-inline: -30px 14px !important;
			margin-block-end: 0;
		}
	}
}

.question__other-answer {
	display: flex;
	gap: 4px 16px;
	flex-wrap: wrap;

	.question__label {
		flex-basis: content;
	}

	.question__input {
		flex: 1;
		min-width: 260px;
	}

	.input-field__input {
		min-height: var(--default-clickable-area);
	}
}

.question__other-answer:deep() .input-field__input {
	min-height: var(--default-clickable-area);
}

.options-list-transition-move,
.options-list-transition-enter-active,
.options-list-transition-leave-active {
	transition: all var(--animation-slow) ease;
}

.options-list-transition-enter-from,
.options-list-transition-leave-to {
	opacity: 0;
	transform: translateX(44px);
}

/* ensure leaving items are taken out of layout flow so that moving
   animations can be calculated correctly. */
.options-list-transition-leave-active {
	position: absolute;
}

.answer-grid {
	border-collapse: collapse;
	width: 100%;

	thead tr {
		border-bottom: 2px solid var(--color-border);
	}

	td {
		min-height: 34px;
		min-width: 64px;
		text-align: center;
		padding: 8px 4px;

		.checkbox-radio-switch {
			display: flex;
			justify-content: center;
		}
	}

	th {
		min-height: 44px;
		padding: 8px 4px;
		text-align: center;
	}

	.first-column {
		min-width: 200px;
		text-align: left;
		position: sticky;
		left: 0;
	}
}
</style>
