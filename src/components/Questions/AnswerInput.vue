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
			@keydown.enter.prevent="focusNextInput"
			@compositionstart="onCompositionEnd"
			@compositionend="onCompositionEnd" />

		<!-- Actions for reordering and deleting the option  -->
		<div class="option__actions">
			<template v-if="!answer.local">
				<NcButton
					ref="buttonUp"
					class="option__actions-button"
					:aria-label="t('forms', 'Move option up')"
					:disabled="index === 0"
					size="small"
					type="tertiary"
					@click="onMoveUp">
					<template #icon>
						<IconArrowUp :size="20" />
					</template>
				</NcButton>
				<NcButton
					ref="buttonDown"
					class="option__actions-button"
					:aria-label="t('forms', 'Move option down')"
					:disabled="index === maxIndex"
					size="small"
					type="tertiary"
					@click="onMoveDown">
					<template #icon>
						<IconArrowDown :size="20" />
					</template>
				</NcButton>
				<NcButton
					class="option__actions-button"
					:aria-label="t('forms', 'Delete answer')"
					size="small"
					type="tertiary"
					@click="deleteEntry">
					<template #icon>
						<IconDelete :size="20" />
					</template>
				</NcButton>
			</template>
		</div>
	</li>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import PQueue from 'p-queue'

import NcButton from '@nextcloud/vue/components/NcButton'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'

import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import logger from '../../utils/Logger.js'

export default {
	name: 'AnswerInput',

	components: {
		IconArrowDown,
		IconArrowUp,
		IconCheckboxBlankOutline,
		IconDelete,
		IconRadioboxBlank,
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
	},

	data() {
		return {
			queue: null,
			debounceOnInput: null,
			isIMEComposing: false,
		}
	},

	computed: {
		ariaLabel() {
			if (this.answer.local) {
				return t('forms', 'Add a new answer option')
			}
			return t('forms', 'The text of option {index}', {
				index: this.index + 1,
			})
		},

		placeholder() {
			if (this.answer.local) {
				return t('forms', 'Add a new answer option')
			}
			return t('forms', 'Answer number {index}', { index: this.index + 1 })
		},

		pseudoIcon() {
			return this.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
		},
	},

	created() {
		this.queue = new PQueue({ concurrency: 1 })

		// As data instead of method, to have a separate debounce per AnswerInput
		this.debounceOnInput = debounce((event) => {
			return this.queue.add(() => this.onInput(event))
		}, 500)
	},

	methods: {
		handleTabbing() {
			this.$emit('tabbed-out')
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
			if (!isComposing && !this.isIMEComposing && target.value !== '') {
				// clone answer
				const answer = Object.assign({}, this.answer)
				answer.text = this.$refs.input.value

				if (this.answer.local) {
					// Dispatched for creation. Marked as synced
					this.$set(this.answer, 'local', false)
					const newAnswer = await this.createAnswer(answer)

					// Forward changes, but use current answer.text to avoid erasing
					// any in-between changes while creating the answer
					newAnswer.text = this.$refs.input.value

					this.$emit('create-answer', this.index, newAnswer)
				} else {
					await this.updateAnswer(answer)
					this.$emit('update:answer', this.index, answer)
				}
			}
		},

		/**
		 * Request a new answer
		 */
		focusNextInput() {
			if (this.index <= this.maxIndex) {
				this.$emit('focus-next', this.index)
			}
		},

		/**
		 * Emit a delete request for this answer
		 * when pressing the delete key on an empty input
		 *
		 * @param {Event} e the event
		 */
		async deleteEntry(e) {
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
				this.$emit('delete', this.answer.id)
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
			return answer
		},

		/**
		 * Reorder option but keep focus on the button
		 */
		onMoveDown() {
			this.$emit('move-down')
			if (this.index < this.maxIndex - 1) {
				this.$nextTick(() => this.$refs.buttonDown.$el.focus())
			} else {
				this.$nextTick(() => this.$refs.buttonUp.$el.focus())
			}
		},
		onMoveUp() {
			this.$emit('move-up')
			if (this.index > 1) {
				this.$nextTick(() => this.$refs.buttonUp.$el.focus())
			} else {
				this.$nextTick(() => this.$refs.buttonDown.$el.focus())
			}
		},

		/**
		 * Handle composition start event for IME inputs
		 */
		onCompositionStart() {
			this.isIMEComposing = true
		},

		/**
		 * Handle composition end event for IME inputs
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
		margin-inline-start: calc(-1 * var(--default-grid-baseline));
		z-index: 1;
	}

	.option__actions {
		display: flex;
		position: absolute;
		gap: var(--default-grid-baseline);
		inset-inline-end: 16px;
		height: var(--default-clickable-area);
	}

	.option__actions-button {
		margin-block: auto;

		&:last-of-type {
			margin-inline: 5px;
		}
	}

	.question__input {
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -12px;
		margin-block: 0 !important;
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
