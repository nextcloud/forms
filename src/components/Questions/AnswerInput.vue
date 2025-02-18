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
			:aria-label="t('forms', 'Answer number {index}', { index: index + 1 })"
			:placeholder="t('forms', 'Answer number {index}', { index: index + 1 })"
			:value="answer.text"
			class="question__input"
			:class="{ 'question__input--shifted': !isDropdown }"
			:maxlength="maxOptionLength"
			minlength="1"
			type="text"
			dir="auto"
			@input="debounceOnInput"
			@keydown.delete="deleteEntry"
			@keydown.enter.prevent="focusNextInput" />

		<!-- Delete answer -->
		<NcActions>
			<NcActionButton @click="deleteEntry">
				<template #icon>
					<IconClose :size="20" />
				</template>
				{{ t('forms', 'Delete answer') }}
			</NcActionButton>
		</NcActions>
	</li>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import pDebounce from 'p-debounce'
// eslint-disable-next-line import/no-unresolved, n/no-missing-import
import PQueue from 'p-queue'

import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconCheckboxBlankOutline from 'vue-material-design-icons/CheckboxBlankOutline.vue'
import IconRadioboxBlank from 'vue-material-design-icons/RadioboxBlank.vue'

import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import logger from '../../utils/Logger.js'

export default {
	name: 'AnswerInput',

	components: {
		IconClose,
		IconCheckboxBlankOutline,
		IconRadioboxBlank,
		NcActions,
		NcActionButton,
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
		}
	},

	computed: {
		pseudoIcon() {
			return this.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
		},
	},

	created() {
		this.queue = new PQueue({ concurrency: 1 })

		// As data instead of method, to have a separate debounce per AnswerInput
		this.debounceOnInput = pDebounce(() => {
			return this.queue.add(() => this.onInput())
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
			this.$refs.input.focus()
		},

		/**
		 * Option changed, processing the data
		 */
		async onInput() {
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
				this.$emit('update:answer', answer.id, newAnswer)
			} else {
				await this.updateAnswer(answer)
				this.$emit('update:answer', answer.id, answer)
			}
		},

		/**
		 * Request a new answer
		 */
		focusNextInput() {
			this.$emit('focus-next', this.index)
		},

		/**
		 * Emit a delete request for this answer
		 * when pressing the delete key on an empty input
		 *
		 * @param {Event} e the event
		 */
		async deleteEntry(e) {
			if (e.type !== 'click' && this.$refs.input.value.length !== 0) {
				return
			}

			// Dismiss delete key action
			e.preventDefault()

			this.$emit('delete', this.answer.id)
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
				return Object.assign({}, answer, OcsResponse2Data(response)[0])
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
	},
}
</script>

<style lang="scss" scoped>
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
		inset-inline-start: -12px;
		margin-inline-end: -12px !important;

		&--shifted {
			inset-inline-start: -34px;
			inset-block-start: 1px;
			margin-inline-end: -34px !important;
			padding-inline-start: 36px !important;
		}
	}
}
</style>
