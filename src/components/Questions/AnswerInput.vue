<template>
	<li class="question__item">
		<div class="question__item__pseudoInput"
			:class="{
				'question__item__pseudoInput--unique':isUnique,
				'question__item__pseudoInput--dropdown':isDropdown
			}" />
		<input ref="input"
			:aria-label="t('forms', 'An answer for the {index} option', { index: index + 1 })"
			:placeholder="t('forms', 'Answer number {index}', { index: index + 1 })"
			:value="answer.text"
			class="question__input"
			:maxlength="maxOptionLength"
			minlength="1"
			type="text"
			@input="onInput"
			@keydown.delete="deleteEntry"
			@keydown.enter.prevent="addNewEntry">

		<!-- Delete answer -->
		<Actions>
			<ActionButton icon="icon-close" @click="deleteEntry">
				{{ t('forms', 'Delete answer') }}
			</ActionButton>
		</Actions>
	</li>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import pDebounce from 'p-debounce'
// eslint-disable-next-line import/no-unresolved, node/no-missing-import
import PQueue from 'p-queue'

import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

import OcsResponse2Data from '../../utils/OcsResponse2Data'

export default {
	name: 'AnswerInput',

	components: {
		Actions,
		ActionButton,
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
			queue: new PQueue({ concurrency: 1 }),

			// As data instead of Method, to have a separate debounce per AnswerInput
			debounceUpdateAnswer: pDebounce(function(answer) {
				return this.queue.add(() => this.updateAnswer(answer))
			}, 500),
		}
	},

	methods: {
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
				// eslint-disable-next-line vue/no-mutating-props
				this.answer.local = false
				const newAnswer = await this.debounceCreateAnswer(answer)

				// Forward changes, but use current answer.text to avoid erasing
				// any in-between changes while creating the answer
				Object.assign(newAnswer, { text: this.$refs.input.value })
				this.$emit('update:answer', answer.id, newAnswer)
			} else {
				this.debounceUpdateAnswer(answer)
				this.$emit('update:answer', answer.id, answer)
			}
		},

		/**
		 * Request a new answer
		 */
		addNewEntry() {
			this.$emit('add')
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2/option'), {
					questionId: answer.questionId,
					text: answer.text,
				})
				console.debug('Created answer', answer)

				// Was synced once, this is now up to date with the server
				delete answer.local
				return Object.assign({}, answer, OcsResponse2Data(response))
			} catch (error) {
				showError(t('forms', 'Error while saving the answer'))
				console.error(error)
			}

			return answer
		},
		debounceCreateAnswer: pDebounce(function(answer) {
			return this.queue.add(() => this.createAnswer(answer))
		}, 100),

		/**
		 * Save to the server, only do it after 500ms
		 * of no change
		 *
		 * @param {object} answer the answer to sync
		 */
		async updateAnswer(answer) {
			try {
				await axios.post(generateOcsUrl('apps/forms/api/v2/option/update'), {
					id: this.answer.id,
					keyValuePairs: {
						text: answer.text,
					},
				})
				console.debug('Updated answer', answer)
			} catch (error) {
				showError(t('forms', 'Error while saving the answer'))
				console.error(error)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.question__item {
	position: relative;
	display: inline-flex;
	min-height: 44px;

	// Taking styles from server radio-input items
	&__pseudoInput {
		flex-shrink: 0;
		display: inline-block;
		height: 16px;
		width: 16px !important;
		vertical-align: middle;
		margin: 0 14px 0px 0px;
		border: 1px solid #878787;
		border-radius: 1px;
		// Adjust position manually to match input-checkbox
		position: relative;
		top: 10px;

		// Show round for Pseudo-Radio-Button
		&--unique {
			border-radius: 50%;
		}

		// Do not show pseudo-icon for dropdowns
		&--dropdown {
			display: none;
		}

		&:hover {
			border-color: var(--color-primary-element);
		}
	}
}

// Using type to have a higher order than the input styling of server
.question__input[type=text] {
	width: 100%;
	// Height 34px + 1px Border
	min-height: 35px;
	margin: 0;
	padding: 0 0;
	border: 0;
	border-bottom: 1px dotted var(--color-border-dark);
	border-radius: 0;
	font-size: 14px;
	position: relative;
}

</style>
