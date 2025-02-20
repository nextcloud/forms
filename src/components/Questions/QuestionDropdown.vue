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
					:form-id="formId"
					:index="index"
					:is-unique="!isMultiple"
					:max-index="options.length - 1"
					:max-option-length="maxStringLengths.optionText"
					@create-answer="onCreateAnswer"
					@update:answer="updateAnswer(index, $event)"
					@delete="deleteOption"
					@focus-next="focusNextInput"
					@move-up="onOptionMoveUp(index)"
					@move-down="onOptionMoveDown(index)"
					@tabbed-out="checkValidOption" />
			</TransitionList>
		</template>

		<!-- Add multiple options modal -->
		<OptionInputDialog
			:open.sync="isOptionDialogShown"
			@multiple-answers="handleMultipleOptions" />
	</Question>
</template>

<script>
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'

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
		return { inputValue: '', isOptionDialogShown: false, isLoading: false }
	},

	computed: {
		selectOptionPlaceholder() {
			if (this.readOnly) {
				return this.answerType.submitPlaceholder
			}
			return this.answerType.createPlaceholder
		},

		isMultiple() {
			// This can be extended if we want to include support for <select multiple>
			return false
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
		width: calc(100% - var(--default-clickable-area));
		position: relative;
		inset-inline-start: -12px;
		margin-inline-end: 32px !important;
	}
}
</style>
