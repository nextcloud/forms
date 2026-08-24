<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:contentValid="contentValid"
		:shiftDragHandle="shiftDragHandle"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:modelValue="extraSettings?.shuffleOptions"
				@update:modelValue="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionButton closeAfterClick @click="isOptionDialogShown = true">
				<template #icon>
					<NcIconSvgWrapper :svg="IconContentPaste" />
				</template>
				{{ t('forms', 'Add multiple options') }}
			</NcActionButton>
		</template>

		<!-- Submit mode -->
		<div
			v-if="readOnly"
			class="question__content"
			role="list"
			:aria-labelledby="titleId"
			:aria-describedby="description ? descriptionId : undefined"
			:aria-errormessage="hasError ? errorId : undefined"
			:aria-invalid="hasError ? 'true' : undefined">
			<!-- Unranked pool -->
			<div class="ranking-unranked">
				<Draggable
					v-if="unrankedOptions.length > 0"
					v-model="unrankedOptions"
					class="ranking-unranked__pool"
					:animation="300"
					:group="'ranking_' + id"
					target=".sort-target"
					direction="horizontal"
					@start="onRankingStart"
					@end="onRankingEnd">
					<TransitionGroup
						tag="ul"
						:name="isRanking ? undefined : 'options-list-transition'"
						class="sort-target">
						<li v-for="option in unrankedOptions" :key="option.id">
							<button
								type="button"
								class="ranking-unranked__item"
								@click="rankOption(option)">
								{{ option.text }}
							</button>
						</li>
					</TransitionGroup>
				</Draggable>
				<p v-else class="ranking-unranked__empty">
					{{ t('forms', 'All options ranked') }}
				</p>
			</div>

			<!-- Ranked list -->
			<div class="ranking-ranked">
				<p class="ranking-section__label">
					{{ t('forms', 'Your ranking') }}
				</p>
				<Draggable
					v-model="rankedOptions"
					class="ranking-ranked__list"
					:animation="300"
					:group="'ranking_' + id"
					target=".sort-target"
					direction="vertical"
					handle=".ranking-item__drag-handle"
					@start="onRankingStart"
					@end="onRankingEnd">
					<TransitionGroup
						tag="ul"
						:name="isRanking ? undefined : 'options-list-transition'"
						class="sort-target">
						<li
							v-for="(option, index) in rankedOptions"
							:key="option.id"
							class="ranking-item"
							role="listitem">
							<NcActions
								:id="`ranking-${option.id}-drag`"
								:container="`#ranking-${option.id}-drag`"
								:aria-label="t('forms', 'Move option actions')"
								class="ranking-item__drag-handle"
								variant="tertiary-no-background">
								<template #icon>
									<NcIconSvgWrapper :svg="IconDragIndicator" />
								</template>
								<NcActionButton
									ref="buttonOptionUp"
									:disabled="index === 0"
									@click="onMoveUp(index)">
									<template #icon>
										<NcIconSvgWrapper :svg="IconArrowUp" />
									</template>
									{{ t('forms', 'Move option up') }}
								</NcActionButton>
								<NcActionButton
									ref="buttonOptionDown"
									:disabled="index === rankedOptions.length - 1"
									@click="onMoveDown(index)">
									<template #icon>
										<NcIconSvgWrapper :svg="IconArrowDown" />
									</template>
									{{ t('forms', 'Move option down') }}
								</NcActionButton>
							</NcActions>
							<span class="ranking-item__position"
								>{{ index + 1 }}.</span
							>
							<span class="ranking-item__text">{{ option.text }}</span>
							<NcButton
								variant="tertiary"
								:ariaLabel="t('forms', 'Remove from ranking')"
								@click="unrankOption(option)">
								<template #icon>
									<NcIconSvgWrapper :svg="IconClose" />
								</template>
							</NcButton>
						</li>
					</TransitionGroup>
				</Draggable>
				<p v-if="rankedOptions.length === 0" class="ranking-ranked__empty">
					{{ t('forms', 'Tap options above to rank them') }}
				</p>
			</div>
		</div>

		<!-- Edit mode: manage options -->
		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>
			<Draggable
				v-else
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
						:isRanking="true"
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

		<!-- Add multiple options modal -->
		<OptionInputDialog
			v-model:open="isOptionDialogShown"
			@multipleAnswers="handleMultipleOptions" />
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import type { FormsOption } from '../../models/Entities.d.ts'

import IconClose from '@material-symbols/svg-400/outlined/close.svg?raw'
import IconContentPaste from '@material-symbols/svg-400/outlined/content_paste.svg?raw'
import IconDragIndicator from '@material-symbols/svg-400/outlined/drag_indicator.svg?raw'
import IconArrowDown from '@material-symbols/svg-400/outlined/keyboard_arrow_down.svg?raw'
import IconArrowUp from '@material-symbols/svg-400/outlined/keyboard_arrow_up.svg?raw'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, nextTick, ref, watch } from 'vue'
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import OptionInputDialog from '../OptionInputDialog.vue'
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

export default defineComponent({
	name: 'QuestionRanking',

	components: {
		AnswerInput,
		Draggable,
		NcActionButton,
		NcActionCheckbox,
		NcActions,
		NcButton,
		NcIconSvgWrapper,
		NcLoadingIcon,
		OptionInputDialog,
		Question,
	},

	props: QUESTION_PROPS,
	emits: [...QUESTION_EMITS, ...QUESTION_MULTIPLE_EMITS, 'update:values'],

	setup(props, { emit }) {
		const question = useQuestion(props, { emit })
		const values = computed<Array<number | string>>(() => {
			return Array.isArray(props.values) ? props.values : []
		})
		const input = ref<
			Array<{
				focus?: () => void
				$props?: { optionType?: string; index?: number }
			} | null>
		>([])
		const isLoading = ref(false)
		const questionMultiple = useQuestionMultiple(props, {
			emit,
			input,
			isLoading,
		})
		const buttonOptionUp = ref<Array<{ $el?: { focus?: () => void } } | null>>(
			[],
		)
		const buttonOptionDown = ref<Array<{ $el?: { focus?: () => void } } | null>>(
			[],
		)
		const isDragging = ref(false)
		const isRanking = ref(false)
		const isOptionDialogShown = ref(false)
		const rankedOptions = ref<FormsOption[]>([])
		const unrankedOptions = ref<FormsOption[]>([])

		const shiftDragHandle = computed(() => {
			return (
				!props.readOnly
				&& props.options.length !== 0
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

		const emitValues = (): void => {
			if (rankedOptions.value.length === 0) {
				// Nothing ranked - emit empty to signal unanswered
				emit('update:values', [])
			} else {
				emit(
					'update:values',
					rankedOptions.value.map((option) => option.id),
				)
			}
		}

		/**
		 * Initialize ranked/unranked options from existing values or default order
		 */
		const initRankedOptions = (): void => {
			const sorted = questionMultiple.sortOptionsOfType(
				props.options,
				OptionType.Choice,
			)

			if (values.value.length > 0) {
				// Restore order from saved values (array of option IDs)
				const byId = Object.fromEntries(
					sorted.map((option) => [option.id, option]),
				) as Record<number, FormsOption>
				rankedOptions.value = values.value
					.map((id) => byId[parseInt(String(id), 10)])
					.filter((option): option is FormsOption => Boolean(option))
				unrankedOptions.value = sorted.filter(
					(option) =>
						!rankedOptions.value.some(
							(ranked) => ranked.id === option.id,
						),
				)
			} else if (props.readOnly) {
				// Submit mode: start with all options unranked
				rankedOptions.value = []
				unrankedOptions.value = [...sorted]
			} else {
				// Edit mode: show all options in default order
				rankedOptions.value = [...sorted]
				unrankedOptions.value = []
			}
		}

		const validate = async (): Promise<boolean> => {
			const optionsCount = questionMultiple.sortOptionsOfType(
				props.options,
				OptionType.Choice,
			).length

			if (
				(props.isRequired && rankedOptions.value.length === 0)
				|| (rankedOptions.value.length > 0
					&& rankedOptions.value.length !== optionsCount)
			) {
				question.errorMessage.value = t('forms', 'You must rank all options')
				return false
			}

			question.errorMessage.value = null
			return true
		}

		const onDragStart = (): void => {
			isDragging.value = true
		}

		const onDragEnd = (): void => {
			nextTick(() => {
				isDragging.value = false
			})
		}

		/**
		 * Move an option from the unranked pool to the ranked list
		 *
		 * @param option The option to rank
		 */
		const rankOption = (option: FormsOption): void => {
			unrankedOptions.value = unrankedOptions.value.filter(
				(unranked) => unranked.id !== option.id,
			)
			rankedOptions.value.push(option)
			emitValues()
		}

		/**
		 * Move an option from the ranked list back to the unranked pool
		 *
		 * @param option The option to unrank
		 */
		const unrankOption = (option: FormsOption): void => {
			rankedOptions.value = rankedOptions.value.filter(
				(ranked) => ranked.id !== option.id,
			)
			unrankedOptions.value.push(option)
			emitValues()
		}

		/**
		 * Re-focus a button ref inside a v-for after reorder
		 *
		 * @param refName The ref name ('buttonOptionUp' or 'buttonOptionDown')
		 * @param index The index of the item in the v-for
		 */
		const focusButton = (
			refName: 'buttonOptionUp' | 'buttonOptionDown',
			index: number,
		): void => {
			nextTick(() => {
				const refs =
					refName === 'buttonOptionUp'
						? buttonOptionUp.value
						: buttonOptionDown.value
				if (Array.isArray(refs) && refs[index]) {
					refs[index].$el?.focus?.()
				}
			})
		}

		/**
		 * Move the ranked option at index up by one position
		 *
		 * @param index Current index
		 */
		const onMoveUp = (index: number): void => {
			if (index <= 0) return
			const items = [...rankedOptions.value]
			;[items[index - 1], items[index]] = [items[index], items[index - 1]]
			rankedOptions.value = items
			emitValues()
			const newIndex = index - 1
			focusButton(
				newIndex > 0 ? 'buttonOptionUp' : 'buttonOptionDown',
				newIndex,
			)
		}

		/**
		 * Move the ranked option at index down by one position
		 *
		 * @param index Current index
		 */
		const onMoveDown = (index: number): void => {
			if (index >= rankedOptions.value.length - 1) return
			const items = [...rankedOptions.value]
			;[items[index], items[index + 1]] = [items[index + 1], items[index]]
			rankedOptions.value = items
			emitValues()
			const newIndex = index + 1
			focusButton(
				newIndex < rankedOptions.value.length - 1
					? 'buttonOptionDown'
					: 'buttonOptionUp',
				newIndex,
			)
		}

		const onRankingStart = (): void => {
			isRanking.value = true
		}

		/**
		 * Emit the current ranking after a drag reorder
		 */
		const onRankingEnd = (): void => {
			nextTick(() => {
				isRanking.value = false
			})
			emitValues()
		}

		watch(
			() => props.options,
			() => {
				initRankedOptions()
			},
			{ immediate: true },
		)

		watch(
			() => props.values,
			() => {
				initRankedOptions()
			},
			{ immediate: true },
		)

		return {
			...question,
			...questionMultiple,
			buttonOptionDown,
			buttonOptionUp,
			choices,
			input,
			initRankedOptions,
			isDragging,
			isLoading,
			isOptionDialogShown,
			isRanking,
			IconArrowDown,
			IconArrowUp,
			IconClose,
			IconContentPaste,
			IconDragIndicator,
			onDragEnd,
			onDragStart,
			onMoveDown,
			onMoveUp,
			onRankingEnd,
			onRankingStart,
			OptionType,
			rankedOptions,
			rankOption,
			shiftDragHandle,
			t,
			unrankedOptions,
			unrankOption,
			validate,
			values,
		}
	},
})
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.ranking-section__label {
	color: var(--color-text-maxcontrast);
	margin-block-end: var(--default-grid-baseline);
	font-size: small;
}

.ranking-unranked {
	margin-block-end: calc(2 * var(--default-grid-baseline));

	&__pool {
		display: flex;
		flex-wrap: wrap;
		gap: var(--default-grid-baseline);
	}

	&__item {
		display: inline-block;
		padding: var(--default-grid-baseline) calc(2 * var(--default-grid-baseline));
		background-color: var(--color-background-dark);
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		cursor: pointer;
		font-size: inherit;
		color: var(--color-main-text);
		transition: background-color var(--animation-quick);

		&:hover,
		&:focus-visible {
			background-color: var(--color-background-hover);
			border-color: var(--color-primary-element);
		}
	}

	&__empty {
		color: var(--color-text-maxcontrast);
		font-style: italic;
		padding: var(--default-grid-baseline) 0;
	}

	li {
		display: inline-block;
	}
}

.ranking-ranked {
	&__list {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
	}

	&__empty {
		color: var(--color-text-maxcontrast);
		font-style: italic;
		padding: var(--default-grid-baseline) 0;
	}
}

.ranking-item {
	display: flex;
	align-items: center;
	min-height: var(--default-clickable-area);
	border-radius: var(--border-radius-large);
	user-select: none;
	transition-property: background-color;
	transition-duration: 0.1s;
	transition-timing-function: linear;

	&:hover,
	&:focus-within {
		background-color: var(--color-background-hover);
	}

	&__position {
		font-weight: bold;
		min-width: 1.5em;
		text-align: end;
		margin-inline-end: calc(3 * var(--default-grid-baseline));
		color: var(--color-text-maxcontrast);
	}

	&__text {
		flex: 1;
	}

	&__drag-handle {
		color: var(--color-text-maxcontrast);
		cursor: grab;

		&:hover,
		&:focus,
		&:focus-within {
			color: var(--color-main-text);
		}

		&:active {
			cursor: grabbing;
		}
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
