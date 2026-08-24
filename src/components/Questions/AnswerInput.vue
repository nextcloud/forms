<!--
  - SPDX-FileCopyrightText: 2020 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="question__item" @focusout="handleTabbing">
		<NcIconSvgWrapper
			v-if="!isDropdown"
			:svg="pseudoIcon"
			inline
			class="question__item__pseudoInput" />
		<input
			ref="input"
			v-model="localText"
			:aria-label="ariaLabel"
			:placeholder="placeholder"
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
					<NcIconSvgWrapper :svg="IconDragIndicator" />
				</template>
				<NcActionButton
					ref="buttonOptionUp"
					:disabled="index === 0"
					@click="onMoveUp">
					<template #icon>
						<NcIconSvgWrapper :svg="IconArrowUp" />
					</template>
					{{ t('forms', 'Move option up') }}
				</NcActionButton>
				<NcActionButton
					ref="buttonOptionDown"
					:disabled="index === maxIndex"
					@click="onMoveDown">
					<template #icon>
						<NcIconSvgWrapper :svg="IconArrowDown" />
					</template>
					{{ t('forms', 'Move option down') }}
				</NcActionButton>
			</NcActions>
			<NcButton
				:aria-label="t('forms', 'Delete answer')"
				variant="tertiary"
				@click="deleteEntry">
				<template #icon>
					<NcIconSvgWrapper :svg="IconDelete" />
				</template>
			</NcButton>
		</div>
		<div v-else class="option__actions">
			<NcButton
				:aria-label="t('forms', 'Add a new answer option')"
				variant="tertiary"
				:disabled="isIMEComposing || !canCreateLocalAnswer"
				@click="createLocalAnswer">
				<template #icon>
					<NcIconSvgWrapper :svg="IconPlus" />
				</template>
			</NcButton>
		</div>
	</li>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsOption } from '../../models/Entities.d.ts'

import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconCheckboxBlankOutline from '@material-symbols/svg-400/outlined/check_box_outline_blank.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconDragIndicator from '@material-symbols/svg-400/outlined/drag_indicator.svg?raw'
import IconArrowDown from '@material-symbols/svg-400/outlined/keyboard_arrow_down.svg?raw'
import IconArrowUp from '@material-symbols/svg-400/outlined/keyboard_arrow_up.svg?raw'
import IconRadioboxBlank from '@material-symbols/svg-400/outlined/radio_button_unchecked.svg?raw'
import IconTableColumn from '@material-symbols/svg-400/outlined/view_column.svg?raw'
import IconTableRow from '@material-symbols/svg-400/outlined/view_stream.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import PQueue from 'p-queue'
import { computed, defineComponent, markRaw, nextTick, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { INPUT_DEBOUNCE_MS, OptionType } from '../../models/Constants.ts'
import logger from '../../utils/Logger.ts'
import OcsResponse2Data from '../../utils/OcsResponse2Data.ts'

export default defineComponent({
	name: 'AnswerInput',

	components: {
		NcIconSvgWrapper,
		NcActions,
		NcActionButton,
		NcButton,
	},

	props: {
		answer: {
			type: Object as PropType<FormsOption>,
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

		isRanking: {
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
		'tabbedOut',
		'createAnswer',
		'update:answer',
		'focusNext',
		'delete',
		'moveDown',
		'moveUp',
	],

	setup(props, { emit }) {
		// markRaw: PQueue relies on private class fields, which break when Vue wraps it in a reactive proxy
		const queue = ref(markRaw(new PQueue({ concurrency: 1 })))
		const input = ref<HTMLInputElement | null>(null)
		const buttonOptionDown = ref<{ $el?: HTMLElement } | null>(null)
		const buttonOptionUp = ref<{ $el?: HTMLElement } | null>(null)
		const isIMEComposing = ref(false)
		const answer = computed(() => props.answer)
		const localText = ref(answer.value.text ?? '')

		const getInputTarget = (
			event: Event | { target: EventTarget | null },
		): HTMLInputElement | null => {
			return event.target instanceof HTMLInputElement ? event.target : null
		}

		const canCreateLocalAnswer = computed(() => {
			if (answer.value.local) {
				return !!localText.value.trim()
			}
			return !!answer.value.text?.trim()
		})

		const ariaLabel = computed(() => {
			if (answer.value.local) {
				if (props.optionType === OptionType.Column) {
					return t('forms', 'Add a new column')
				}
				if (props.optionType === OptionType.Row) {
					return t('forms', 'Add a new row')
				}

				return t('forms', 'Add a new answer option')
			}

			if (props.optionType === OptionType.Column) {
				return t('forms', 'The text of column {index}', {
					index: props.index + 1,
				})
			}

			if (props.optionType === OptionType.Row) {
				return t('forms', 'The text of row {index}', {
					index: props.index + 1,
				})
			}

			return t('forms', 'The text of option {index}', {
				index: props.index + 1,
			})
		})

		const optionDragMenuId = computed(() => {
			return `q${answer.value.questionId}o${answer.value.id}o${props.optionType}__drag_menu`
		})

		const placeholder = computed(() => {
			if (answer.value.local) {
				if (props.optionType === OptionType.Column) {
					return t('forms', 'Add a new column')
				}

				if (props.optionType === OptionType.Row) {
					return t('forms', 'Add a new row')
				}

				return t('forms', 'Add a new answer option')
			}

			if (props.optionType === OptionType.Column) {
				return t('forms', 'Column number {index}', {
					index: props.index + 1,
				})
			}

			if (props.optionType === OptionType.Row) {
				return t('forms', 'Row number {index}', { index: props.index + 1 })
			}

			return t('forms', 'Answer number {index}', { index: props.index + 1 })
		})

		const pseudoIcon = computed(() => {
			if (answer.value.local) {
				return IconPlus
			}

			if (props.optionType === OptionType.Column) {
				return IconTableColumn
			}

			if (props.optionType === OptionType.Row) {
				return IconTableRow
			}

			if (props.isRanking) {
				return IconDragIndicator
			}

			return props.isUnique ? IconRadioboxBlank : IconCheckboxBlankOutline
		})

		// Keep localText in sync when the parent replaces/updates the answer prop
		watch(
			() => props.answer,
			(newVal: FormsOption) => {
				localText.value = newVal?.text ?? ''
			},
			{ deep: true },
		)

		// As data instead of method, to have a separate debounce per AnswerInput
		const debounceOnInput = debounce((event: InputEvent) => {
			void queue.value.add(() => onInput(event))
		}, INPUT_DEBOUNCE_MS)

		const handleTabbing = (): void => {
			emit('tabbedOut', props.optionType)
		}

		/**
		 * Focus the current answer input.
		 */
		const focus = (): void => {
			input.value?.focus()
		}

		/**
		 * Handle text input change from the form field.
		 *
		 * @param event The input event that triggered the save.
		 */
		async function onInput(
			event: InputEvent | { target: HTMLInputElement; isComposing?: boolean },
		): Promise<void> {
			const target = getInputTarget(event)
			if (!target) {
				return
			}

			if (answer.value.local) {
				localText.value = target.value
				return
			}

			if (!event.isComposing && !isIMEComposing.value && target.value !== '') {
				// clone answer
				const answerCopy = { ...answer.value }
				if (!input.value) {
					return
				}
				answerCopy.text = input.value.value

				await updateAnswer(answerCopy)

				// Forward changes, but use current answer.text to avoid erasing
				// any in-between changes while updating the answer
				answerCopy.text = input.value.value
				emit('update:answer', props.index, answerCopy)
			}
		}

		/**
		 * Handle Enter key: create local answer or move focus
		 *
		 * @param e The keyboard event.
		 */
		const onEnter = (e: KeyboardEvent): void => {
			if (answer.value.local) {
				void createLocalAnswer(e)
				return
			}
			focusNextInput(e)
		}

		/**
		 * Create a new local answer option from the current input value.
		 *
		 * @param e The triggering event, if any.
		 */
		async function createLocalAnswer(
			e?: Event & { isComposing?: boolean },
		): Promise<void> {
			if (isIMEComposing.value || e?.isComposing) {
				return
			}

			const value = localText.value ?? ''
			if (!value.trim()) {
				return
			}

			const answerValue = {
				...answer.value,
				text: value,
				local: false,
			}

			queue.value.pause()
			try {
				// Forward changes, but use current answer.text to avoid erasing
				// any in-between changes while creating the answer
				const newAnswer = await createAnswer(answerValue)
				if (!input.value) {
					return
				}
				newAnswer.text = input.value.value
				localText.value = ''
				emit('createAnswer', props.index, newAnswer)
			} finally {
				// Clear pending update tasks (stale PATCHes) before resuming processing
				queue.value.clear()
				queue.value.start()
			}
		}

		/**
		 * Move focus to the next answer input when Enter is pressed.
		 *
		 * @param e The keyboard event.
		 */
		function focusNextInput(e: Event & { isComposing?: boolean }): void {
			if (isIMEComposing.value || e?.isComposing) {
				return
			}
			if (props.index <= props.maxIndex) {
				emit('focusNext', props.index, props.optionType)
			}
		}

		/**
		 * Remove the current option when the delete action is triggered.
		 *
		 * @param e The input or button event.
		 */
		const deleteEntry = async (
			e: Event & { isComposing?: boolean; type: string },
		): Promise<void> => {
			if (isIMEComposing.value || e?.isComposing) {
				return
			}

			if (answer.value.local) {
				return
			}

			if (e.type !== 'click' && (input.value?.value.length ?? 0) !== 0) {
				return
			}

			// Dismiss delete key action
			e.preventDefault()
			void queue.value.add(() => {
				emit('delete', props.answer)
				queue.value.pause()
				queue.value.clear()
			})
		}

		/**
		 * Save a newly created option to the server.
		 *
		 * @param answer The answer payload to create.
		 * @return The saved server response item.
		 */
		async function createAnswer(answer: FormsOption): Promise<FormsOption> {
			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options',
						{
							id: props.formId,
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
				return (OcsResponse2Data(response) as FormsOption[])[0]
			} catch (error) {
				logger.error('Error while saving answer', { answer, error })
				showError(t('forms', 'Error while saving the answer'))
			}

			return answer
		}

		/**
		 * Persist a changed answer text to the server after debounce.
		 *
		 * @param answer The updated answer payload.
		 */
		async function updateAnswer(answer: FormsOption): Promise<void> {
			try {
				await axios.patch(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/questions/{questionId}/options/{optionId}',
						{
							id: props.formId,
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
		}

		/**
		 * Move the current answer down in the list.
		 */
		const onMoveDown = (): void => {
			emit('moveDown')
			focusButton(
				props.index < props.maxIndex - 1
					? 'buttonOptionDown'
					: 'buttonOptionUp',
			)
		}

		/**
		 * Move the current answer up in the list.
		 */
		const onMoveUp = (): void => {
			emit('moveUp')
			focusButton(props.index > 1 ? 'buttonOptionUp' : 'buttonOptionDown')
		}

		/**
		 * Restore focus to the move button after reordering.
		 *
		 * @param refName The target button reference name.
		 */
		function focusButton(refName: 'buttonOptionDown' | 'buttonOptionUp'): void {
			nextTick(() => {
				const button =
					refName === 'buttonOptionDown'
						? buttonOptionDown.value
						: buttonOptionUp.value
				button?.$el?.focus()
			})
		}

		/**
		 * Track the start of an IME composition sequence.
		 */
		const onCompositionStart = (): void => {
			isIMEComposing.value = true
		}

		/**
		 * Flush a pending input after IME composition ends.
		 *
		 * @param event The composition event.
		 */
		const onCompositionEnd = (
			event: CompositionEvent & { isComposing?: boolean },
		): void => {
			const target = getInputTarget(event)
			isIMEComposing.value = false
			if (!event.isComposing && target) {
				void onInput({ target, isComposing: event.isComposing })
			}
		}

		return {
			IconArrowDown,
			IconArrowUp,
			IconDelete,
			IconDragIndicator,
			IconPlus,
			buttonOptionDown,
			buttonOptionUp,
			canCreateLocalAnswer,
			ariaLabel,
			optionDragMenuId,
			placeholder,
			pseudoIcon,
			input,
			localText,
			isIMEComposing,
			debounceOnInput,
			handleTabbing,
			focus,
			onEnter,
			createLocalAnswer,
			deleteEntry,
			onMoveDown,
			onMoveUp,
			onCompositionStart,
			onCompositionEnd,
			t,
		}
	},
})
</script>

<style lang="scss" scoped>
.question__item {
	position: relative;
	display: inline-flex;
	min-height: var(--default-clickable-area);
	width: 100%;

	&__pseudoInput {
		color: var(--color-primary-element);
		margin-inline-start: 2px;
		z-index: 1;
	}

	.option__actions {
		display: flex;
		position: absolute;
		gap: var(--default-grid-baseline);
		inset-inline-end: 12px;
		height: 100%;
	}

	.option__drag-handle {
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
