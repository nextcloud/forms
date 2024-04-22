<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
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
				:checked="extraSettings?.shuffleOptions"
				@update:checked="onShuffleOptionsChange">
				{{ t('forms', 'Shuffle options') }}
			</NcActionCheckbox>
			<NcActionButton close-after-click @click="isOptionDialogShown = true">
				<template #icon>
					<IconContentPaste :size="20" />
				</template>
				{{ t('forms', 'Add multiple options') }}
			</NcActionButton>
		</template>
		<NcSelect
			v-if="readOnly"
			:value="selectedOption"
			:name="name || undefined"
			:placeholder="selectOptionPlaceholder"
			:multiple="isMultiple"
			:required="isRequired"
			:options="sortedOptions"
			:searchable="false"
			label="text"
			@input="onInput" />

		<template v-else>
			<div v-if="isLoading">
				<NcLoadingIcon :size="64" />
			</div>
			<TransitionList v-else class="question__content">
				<!-- Answer text input edit -->
				<AnswerInput
					v-for="(answer, index) in sortedOptions"
					:key="answer.local ? 'option-local' : answer.id"
					ref="input"
					:answer="answer"
					is-dropdown
					:allow-reorder="!extraSettings?.shuffleOptions"
					:form-id="formId"
					:index="index"
					:is-unique="!isMultiple"
					:max-index="options.length - 1"
					:max-option-length="maxStringLengths.optionText"
					@delete="deleteOption"
					@update:answer="updateAnswer"
					@focus-next="focusNextInput"
					@tabbed-out="checkValidOption" />

				<li v-if="!isLastEmpty || hasNoAnswer" class="question__item">
					<input
						ref="pseudoInput"
						class="question__input"
						:aria-label="t('forms', 'Add a new answer')"
						:placeholder="t('forms', 'Add a new answer')"
						:maxlength="maxStringLengths.optionText"
						minlength="1"
						type="text"
						@input="addNewEntry"
						@compositionstart="onCompositionStart"
						@compositionend="onCompositionEnd"
						@move-up="onOptionMoveUp(index)"
						@move-down="onOptionMoveDown(index)"
						@tabbed-out="checkValidOption"
						@update:answer="updateAnswer(index, $event)" />
				</li>
			</TransitionList>
		</template>

		<!-- Add multiple options modal -->
		<OptionInputDialog
			:open.sync="isOptionDialogShown"
			@multiple-answers="handleMultipleOptions" />
	</Question>
</template>

<script>
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

import IconContentPaste from 'vue-material-design-icons/ContentPaste.vue'

import AnswerInput from './AnswerInput.vue'
import OptionInputDialog from '../OptionInputDialog.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'
import QuestionMultipleMixin from '../../mixins/QuestionMultipleMixin.ts'
import TransitionList from '../TransitionList.vue'

export default {
	name: 'QuestionDropdown',

	components: {
		AnswerInput,
		IconContentPaste,
		NcActionButton,
		NcActionCheckbox,
		NcLoadingIcon,
		NcSelect,
		OptionInputDialog,
		TransitionList,
	},

	mixins: [QuestionMixin, QuestionMultipleMixin],

	data() {
		return {
			inputValue: '',
			isOptionDialogShown: false,
			isLoading: false,
		}
	},

	computed: {
		selectOptionPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		contentValid() {
			return this.answerType.validate(this)
		},

		isLastEmpty() {
			const value = this.options[this.options.length - 1]
			return value?.text?.trim?.().length === 0
		},

		isMultiple() {
			// This can be extended if we want to include support for <select multiple>
			return false
		},

		hasNoAnswer() {
			return this.options.length === 0
		},

		areNoneChecked() {
			return this.values.length === 0
		},

		shiftDragHandle() {
			return !this.readOnly && this.options.length !== 0 && !this.isLastEmpty
		},

		selectedOption() {
			if (!this.values) {
				return null
			}

			const selected = this.values.map((id) =>
				this.options.find((option) => option.id === id),
			)

			return this.isMultiple ? selected : selected[0]
		},
	},

	methods: {
		onInput(option) {
			if (Array.isArray(option)) {
				this.$emit('update:values', [
					...new Set(option.map((opt) => opt.id)),
				])
				return
			}

			// Simple select
			this.$emit('update:values', option ? [option.id] : [])
		},
	},
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	flex-direction: column;
}

.question__item {
	position: relative;
	display: inline-flex;
	min-height: var(--default-clickable-area);

	.question__input {
		width: 100%;
		position: relative;
		inset-inline-start: -12px;
		margin-inline-end: 32px !important;
	}
}
</style>
