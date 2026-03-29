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

		<!-- Submit mode: drag to rank -->
		<div
			v-if="readOnly"
			class="question__content"
			role="list"
			:aria-labelledby="titleId"
			:aria-describedby="description ? descriptionId : undefined">
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
					<span class="ranking-item__drag-handle">
						<IconDragHorizontalVariant :size="20" />
					</span>
					<span class="ranking-item__text">{{ option.text }}</span>
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
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'
import IconDragHorizontalVariant from 'vue-material-design-icons/DragHorizontalVariant.vue'
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
		IconDragHorizontalVariant,
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
		 * Initialize ranked options from existing values or default order
		 */
		initRankedOptions() {
			const sorted = this.sortOptionsOfType(this.options, OptionType.Choice)

			if (this.values && this.values.length > 0) {
				// Restore order from saved values (array of option IDs)
				const byId = Object.fromEntries(sorted.map((o) => [o.id, o]))
				this.rankedOptions = this.values
					.map((id) => byId[parseInt(id)])
					.filter(Boolean)
			} else {
				this.rankedOptions = [...sorted]
			}
		},

		/**
		 * Emit the new ranking after a drag ends
		 */
		onRankingEnd() {
			this.$emit(
				'update:values',
				this.rankedOptions.map((o) => o.id),
			)
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

	&__drag-handle {
		display: flex;
		align-items: center;
		cursor: grab;

		&:active {
			cursor: grabbing;
		}
	}

	&__text {
		flex: 1;
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
