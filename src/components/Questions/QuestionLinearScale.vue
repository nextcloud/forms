<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		v-on="commonListeners">
		<template #actions>
			<NcActionInput
				:modelValue="optionsLowest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Lowest value')"
				labelOutside
				:options="[0, 1]"
				required
				@update:modelValue="onOptionsLowestChange">
				<template #icon>
					<IconPencil :size="20" />
				</template>
			</NcActionInput>
			<NcActionInput
				:modelValue="optionsHighest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Highest value')"
				labelOutside
				:options="[2, 3, 4, 5, 6, 7, 8, 9, 10]"
				required
				@update:modelValue="onOptionsHighestChange">
				<template #icon>
					<IconPencil :size="20" />
				</template>
			</NcActionInput>
		</template>

		<div
			class="question__content question-linear-scale"
			:class="{
				question__content__edit: !readOnly,
			}">
			<NcTextArea
				v-if="!readOnly"
				ref="lowest"
				:modelValue="optionsLabelLowest"
				class="question-linear-scale__label-input"
				:label="t('forms', 'Label for lowest value')"
				:placeholder="t('forms', 'Label (optional)')"
				resize="none"
				@input="resizeLabel('lowest')"
				@blur="onBlur('lowest')"
				@update:modelValue="onOptionsLabelLowestChange" />
			<div
				v-else-if="optionsLabelLowest !== ''"
				:id="labelId"
				class="question-linear-scale__label question-linear-scale__label-lowest">
				{{ optionsLabelLowest }}
			</div>
			<fieldset class="question-linear-scale__options">
				<legend class="hidden-visually">
					{{
						t('forms', 'From {firstOption} to {lastOption}', {
							firstOption: optionsLabelLowest,
							lastOption: optionsLabelHighest,
						})
					}}
				</legend>
				<div
					v-for="(option, index) in scaleOptions"
					:key="option"
					class="question-linear-scale__option">
					<label :for="`linear-scale-${id}-${option}`">{{ option }}</label>
					<NcCheckboxRadioSwitch
						:id="`linear-scale-${id}-${option}`"
						:aria-describedby="index === 0 ? labelId : undefined"
						:disabled="!readOnly"
						:modelValue="questionValues"
						:value="option.toString()"
						:name="`${id}-answer`"
						type="radio"
						:required="checkRequired(option)"
						@update:modelValue="onChange"
						@keydown.enter.exact.prevent="onKeydownEnter" />
				</div>
			</fieldset>
			<NcTextArea
				v-if="!readOnly"
				ref="highest"
				:modelValue="optionsLabelHighest"
				class="question-linear-scale__label-input"
				:label="t('forms', 'Label (optional)')"
				:aria-label="t('forms', 'Label for highest value')"
				resize="none"
				@input="resizeLabel('highest')"
				@blur="onBlur('highest')"
				@update:modelValue="onOptionsLabelHighestChange" />
			<div
				v-else-if="optionsLabelHighest !== ''"
				class="question-linear-scale__label question-linear-scale__label-highest">
				{{ optionsLabelHighest }}
			</div>
		</div>
	</Question>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionLinearScale',

	components: {
		IconPencil,
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcTextArea,
		Question,
	},

	mixins: [QuestionMixin],
	emits: ['update:values'],

	data() {
		return {
			isLoading: false,
		}
	},

	computed: {
		scaleOptions() {
			return Array.from(
				{ length: this.optionsHighest - this.optionsLowest + 1 },
				(_, i) => i + this.optionsLowest,
			)
		},

		isUnique() {
			return this.answerType.unique === true
		},

		questionValues() {
			return this.values
		},

		/**
		 * ID for the label for the lowest option
		 */
		labelId() {
			return 'q' + this.index + '__label_lowest'
		},

		optionsLowest() {
			return this.extraSettings?.optionsLowest ?? 1
		},

		optionsHighest() {
			return this.extraSettings?.optionsHighest ?? 5
		},

		optionsLabelLowest() {
			return (
				this.extraSettings?.optionsLabelLowest
				?? t('forms', 'Strongly disagree')
			)
		},

		optionsLabelHighest() {
			return (
				this.extraSettings?.optionsLabelHighest
				?? t('forms', 'Strongly agree')
			)
		},
	},

	mounted() {
		if (!this.readOnly) {
			this.resizeLabel('lowest')
			this.resizeLabel('highest')
		}
	},

	methods: {
		onChange(option) {
			this.$emit('update:values', [option])
		},

		onOptionsLowestChange(value) {
			this.onExtraSettingsChange({ optionsLowest: value === 1 ? null : value })
		},

		onOptionsHighestChange(value) {
			this.onExtraSettingsChange({
				optionsHighest: value === 5 ? null : value,
			})
		},

		onOptionsLabelLowestChange(value) {
			this.onExtraSettingsChange({
				optionsLabelLowest:
					value === t('forms', 'Strongly disagree') ? null : value,
			})
		},

		onOptionsLabelHighestChange(value) {
			this.onExtraSettingsChange({
				optionsLabelHighest:
					value === t('forms', 'Strongly agree') ? null : value,
			})
		},

		/**
		 * Is the provided answer required ?
		 * This is needed for checkboxes as html5
		 * doesn't allow to require at least ONE checked.
		 * So we require the one that are checked or all
		 * if none are checked yet.
		 *
		 * @return {boolean}
		 */
		checkRequired() {
			// false, if question not required
			if (!this.isRequired) {
				return false
			}

			return true
		},

		/**
		 * Resizes the given label to fit within the specified constraints.
		 *
		 * @param {string} label - The label identifier, either 'lowest' or 'highest', indicating which label to resize.
		 */
		resizeLabel(label) {
			let textarea
			if (label === 'lowest') {
				textarea = this.$refs.lowest.$refs.input
			} else if (label === 'highest') {
				textarea = this.$refs.highest.$refs.input
			}
			// next tick ensures that the textarea is attached to DOM
			this.$nextTick(() => {
				if (textarea) {
					textarea.style.cssText = 'height: 0'
					// include 2px border
					textarea.style.cssText = `height: ${textarea.scrollHeight + 4}px; resize: none;`
				}
			})
		},

		/**
		 * Handles the blur event for a label input.
		 *
		 * @param {string} label - The label that is being blurred.
		 *                         It can be either 'lowest' or 'highest' indicating
		 *                         which label input (lowest value or highest value) triggered the blur event.
		 */
		onBlur(label) {
			if (label === 'lowest') {
				this.optionsLabelLowest = this.optionsLabelLowest
					.replace(/[\r\n]+/gm, ' ')
					.trim()
			} else if (label === 'highest') {
				this.optionsLabelHighest = this.optionsLabelHighest
					.replace(/[\r\n]+/gm, ' ')
					.trim()
			}
			this.resizeLabel(label)
		},
	},
}
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;

	@media (max-width: 768px) {
		flex-wrap: wrap; // Allow wrapping for smaller screens
	}

	&__edit {
		margin-inline-start: -12px;

		@media (max-width: 768px) {
			margin-inline-end: calc(var(--clickable-area-large) - 2px);
		}
	}

	.question-linear-scale {
		&__label {
			width: 120px;
			align-self: center;
			flex-shrink: 0;

			&-lowest {
				text-align: start;
			}

			&-highest {
				text-align: end;

				@media (max-width: 768px) {
					text-align: start;
				}
			}

			@media (max-width: 768px) {
				width: 100%; // Full width on smaller screens
				padding-block: var(--default-grid-baseline);
			}
		}

		&__label-input {
			width: 120px;
			align-self: center;
			min-height: fit-content;
			flex-shrink: 0;

			@media (max-width: 768px) {
				width: 100%; // Full width on smaller screens
				padding-block: var(--default-grid-baseline);
			}
		}

		&__options {
			width: 100%;
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: space-evenly;
			flex-grow: 1;

			@media (max-width: 768px) {
				flex-direction: column; // Stack options vertically on smaller screens
				align-items: flex-start; // Align items to the left
			}
		}

		&__option {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;

			@media (max-width: 768px) {
				flex-direction: row-reverse;
			}
		}
	}
}
</style>
