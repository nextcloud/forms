<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		v-on="commonListeners">
		<template #actions>
			<NcActionInput
				v-model="optionsLowest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Lowest value')"
				label-outside
				:options="[0, 1]"
				required>
				<template #icon>
					<IconPencil :size="20" />
				</template>
			</NcActionInput>
			<NcActionInput
				v-model="optionsHighest"
				type="multiselect"
				:clearable="false"
				:label="t('forms', 'Highest value')"
				label-outside
				:options="[2, 3, 4, 5, 6, 7, 8, 9, 10]"
				required>
				<template #icon>
					<IconPencil :size="20" />
				</template>
			</NcActionInput>
		</template>

		<div
			:class="
				readOnly
					? 'question__content'
					: 'question__content question__content__edit'
			">
			<NcTextArea
				v-if="!readOnly"
				ref="lowest"
				v-model="optionsLabelLowest"
				class="label-input-field"
				:label="t('forms', 'Label (optional)')"
				:aria-label="t('forms', 'Label for lowest value')"
				resize="none"
				@input="resizeLabel('lowest')"
				@blur="onBlur('lowest')">
			</NcTextArea>
			<div v-else-if="optionsLabelLowest !== ''" class="label-lowest">
				{{ optionsLabelLowest }}
			</div>
			<div class="question__content__options">
				<NcCheckboxRadioSwitch
					v-for="option in scaleOptions"
					:key="option"
					:disabled="!readOnly"
					:checked="questionValues"
					:value="option.toString()"
					:name="`${id}-answer`"
					type="radio"
					:required="checkRequired(option)"
					@update:checked="onChange"
					@keydown.enter.exact.prevent="onKeydownEnter">
					{{ option }}
				</NcCheckboxRadioSwitch>
			</div>
			<NcTextArea
				v-if="!readOnly"
				ref="highest"
				v-model="optionsLabelHighest"
				class="label-input-field"
				:label="t('forms', 'Label (optional)')"
				:aria-label="t('forms', 'Label for highest value')"
				resize="none"
				@input="resizeLabel('highest')"
				@blur="onBlur('highest')">
			</NcTextArea>
			<div v-else-if="optionsLabelHighest !== ''" class="label-highest">
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

import IconPencil from 'vue-material-design-icons/Pencil.vue'

import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionLinearScale',

	components: {
		IconPencil,
		NcActionInput,
		NcCheckboxRadioSwitch,
		NcTextArea,
	},

	mixins: [QuestionMixin],

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

		optionsLowest: {
			get() {
				return this.extraSettings?.optionsLowest ?? 1
			},
			set(value) {
				this.onExtraSettingsChange({ optionsLowest: value })
			},
		},
		optionsHighest: {
			get() {
				return this.extraSettings?.optionsHighest ?? 5
			},
			set(value) {
				this.onExtraSettingsChange({ optionsHighest: value })
			},
		},
		optionsLabelLowest: {
			get() {
				return (
					this.extraSettings?.optionsLabelLowest ??
					t('forms', 'Strongly disagree')
				)
			},
			set(value) {
				this.onExtraSettingsChange({ optionsLabelLowest: value })
			},
		},
		optionsLabelHighest: {
			get() {
				return (
					this.extraSettings?.optionsLabelHighest ??
					t('forms', 'Strongly agree')
				)
			},
			set(value) {
				this.onExtraSettingsChange({ optionsLabelHighest: value })
			},
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
	padding-block-start: var(--clickable-area-small);

	&__options {
		width: 100%;
		display: flex;
		flex-direction: row;
		justify-content: space-evenly;
		flex-grow: 1;
	}

	&__edit {
		margin-inline-start: -12px;
	}

	:deep(.checkbox-content__text) {
		position: absolute;
		margin-block-start: calc(-1 * var(--clickable-area-large));
		margin-inline-start: 8px;
	}

	.label-input-field {
		width: 120px;
		align-self: center;
		min-height: fit-content;
		flex-shrink: 0;
	}

	.label-lowest {
		width: 120px;
		align-self: center;
		text-align: start;
		flex-shrink: 0;
	}

	.label-highest {
		width: 120px;
		align-self: center;
		text-align: end;
		flex-shrink: 0;
	}
}
</style>
