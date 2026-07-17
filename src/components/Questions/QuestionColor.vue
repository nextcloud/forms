<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:titlePlaceholder="answerType.titlePlaceholder"
		:warningInvalid="answerType.warningInvalid"
		:errorMessage="errorMessage"
		v-on="commonListeners">
		<div
			class="question__content"
			role="group"
			:aria-labelledby="titleId"
			:aria-describedby="description ? descriptionId : undefined">
			<NcColorPicker
				:modelValue="pickedColor"
				advancedFields
				:aria-required="isRequired"
				:aria-errormessage="hasError ? errorId : undefined"
				:aria-invalid="hasError ? 'true' : undefined"
				@update:modelValue="onUpdatePickedColor">
				<NcButton :disabled="!readOnly">
					{{ colorPickerPlaceholder }}
				</NcButton>
			</NcColorPicker>
			<div :style="{ 'background-color': pickedColor }" class="color__field">
				<NcButton
					v-if="pickedColor !== '' && !isRequired"
					class="color__field__button"
					:aria-label="t('forms', 'Clear selected color')"
					variant="tertiary"
					@click="onUpdatePickedColor('')">
					<template #icon>
						<NcIconSvgWrapper :svg="IconClose" />
					</template>
				</NcButton>
			</div>
		</div>
		<template #insert>
			<slot name="insert" />
		</template>
	</Question>
</template>

<script lang="ts">
import IconClose from '@material-symbols/svg-400/outlined/close.svg?raw'
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import Question from './Question.vue'
import QuestionMixin from '../../mixins/QuestionMixin.ts'

export default defineComponent({
	name: 'QuestionColor',

	components: {
		NcIconSvgWrapper,
		NcButton,
		NcColorPicker,
		Question,
	},

	mixins: [QuestionMixin],
	emits: ['update:values'],

	setup() {
		return {
			IconClose,
			t,
		}
	},

	data() {
		return {
			isLoading: false,
		}
	},

	computed: {
		colorPickerPlaceholder(): string {
			return this.readOnly
				? this.answerType.submitPlaceholder
				: this.answerType.createPlaceholder
		},

		pickedColor(): string {
			return (this.values[0] as string | null | undefined) ?? ''
		},
	},

	methods: {
		async validate(): Promise<boolean> {
			if (this.isRequired && this.pickedColor === '') {
				this.errorMessage = t('forms', 'You must answer this question')
				return false
			}

			this.errorMessage = null
			return true
		},

		onUpdatePickedColor(color: string | undefined): void {
			this.$emit('update:values', [color ?? ''])
		},
	},
})
</script>

<style lang="scss" scoped>
.question__content {
	display: flex;
	gap: var(--clickable-area-small);
}

.color__field {
	width: 100px;
	height: var(--default-clickable-area);
	border-radius: var(--border-radius-element);

	&__button {
		position: relative;
		margin-inline-start: calc(100% - var(--default-clickable-area));
	}
}
</style>
