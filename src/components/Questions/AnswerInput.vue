<!--
  - SPDX-FileCopyrightText: 2020 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="question__item" @focusout="handleTabbing">
		<div
			:is="pseudoIcon"
			v-if="!isDropdown"
			class="question__item__pseudoInput" />
		<input
			ref="input"
			:aria-label="ariaLabel"
			:placeholder="placeholder"
			:value="answer.text"
			class="question__input"
			:class="{ 'question__input--shifted': !isDropdown }"
			:maxlength="maxOptionLength"
			type="text"
			dir="auto"
			@input="debounceOnInput"
			@keydown.delete="deleteEntry"
			@keydown.enter.prevent="onEnter"
			@compositionstart="onCompositionStart"
			@compositionend="onCompositionEnd" />

		<!-- Actions for reordering and deleting the option  -->
		<div v-if="!answer.local" class="option__actions">
			<NcActions
				:id="optionDragMenuId"
				:container="`#${optionDragMenuId}`"
				:aria-label="t('forms', 'Move option actions')"
				class="option__drag-handle"
				variant="tertiary-no-background">
				<template #icon>
					<IconDragIndicator :size="20" />
				</template>
				<NcActionButton
					ref="buttonOptionUp"
					:disabled="index === 0"
					@click="onMoveUp">
					<template #icon>
						<IconArrowUp :size="20" />
					</template>
					{{ t('forms', 'Move option up') }}
				</NcActionButton>
				<NcActionButton
					ref="buttonOptionDown"
					:disabled="index === maxIndex"
					@click="onMoveDown">
					<template #icon>
						<IconArrowDown :size="20" />
					</template>
					{{ t('forms', 'Move option down') }}
				</NcActionButton>
			</NcActions>
			<NcButton
				:aria-label="t('forms', 'Delete answer')"
				variant="tertiary"
				@click="deleteEntry">
				<template #icon>
					<IconDelete :size="20" />
				</template>
			</NcButton>
		</div>
		<div v-else class="option__actions">
			<NcButton
				:aria-label="t('forms', 'Add a new answer option')"
				variant="tertiary"
				:disabled="isIMEComposing"
				@click="createLocalAnswer">
				<template #icon>
					<IconPlus :size="20" />
				</template>
			</NcButton>
		</div>
	</li>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import PQueue from 'p-queue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'
import IconTableColumn from 'vue-material-design-icons/TableColumn.vue'
import IconTableRow from 'vue-material-design-icons/TableRow.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'
import IconDragIndicator from '../Icons/IconDragIndicator.vue'
import { INPUT_DEBOUNCE_MS, OptionType } from '../../models/Constants.ts'
import logger from '../../utils/Logger.js'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'

export default {
	name: 'AnswerInput',

	components: {
		IconArrowDown,
		IconArrowUp,
		IconCheckboxBlankOutline,
		IconDelete,
		IconDragIndicator,
		IconPlus,
		IconRadioboxBlank,
		IconTableColumn,
		IconTableRow,
		NcActions,
		NcActionButton,
		NcButton,
	},

	props: {
		answer: {
			type: Object,
			required: true,
		},

		index: {
			type: Number,
			required: true,
		},

		formId: {
			type: Number,
			required: true,
		},

		isUnique: {
			type: Boolean,
			required: true,
		},

		isDropdown: {
			type: Boolean,
			default: false,
		},

		maxIndex: {
			type: Number,
			required: true,
		},

		maxOptionLength: {
			type: Number,
			required: true,
		},

		optionType: {
			type: String,
			required: true,
		},
	},

	emits: [
		'tabbed-out',
		'create-answer',
		'update:answer',
		'focus-next',
		'delete',
		'move-down',
		'move-up',
	],

	data() {
		return {
			queue: null,
			debounceOnInput: null,
			isIMEComposing: false,
		}
	},

	computed: {
		canCreateLocalAnswer() {
			return !!this.$refs.input?.value?.trim()
		},

		ariaLabel() {
			if (this.answer.local) {
				if (this.optionType === OptionType.Column) {
					return t('forms', 'Add a new column')
				}
				if (this.optionType === OptionType.Row) {
					return t('forms', 'Add a new row')
				}

				return t('forms', 'Add a new answer option')
			}

			if (this.optionType === OptionType.Column) {
				return t('forms', 'The text of column {index}', {
					index: this.index + 1,
				})
			}

			if (this.optionType === OptionType.Row) {
				return t('forms', 'The text of row {index}', {})
			}

			return t('forms', 'The text of option {index}', {
				index: this.index + 1,
			})
		},

		optionDragMenuId() {
			return `q${this.answer.questionId}o${this.answer.id}o${this.optionType}__drag_menu`
		},

		placeholder() {
			if (this.answer.local) {
				if (this.optionType === OptionType.Column) {
					return t('forms', 'Add a new column')
				}

				if (this.optionType === OptionType.Row) {
					return t('forms', 'Add a new row')
				}

				return t('forms', 'Add a new answer option')
			}

			if (this.optionType === OptionType.Column) {
				return t('forms', 'Column number {index}', { index: this.index + 1 })
			}

			if (this.optionType === OptionType.Row) {
				return t('forms', 'Row number {index}', { index: this.index + 1 })
			}

			return t('forms', 'Answer number {index}', { index: this.index + 1 })
		},

		pseudoIcon() {
			if (this.answer.local) {
				return IconPlus
			}

			if (this.optionType === OptionType.Column) {
				return IconTableColumn
			}

			if (this.optionType === OptionType.Row) {
				return IconTableRow
			}

			return this.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
		},
	},

	created() {
		this.queue = new PQueue({ concurrency: 1 })

		// As data instead of method, to have a separate debounce per AnswerInput
		this.debounceOnInput = debounce((event) => {
			return this.queue.add(() => this.onInput(event))
		}, INPUT_DEBOUNCE_MS)
	},

	methods: {
		handleTabbing() {
			this.$emit('tabbed-out', this.optionType)
		},

		/**
		 * Focus the input
		 */
		focus() {
			this.$refs.input?.focus()
		},

		/**
		 * Option changed, processing the data
		 *
		 * @param {InputEvent} event The input event that triggered adding a new entry
		 */
		async onInput({ target, isComposing }) {
			if (this.answer.local) {
				this.$set(this.answer, 'text', target.value)
				return
			}

			if (!isComposing && !this.isIMEComposing && target.value !== '') {
				// clone answer
				const answer = { ...this.answer }
				answer.text = this.$refs.input.value

				await this.updateAnswer(answer)

				// Forward changes, but use current answer.text to avoid erasing
				// any in-between changes while updating the answer
				answer.text = this.$refs.input.value
				this.$emit('update:answer', this.index, answer)
			}
		},

		/**
		 * Handle Enter key: create local answer or move focus
		 *
		 * @param {KeyboardEvent} e the keydown event
		 */
		onEnter(e) {
			if (this.answer.local) {
				this.createLocalAnswer(e)
				return
			}
			this.focusNextInput(e)
		},

		/**
		 * Create a new local answer option from the current input
		 *
		 * @param {Event} e the triggering event
		 */
		async createLocalAnswer(e) {
			if (this.isIMEComposing || e?.isComposing) {
				return
			}

			const value = this.$refs.input?.value ?? ''
			if (!value.trim()) {
				return
			}

			const answer = { ...this.answer }
			answer.text = value

			// Dispatched for creation. Marked as synced
			this.$set(this.answer, 'local', false)
			const newAnswer = await this.createAnswer(answer)

			// Forward changes, but use current answer.text to avoid erasing
			// any in-between changes while creating the answer
			newAnswer.text = this.$refs.input.value

			this.$emit('create-answer', this.index, newAnswer)
		},

		/**
		 * Request a new answer
		 */
		focusNextInput(e) {
			if (this.isIMEComposing || e?.isComposing) {
				return
			}
			if (this.index <= this.maxIndex) {
				this.$emit('focus-next', this.index, this.optionType)
			}
		},

		/**
		 * Emit a delete request for this answer
		 * when pressing the delete key on an empty input
		 *
		 * @param {Event} e the event
		 */
		async deleteEntry(e) {
			if (this.isIMEComposing || e?.isComposing) {
				return
			}

			if (this.answer.local) {
				return
			}

			if (e.type !== 'click' && this.$refs.input.value.length !== 0) {
				return
			}

			// Dismiss delete key action
			e.preventDefault()

			// do this in queue to prevent race conditions between PATCH and DELETE
			this.queue.add(() => {
				this.$emit('delete', this.answer)
				// Prevent any patch requests
				this.queue.pause()
				this.queue.clear()
			})
		},

		/**
		 * Create an unsynced answer to the server
		 *
		 * @param {object} answer the answer to sync
		 * @return {object} answer
		 */
		async createAnswer(answer) {
			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
						{
							id: this.formId,
							questionId: answer.questionId,
						},
					),
					{
						optionTexts: [answer.text],
						optionType: answer.optionType,
					},
				)
				logger.debug('Created answer', { answer })

				// Was synced once, this is now up to date with the server
				delete answer.local
				return OcsResponse2Data(response)[0]
			} catch (error) {
				logger.error('Error while saving answer', { answer, error })
				showError(t('forms', 'Error while saving the answer'))
			}

			return answer
		},

		/**
		 * Save to the server, only do it after 500ms
		 * of no change
		 *
		 * @param {object} answer the answer to sync
		 */
		async updateAnswer(answer) {
			try {
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options/{optionId}',
						{
							id: this.formId,
							questionId: answer.questionId,
							optionId: answer.id,
						},
					),
					{
						keyValuePairs: {
							text: answer.text,
						},
					},
				)
				logger.debug('Updated answer', { answer })
			} catch (error) {
				logger.error('Error while saving answer', { answer, error })
				showError(t('forms', 'Error while saving the answer'))
			}
		},

		/**
		 * Reorder option but keep focus on the button
		 */
		onMoveDown() {
			this.$emit('move-down')
			this.focusButton(
				this.index < this.maxIndex - 1
					? 'buttonOptionDown'
					: 'buttonOptionUp',
			)
		},

		onMoveUp() {
			this.$emit('move-up')
			this.focusButton(this.index > 1 ? 'buttonOptionUp' : 'buttonOptionDown')
		},

		focusButton(refName) {
			this.$nextTick(() => this.$refs[refName].$el.focus())
		},

		/**
		 * Handle composition start event for IME inputs
		 */
		onCompositionStart() {
			this.isIMEComposing = true
		},

		/**
		 * Handle composition end event for IME inputs
		 *
		 * @param {CompositionEvent} event The input event that triggered adding a new entry
		 */
		onCompositionEnd({ target, isComposing }) {
			this.isIMEComposing = false
			if (!isComposing) {
				this.onInput({ target, isComposing })
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.question__item {
	position: relative;
	display: inline-flex;
	min-height: var(--default-clickable-area);
	width: 100%;

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.option__actions {
		display: flex;
		position: absolute;
		gap: var(--default-grid-baseline);
		inset-inline-end: 12px;
		height: 100%;
	}

	.option__drag-handle,
	.drag-indicator-icon {
		color: var(--color-text-maxcontrast);
		cursor: grab;
		margin-block: auto;

		&:hover,
		&:focus,
		&:focus-within {
			color: var(--color-main-text);
		}

		&:active {
			cursor: grabbing;
		}

		> * {
			cursor: grab;
		}
	}

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -12px;
		margin-inline-end: -12px !important;

		&--shifted {
			inset-inline-start: calc(-1 * var(--default-clickable-area));
			padding-inline-start: calc(
				var(--default-clickable-area) + var(--default-grid-baseline)
			) !important;
		}
	}
}
</style>
