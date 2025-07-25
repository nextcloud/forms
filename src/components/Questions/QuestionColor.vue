<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<Question
		v-bind="questionProps"
		:title-placeholder="answerType.titlePlaceholder"
		:warning-invalid="answerType.warningInvalid"
		v-on="commonListeners">
		<div class="question__content">
			<NcColorPicker
				:model-value="pickedColor"
				advanced-fields
				@update:model-value="onUpdatePickedColor">
				<NcButton :disabled="!readOnly">{{
					colorPickerPlaceholder
				}}</NcButton>
			</NcColorPicker>
			<div :style="{ 'background-color': pickedColor }" class="color__field">
				<NcButton
					v-if="pickedColor !== '' && !isRequired"
					class="color__field__button"
					:aria-label="t('forms', 'Clear selected color')"
					variant="tertiary"
					@click="onUpdatePickedColor('')">
					<template #icon>
						<IconClose :size="20" />
					</template>
				</NcButton>
			</div>
		</div>
	</Question>
</template>

<script>
import IconClose from 'vue-material-design-icons/Close.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'

import QuestionMixin from '../../mixins/QuestionMixin.js'

export default {
	name: 'QuestionColor',

	components: {
		IconClose,
		NcButton,
		NcColorPicker,
	},

	mixins: [QuestionMixin],

	data() {
		return {
			isLoading: false,
		}
	},

	computed: {
		colorPickerPlaceholder() {
			return this.readOnly
				? this.answerType.submitPlaceholder
				: this.answerType.createPlaceholder
		},

		pickedColor() {
			return this.values[0] ?? ''
		},
	},

	methods: {
		onUpdatePickedColor(color) {
			this.$emit('update:values', [color])
		},
	},
}
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
