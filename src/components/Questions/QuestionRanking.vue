<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		:content-valid="contentValid"
		:shift-drag-handle="shiftDragHandle"
		v-on="commonListeners">
		<template #actions>
			<NcActionCheckbox
				:model-value="extraSettings?.shuffleOptions"
				@update:model-value="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionButton close-after-click @click="isOptionDialogShown = true">
				<template #icon>
					<IconContentPaste :size="20" />
				</template>
				{{ t('forms', 'Add multiple options') }}
			</NcActionButton>
		</template>

		<!-- SUBMIT MODE: drag-to-rank -->
		<Draggable
			v-if="readOnly"
			:list="rankedOptions"
			class="question__content question-ranking"
			:animation="200"
			handle=".ranking-item__drag-handle"
			tag="ol"
			role="list"
			:aria-labelledby="titleId"
			:aria-describedby="description ? descriptionId : undefined"
			@end="onRankingEnd">
			<li
				v-for="(option, index) in rankedOptions"
				:key="option.id"
				class="ranking-item"
				role="listitem">
				<span class="ranking-item__position">{{ index + 1 }}</span>
				<IconDragVertical
					class="ranking-item__drag-handle"
					:size="20" />
				<span class="ranking-item__text">{{ option.text }}</span>
			</li>
		</Draggable>

		<!-- EDIT MODE: standard option editor -->
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
				invert-swap
				tag="ul"
				@change="saveOptionsOrder('choice')"
				@start="isDragging = true"
				@end="isDragging = false">
				<TransitionGroup
					:name="
						isDragging
							? 'no-external-transition-on-drag'
							: 'options-list-transition'
					">
					<AnswerInput
						v-for="(answer, index) in choices"
						:key="answer.local ? 'option-local' : answer.id"
						ref="input"
						:answer="answer"
						:form-id="formId"
						:index="index"
						:is-unique="true"
						:max-index="options.length - 1"
						:max-option-length="maxStringLengths.optionText"
						option-type="choice"
						@create-answer="onCreateAnswer"
						@update:answer="updateAnswer"
						@delete="deleteOption"
						@focus-next="focusNextInput"
						@move-up="onOptionMoveUp(index, 'choice')"
						@move-down="onOptionMoveDown(index, 'choice')"
						@tabbed-out="checkValidOption('choice')" />
				</TransitionGroup>
			</Draggable>
		</template>

		<!-- Add multiple options modal -->
		<OptionInputDialog
			:open.sync="isOptionDialogShown"
			@multiple-answers="handleMultipleOptions" />
	</Question>
</template>

<script>
import Draggable from 'vuedraggable'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'
import IconDragVertical from 'vue-material-design-icons/DragVertical.vue'
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
		IconContentPaste,
		IconDragVertical,
		NcActionButton,
		NcActionCheckbox,
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
			rankedInitialized: false,
		}
	},

	computed: {
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
				// Only initialize once in submit mode to avoid resetting after drag
				if (this.readOnly && this.rankedInitialized) {
					return
				}
				this.initRankedOptions()
			},
		},
	},

	mounted() {
		// If in submit mode and no values set yet, emit the initial order
		if (this.readOnly && (!this.values || this.values.length === 0)) {
			this.$emit('update:values', this.rankedOptions.map((o) => o.id))
		}
	},

	methods: {
		/**
		 * Initialize rankedOptions from existing values or default option order.
		 */
		initRankedOptions() {
			const opts = this.sortOptionsOfType(this.options, OptionType.Choice)
				.filter((o) => !o.local)

			if (this.values && Array.isArray(this.values) && this.values.length > 0) {
				const ordered = []
				for (const id of this.values) {
					const opt = opts.find((o) => o.id === parseInt(id))
					if (opt) ordered.push(opt)
				}
				for (const opt of opts) {
					if (!ordered.find((o) => o.id === opt.id)) {
						ordered.push(opt)
					}
				}
				this.rankedOptions = ordered
			} else {
				this.rankedOptions = opts
			}
			this.rankedInitialized = true
		},

		/**
		 * Called after drag ends — emit the new ranking order to the parent.
		 */
		onRankingEnd() {
			this.$emit('update:values', this.rankedOptions.map((o) => o.id))
		},
	},
}
</script>

<style lang="scss" scoped>
.question-ranking {
	list-style: none;
	padding: 0;
}

.ranking-item {
	display: flex;
	align-items: center;
	padding: 12px 16px;
	margin-bottom: 4px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	user-select: none;
	min-height: 44px;

	&__position {
		font-weight: bold;
		margin-inline-end: 12px;
		min-width: 20px;
		text-align: center;
		color: var(--color-primary-element);
	}

	&__drag-handle {
		margin-inline-end: 8px;
		color: var(--color-text-maxcontrast);
		cursor: grab;
		padding: 8px;

		&:active {
			cursor: grabbing;
		}
	}

	&__text {
		flex: 1;
	}
}
</style>
