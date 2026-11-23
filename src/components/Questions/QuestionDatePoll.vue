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
		:shiftDragHandle="shiftDragHandle"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:modelValue="!!extraSettings.allowMaybe"
				@update:modelValue="onAllowMaybeChange">
				{{ t('forms', 'Allow "maybe"') }}
			</NcActionCheckbox>
			<NcActionCheckbox
				:modelValue="!!extraSettings.yesLimitPerPerson"
				@update:modelValue="
					(checked) => onYesLimit('yesLimitPerPerson', checked ? 1 : null)
				">
				{{ t('forms', 'Limit yes votes per person') }}
			</NcActionCheckbox>
			<NcActionInput
				v-if="extraSettings.yesLimitPerPerson"
				type="number"
				:label="t('forms', 'Yes votes per person')"
				:labelOutside="false"
				:showTrailingButton="false"
				:modelValue="extraSettings.yesLimitPerPerson"
				@update:modelValue="
					(value) => onYesLimit('yesLimitPerPerson', value)
				" />
			<NcActionCheckbox
				:modelValue="!!extraSettings.yesLimitPerSlot"
				@update:modelValue="
					(checked) => onYesLimit('yesLimitPerSlot', checked ? 1 : null)
				">
				{{ t('forms', 'Limit yes votes per slot') }}
			</NcActionCheckbox>
			<NcActionInput
				v-if="extraSettings.yesLimitPerSlot"
				type="number"
				:label="t('forms', 'Yes votes per slot')"
				:labelOutside="false"
				:showTrailingButton="false"
				:modelValue="extraSettings.yesLimitPerSlot"
				@update:modelValue="
					(value) => onYesLimit('yesLimitPerSlot', value)
				" />
		</template>

		<div
			v-if="readOnly"
			class="question__content"
			role="group"
			:aria-labelledby="titleId">
			<div
				v-for="slot in savedSlots"
				:key="slot.id"
				class="datepoll-slot"
				role="radiogroup"
				:aria-label="formatSlot(slot.text)">
				<span class="datepoll-slot__text">{{ formatSlot(slot.text) }}</span>
				<div class="datepoll-slot__votes">
					<NcCheckboxRadioSwitch
						type="radio"
						:name="`datepoll-${id}-${slot.id}`"
						value="yes"
						:modelValue="votes[String(slot.id)]"
						@update:modelValue="onVote(slot.id, 'yes')">
						{{ t('forms', 'Yes') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						type="radio"
						:name="`datepoll-${id}-${slot.id}`"
						value="no"
						:modelValue="votes[String(slot.id)]"
						@update:modelValue="onVote(slot.id, 'no')">
						{{ t('forms', 'No') }}
					</NcCheckboxRadioSwitch>
					<NcCheckboxRadioSwitch
						v-if="extraSettings.allowMaybe"
						type="radio"
						:name="`datepoll-${id}-${slot.id}`"
						value="maybe"
						:modelValue="votes[String(slot.id)]"
						@update:modelValue="onVote(slot.id, 'maybe')">
						{{ t('forms', 'Maybe') }}
					</NcCheckboxRadioSwitch>
				</div>
			</div>
		</div>

		<template v-else>
			<Draggable
				v-model="choices"
				class="question__content"
				:animation="300"
				direction="vertical"
				handle=".option__drag-handle"
				invertSwap
				target=".sort-target"
				@update="dirtyOptionsType = 'choice'"
				@start="onDragStart"
				@end="onDragEnd">
				<TransitionGroup
					tag="ul"
					:name="isDragging ? undefined : 'options-list-transition'"
					class="sort-target">
					<AnswerInput
						v-for="(answer, index) in choices"
						:key="answer.local ? 'option-local' : answer.id"
						ref="input"
						:answer="answer"
						:formId="formId"
						:index="index"
						:isUnique="true"
						:maxIndex="options.length - 1"
						:maxOptionLength="maxStringLengths.optionText"
						inputKind="datetime"
						optionType="choice"
						@createAnswer="onCreateAnswer"
						@update:answer="updateAnswer"
						@delete="deleteOption"
						@focusNext="focusNextInput"
						@moveUp="onOptionMoveUp(index, OptionType.Choice)"
						@moveDown="onOptionMoveDown(index, OptionType.Choice)"
						@tabbedOut="checkValidOption" />
				</TransitionGroup>
			</Draggable>
		</template>

		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import type { FormsOption } from '../../types/Entities.d.ts'

import { n, t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, ref, watch } from 'vue'
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import AnswerInput from './AnswerInput.vue'
import Question from './Question.vue'
import {
	QUESTION_EMITS,
	QUESTION_PROPS,
	useQuestion,
} from '../../composables/useQuestion.ts'
import {
	QUESTION_MULTIPLE_EMITS,
	useQuestionMultiple,
} from '../../composables/useQuestionMultiple.ts'
import { OptionType } from '../../models/Constants.ts'

type DatePollExtraSettings = {
	allowMaybe?: boolean
	yesLimitPerPerson?: number | null
	yesLimitPerSlot?: number | null
}

export default defineComponent({
	name: 'QuestionDatePoll',

	components: {
		AnswerInput,
		Draggable,
		NcActionCheckbox,
		NcActionInput,
		NcCheckboxRadioSwitch,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, ...QUESTION_MULTIPLE_EMITS],

	setup(props, { emit }) {
		const rootElement = ref<{ $el?: HTMLElement } | null>(null)
		const question = useQuestion(props, { emit, rootElement })
		const input = ref<
			Array<{
				focus?: () => void
				$props?: { optionType?: string; index?: number }
			} | null>
		>([])
		const questionMultiple = useQuestionMultiple(props, { emit, input })
		const isDragging = ref(false)
		const extraSettings = computed<DatePollExtraSettings>(() => {
			return (props.extraSettings as DatePollExtraSettings | undefined) ?? {}
		})

		const shiftDragHandle = computed(() => {
			return (
				!props.readOnly
				&& props.options?.length !== 0
				&& !questionMultiple.isLastEmpty.value
			)
		})

		const choices = computed<FormsOption[]>({
			get() {
				return questionMultiple.sortOptionsOfType(
					props.options,
					OptionType.Choice,
				)
			},

			set(value: FormsOption[]) {
				questionMultiple.updateOptionsOrder(value, OptionType.Choice)
			},
		})

		const savedSlots = computed(() => {
			return choices.value.filter((slot) => !slot.local)
		})

		const allowedVotes = computed(() => {
			return extraSettings.value.allowMaybe
				? ['yes', 'no', 'maybe']
				: ['yes', 'no']
		})

		const storedVotes = computed<Record<string, string>>(() => {
			const value = props.values
			if (!value || Array.isArray(value)) {
				return {}
			}
			return value as Record<string, string>
		})

		/**
		 * Votes for slots that still exist, with only the votes the question allows.
		 */
		const votes = computed<Record<string, string>>(() => {
			const slotIds = new Set(savedSlots.value.map((slot) => String(slot.id)))
			return Object.fromEntries(
				Object.entries(storedVotes.value).filter(
					([slotId, vote]) =>
						slotIds.has(slotId) && allowedVotes.value.includes(vote),
				),
			)
		})

		// Drop votes restored for removed slots or a disabled maybe, so they are not submitted
		watch(
			votes,
			(validVotes) => {
				if (
					props.readOnly
					&& Object.keys(validVotes).length
						!== Object.keys(storedVotes.value).length
				) {
					emit('update:values', validVotes)
				}
			},
			{ immediate: true },
		)

		const yesCount = (nextVotes: Record<string, string>): number => {
			return Object.values(nextVotes).filter((vote) => vote === 'yes').length
		}

		/**
		 * Show a stored slot in the local date and time format.
		 *
		 * @param text The stored slot, for example 2026-11-17 10:00
		 */
		const formatSlot = (text: string): string => {
			const match = /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/.exec(text)
			if (!match) {
				return text
			}
			const date = new Date(`${match[1]}T${match[2]}`)
			if (Number.isNaN(date.getTime())) {
				return text
			}
			return new Intl.DateTimeFormat(undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			}).format(date)
		}

		const onVote = (slotId: number, vote: 'yes' | 'no' | 'maybe'): void => {
			const nextVotes = {
				...votes.value,
				[String(slotId)]: vote,
			}
			const limit = extraSettings.value.yesLimitPerPerson
			if (
				vote === 'yes'
				&& typeof limit === 'number'
				&& yesCount(nextVotes) > limit
			) {
				question.errorMessage.value = n(
					'forms',
					'You can choose at most one yes',
					'You can choose at most %n yes',
					limit,
				)
				return
			}
			question.errorMessage.value = null
			emit('update:values', nextVotes)
		}

		/**
		 * Show a server error on this question if it names the question or one of its slots.
		 *
		 * @param message The message returned by the server
		 * @return Whether the error belongs to this question
		 */
		const applySubmissionError = (message: string): boolean => {
			const names = [props.text, ...savedSlots.value.map((slot) => slot.text)]
			const isOwnError = names.some(
				(name) => !!name && message.includes(`"${name}"`),
			)
			if (!isOwnError) {
				return false
			}
			question.errorMessage.value = message
			question.focus()
			return true
		}

		const validate = async (): Promise<boolean> => {
			if (props.isRequired && Object.keys(votes.value).length === 0) {
				question.errorMessage.value = t(
					'forms',
					'You must answer this question',
				)
				return false
			}

			const limit = extraSettings.value.yesLimitPerPerson
			if (typeof limit === 'number' && yesCount(votes.value) > limit) {
				question.errorMessage.value = n(
					'forms',
					'You can choose at most one yes',
					'You can choose at most %n yes',
					limit,
				)
				return false
			}

			question.errorMessage.value = null
			return true
		}

		const onAllowMaybeChange = (allowMaybe: boolean): void => {
			question.onExtraSettingsChange({ allowMaybe })
		}

		/**
		 * Empty means unlimited. A set limit is at least 1.
		 *
		 * @param key The limit to change
		 * @param value The new limit, or null to remove it
		 */
		const onYesLimit = (
			key: 'yesLimitPerPerson' | 'yesLimitPerSlot',
			value: number | string | null,
		): void => {
			if (value === null) {
				question.onExtraSettingsChange({ [key]: null })
				return
			}
			const parsed = Number.parseInt(value.toString(), 10)
			if (parsed >= 1) {
				question.onExtraSettingsChange({ [key]: parsed })
			}
		}

		const onDragStart = (): void => {
			isDragging.value = true
		}

		const onDragEnd = (): void => {
			nextTick(() => {
				isDragging.value = false
			})
		}

		return {
			...question,
			...questionMultiple,
			rootElement,
			choices,
			extraSettings,
			input,
			isDragging,
			onAllowMaybeChange,
			onDragEnd,
			onDragStart,
			onYesLimit,
			OptionType,
			applySubmissionError,
			formatSlot,
			onVote,
			savedSlots,
			shiftDragHandle,
			t,
			validate,
			votes,
		}
	},
})
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
	width: 100%;
	gap: calc(2 * var(--default-grid-baseline));
}

.datepoll-slot {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: var(--default-grid-baseline) calc(2 * var(--default-grid-baseline));

	&__text {
		flex: 1 1 12em;
	}

	&__votes {
		display: flex;
		flex-wrap: wrap;
		gap: calc(2 * var(--default-grid-baseline));
	}
}

.options-list-transition-move,
.options-list-transition-enter-active,
.options-list-transition-leave-active {
	transition: all var(--animation-slow) ease;
}

.options-list-transition-enter-from,
.options-list-transition-leave-to {
	opacity: 0;
	transform: translateX(var(--default-clickable-area));
}

.options-list-transition-leave-active {
	position: absolute;
}
</style>
