<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:contentValid="contentValid"
		:shiftDragHandle="shiftDragHandle"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:modelValue="extraSettings?.shuffleOptions"
				@update:modelValue="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionButton closeAfterClick @click="isOptionDialogShown = true">
				<template #icon>
					<IconContentPaste :size="20" />
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
			:aria-describedby="description ? descriptionId : undefined">
			<!-- Unranked pool (visible when items remain) -->
			<div v-if="unrankedOptions.length > 0" class="ranking-unranked">
				<p class="ranking-unranked__label">
					{{ t('forms', 'Tap to rank') }}
				</p>
				<button
					v-for="option in unrankedOptions"
					:key="option.id"
					class="ranking-unranked__item"
					@click="rankOption(option)">
					{{ option.text }}
				</button>
			</div>

			<!-- Empty state when nothing ranked yet -->
			<p v-if="rankedOptions.length === 0" class="ranking-empty">
				{{ t('forms', 'Tap items above to rank them') }}
			</p>

			<!-- Ranked list header (to separate from pool) -->
			<p v-if="rankedOptions.length > 0" class="ranking-ranked__label">
				{{ t('forms', 'Your ranking') }}
			</p>

			<!-- Ranked list -->
			<Draggable
				v-model="rankedOptions"
				:animation="200"
				direction="vertical"
				handle=".ranking-item__drag-handle"
				@end="onRankingEnd">
				<div
					v-for="(option, index) in rankedOptions"
					:key="option.id"
					class="ranking-item"
					role="listitem">
					<span class="ranking-item__position">{{ index + 1 }}.</span>
					<span class="ranking-item__text">{{ option.text }}</span>
					<div class="ranking-item__actions">
						<NcActions
							:aria-label="t('forms', 'Move option actions')"
							class="ranking-item__drag-handle"
							variant="tertiary-no-background">
							<template #icon>
								<IconDragIndicator :size="20" />
							</template>
							<NcActionButton
								ref="buttonOptionUp"
								:disabled="index === 0"
								@click="onMoveUp(index)">
								<template #icon>
									<IconArrowUp :size="20" />
								</template>
								{{ t('forms', 'Move option up') }}
							</NcActionButton>
							<NcActionButton
								ref="buttonOptionDown"
								:disabled="index === rankedOptions.length - 1"
								@click="onMoveDown(index)">
								<template #icon>
									<IconArrowDown :size="20" />
								</template>
								{{ t('forms', 'Move option down') }}
							</NcActionButton>
						</NcActions>
						<NcButton
							variant="tertiary"
							:ariaLabel="t('forms', 'Remove from ranking')"
							@click="unrankOption(option)">
							<template #icon>
								<IconClose :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
			</Draggable>
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
				:animation="200"
				direction="vertical"
				handle=".option__drag-handle"
				invertSwap
				tag="transition-group"
				:componentData="{
					name: isDragging
						? 'no-external-transition-on-drag'
						: 'options-list-transition',
				}"
				@change="saveOptionsOrder('choice')"
				@start="isDragging = true"
				@end="isDragging = false">
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
					optionType="choice"
					@createAnswer="onCreateAnswer"
					@update:answer="updateAnswer"
					@delete="deleteOption"
					@focusNext="focusNextInput"
					@moveUp="onOptionMoveUp(index, OptionType.Choice)"
					@moveDown="onOptionMoveDown(index, OptionType.Choice)"
					@tabbedOut="checkValidOption" />
			</Draggable>
		</template>

		<!-- Add multiple options modal -->
		<OptionInputDialog
			v-model:open="isOptionDialogShown"
			@multipleAnswers="handleMultipleOptions" />
	</Question>
</template>

<script>
import { VueDraggable as Draggable } from 'vue-draggable-plus'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'
import IconDragIndicator from '../Icons/IconDragIndicator.vue'
import OptionInputDialog from '../OptionInputDialog.vue'
import AnswerInput from './AnswerInput.vue'
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import QuestionMultipleMixin from '../../mixins/QuestionMultipleMixin.ts'
import { OptionType } from '../../models/Constants.ts'

export default {
	name: 'QuestionRanking',

	components: {
		AnswerInput,
		Draggable,
		IconArrowDown,
		IconArrowUp,
		IconClose,
		IconContentPaste,
		IconDragIndicator,
		NcActionButton,
		NcActionCheckbox,
		NcActions,
		NcButton,
		NcLoadingIcon,
		OptionInputDialog,
		Question,
	},

	mixins: [QuestionMixin, QuestionMultipleMixin],
	emits: ['update:values'],

	data() {
		return {
			isDragging: false,
			isLoading: false,
			isOptionDialogShown: false,
			rankedOptions: [],
			unrankedOptions: [],
			OptionType,
		}
	},

	computed: {
		contentValid() {
			return this.answerType.validate(this)
		},

		shiftDragHandle() {
			return !this.readOnly && this.options.length !== 0 && !this.isLastEmpty
		},

		choices: {
			get() {
				return this.sortOptionsOfType(this.options, OptionType.Choice)
			},

			set(value) {
				this.updateOptionsOrder(value, OptionType.Choice)
			},
		},
	},

	watch: {
		options: {
			immediate: true,
			handler() {
				this.initRankedOptions()
			},
		},
	},

	methods: {
		/**
		 * Initialize ranked/unranked options from existing values or default order
		 */
		initRankedOptions() {
			const sorted = this.sortOptionsOfType(this.options, OptionType.Choice)

			if (this.values && this.values.length > 0) {
				// Restore order from saved values (array of option IDs)
				const byId = Object.fromEntries(sorted.map((o) => [o.id, o]))
				this.rankedOptions = this.values
					.map((id) => byId[parseInt(id)])
					.filter(Boolean)
				this.unrankedOptions = sorted.filter(
					(o) => !this.rankedOptions.some((r) => r.id === o.id),
				)
			} else if (this.readOnly) {
				// Submit mode: start with all options unranked
				this.rankedOptions = []
				this.unrankedOptions = [...sorted]
			} else {
				// Edit mode: show all options in default order
				this.rankedOptions = [...sorted]
				this.unrankedOptions = []
			}
		},

		/**
		 * Move an option from the unranked pool to the ranked list
		 *
		 * @param {object} option The option to rank
		 */
		rankOption(option) {
			this.unrankedOptions = this.unrankedOptions.filter(
				(o) => o.id !== option.id,
			)
			this.rankedOptions.push(option)
			this.emitValues()
		},

		/**
		 * Move an option from the ranked list back to the unranked pool
		 *
		 * @param {object} option The option to unrank
		 */
		unrankOption(option) {
			this.rankedOptions = this.rankedOptions.filter((o) => o.id !== option.id)
			this.unrankedOptions.push(option)
			this.emitValues()
		},

		/**
		 * Move the ranked option at index up by one position
		 *
		 * @param {number} index Current index
		 */
		onMoveUp(index) {
			if (index <= 0) return
			const items = [...this.rankedOptions]
			;[items[index - 1], items[index]] = [items[index], items[index - 1]]
			this.rankedOptions = items
			this.emitValues()
		},

		/**
		 * Move the ranked option at index down by one position
		 *
		 * @param {number} index Current index
		 */
		onMoveDown(index) {
			if (index >= this.rankedOptions.length - 1) return
			const items = [...this.rankedOptions]
			;[items[index], items[index + 1]] = [items[index + 1], items[index]]
			this.rankedOptions = items
			this.emitValues()
		},

		/**
		 * Emit the current ranking after a drag reorder
		 */
		onRankingEnd() {
			this.emitValues()
		},

		/**
		 * Emit the current values based on ranking state
		 */
		emitValues() {
			if (this.rankedOptions.length === 0) {
				// Nothing ranked — emit empty to signal unanswered
				this.$emit('update:values', [])
			} else {
				this.$emit(
					'update:values',
					this.rankedOptions.map((o) => o.id),
				)
			}
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

.ranking-unranked {
	margin-block-end: 12px;

	&__label {
		font-weight: bold;
		color: var(--color-text-maxcontrast);
		margin-block-end: 8px;
	}

	&__item {
		display: inline-block;
		padding: 8px 16px;
		margin: 0 8px 8px 0;
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
}

.ranking-empty {
	color: var(--color-text-maxcontrast);
	font-style: italic;
	padding: 12px 0;
}

.ranking-ranked__label {
	font-weight: bold;
	color: var(--color-text-maxcontrast);
	margin-block-end: 4px;
}

.ranking-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	min-height: 44px;
	margin-block-end: 8px;
	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	user-select: none;

	&__position {
		font-weight: bold;
		min-width: 1.5em;
		text-align: end;
		color: var(--color-text-maxcontrast);
	}

	&__text {
		flex: 1;
	}

	&__actions {
		display: flex;
		gap: var(--default-grid-baseline);
		margin-inline-start: auto;
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
	transform: translateX(44px);
}

.options-list-transition-leave-active {
	position: absolute;
}
</style>
