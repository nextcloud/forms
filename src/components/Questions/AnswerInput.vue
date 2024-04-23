<template>
	<li class="question__item" @focusout="handleTabbing">
		<div :is="pseudoIcon"
			v-if="!isDropdown"
			class="question__item__pseudoInput" />
		<input ref="input"
			v-model="localAnswer.text"
			:aria-label="ariaLabel"
			:placeholder="placeholder"
			class="question__input"
			:class="{ 'question__input--shifted' : !isDropdown }"
			:maxlength="maxOptionLength"
			type="text"
			dir="auto"
			@input="onInput"
			@keydown.delete="deleteEntry"
			@keydown.enter.prevent="focusNextInput">

		<!-- Actions for reordering and deleting the option  -->
		<div class="option__actions">
			<template v-if="!answer.local">
				<template v-if="allowReorder">
					<NcButton ref="buttonUp"
						:aria-label="t('forms', 'Move option up')"
						:disabled="index === 0"
						type="tertiary"
						@click="onMoveUp">
						<template #icon>
							<IconArrowUp :size="20" />
						</template>
					</NcButton>
					<NcButton ref="buttonDown"
						:aria-label="t('forms', 'Move option down')"
						:disabled="index === maxIndex"
						type="tertiary"
						@click="onMoveDown">
						<template #icon>
							<IconArrowDown :size="20" />
						</template>
					</NcButton>
				</template>
				<NcButton type="tertiary"
					:aria-label="t('forms', 'Delete answer')"
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

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
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
		allowReorder: {
			type: Boolean,
			default: true,
		},
		index: {
			type: Number,
			required: true,
		},
		maxIndex: {
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
			localAnswer: this.answer,
			queue: new PQueue({ concurrency: 1 }),
		}
	},

	computed: {
		ariaLabel() {
			if (this.local) {
				return t('forms', 'Add a new answer option')
			}
			return t('forms', 'The text of option {index}', { index: this.index + 1 })
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

		onInput() {
			return debounce(() => this.queue.add(this.handleInput), 150)
		},
	},

	watch: {
		answer() {
			this.localAnswer = { ...this.answer }
			// If this component is recycled but was stopped previously (delete of option) - then we need to restart the queue
			this.queue.start()
		},
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
		 */
		async handleInput() {
			let response
			if (this.localAnswer.local) {
				response = await this.createAnswer(this.localAnswer)
			} else {
				response = await this.updateAnswer(this.localAnswer)
			}

			// Forward changes, but use current answer.text to avoid erasing any in-between changes
			this.localAnswer = { ...response, text: this.localAnswer.text }
			this.$emit('update:answer', this.localAnswer)
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2.4/option'), {
					questionId: answer.questionId,
					order: answer.order ?? this.maxIndex,
					text: answer.text,
				})
				logger.debug('Created answer', { answer })

				// Was synced once, this is now up to date with the server
				delete answer.local
				return { ...answer, ...OcsResponse2Data(response) }
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
				await axios.patch(generateOcsUrl('apps/forms/api/v2.4/option/update'), {
					id: this.answer.id,
					keyValuePairs: {
						text: answer.text,
					},
				})
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
	},
}
</script>

<style lang="scss" scoped>
.question__item {
	position: relative;
	display: inline-flex;
	min-height: 44px;
	width: 100%;

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: -2px;
		z-index: 1;
	}

	.option__actions {
		display: flex;
		// make sure even the "add new" option is aligned correctly
		min-width: 44px;
	}

	.question__input {
		width: 100%;
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
