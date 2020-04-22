<template>
	<li class="question__item">
		<!-- TODO: properly choose max length -->
		<input
			ref="input"
			:aria-label="t('forms', 'An answer for the {index} option', { index: index + 1 })"
			:placeholder="t('forms', 'Answer number {index}', { index: index + 1 })"
			:value="answer.text"
			class="question__input"
			maxlength="256"
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
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import pDebounce from 'p-debounce'

import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

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
			const answer = Object.assign({}, this.answer)
			answer.text = this.$refs.input.value

			if (answer.local) {
				await this.createAnswer(answer)
			} else {
				this.updateAnswer(answer)
			}

			// Update question
			this.$emit('update:answer', answer)
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
			const answer = Object.assign({}, this.answer)
			const index = this.index

			// let's not await, deleting in background
			axios.delete(generateUrl('/apps/forms/api/v1/option/{id}', { id: this.answer.id }))
				.catch(error => {
					showError(t('forms', 'There was an issue deleting this option'))
					console.error(error)
					// restore option
					this.$emit('restore', answer, index)
				})

			this.$emit('delete', this.answer.id, this.index)
		},

		/**
		 * Create an unsynced answer to the server
		 *
		 * @param {Object} answer the answer to sync
		 */
		createAnswer: pDebounce(async function(answer) {
			try {
				const response = await axios.post(generateUrl('/apps/forms/api/v1/option'), {
					questionId: answer.question_id,
					text: answer.text,
				})

				// Was synced once, this is now up to date with the server
				delete answer.local
				answer.id = response.data.id
				console.debug('Created answer', answer)
			} catch (error) {
				showError(t('forms', 'Error while saving the answer'))
				console.error(error)
			}
		}, 100),

		/**
		 * Save to the server, only do it after 500ms
		 * of no change
		 *
		 * @param {Object} answer the answer to sync
		 */
		updateAnswer: pDebounce(async function(answer) {
			try {
				await axios.post(generateUrl('/apps/forms/api/v1/option/update'), {
					id: this.answer.id,
					keyValuePairs: {
						text: answer.text,
					},
				})
			} catch (error) {
				showError(t('forms', 'Error while saving the answer'))
				console.error(error)
			}
		}, 500),
	},
}
</script>

<style lang="scss" scoped>

</style>
