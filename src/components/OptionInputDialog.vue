<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		contentClasses="options-modal"
		:name="t('forms', 'Add multiple options')"
		:open="open"
		:buttons="buttons"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<div class="options-text-area">
			<NcTextArea
				v-model="enteredOptions"
				:label="t('forms', 'Add multiple options (one per line)')"
				:placeholder="t('forms', 'Add multiple options (one per line)')"
				resize="vertical"
				rows="10" />
		</div>
		<NcSelect
			:inputLabel="t('forms', 'Options')"
			multiple
			disabled
			:modelValue="multipleOptions" />
	</NcDialog>
</template>

<script lang="ts">
import IconCheck from '@material-symbols/svg-400/outlined/check.svg?raw'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'

export default defineComponent({
	name: 'OptionInputDialog',

	components: {
		NcDialog,
		NcSelect,
		NcTextArea,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:open', 'multipleAnswers'],

	setup(props, { emit }) {
		const enteredOptions = ref('')

		const multipleOptions = computed(() => {
			const allOptions = enteredOptions.value.split(/\r?\n/g)
			return allOptions.filter((answer: string) => {
				return answer.trim().length > 0
			})
		})

		const onMultipleOptions = (): void => {
			emit('update:open', false)
			if (multipleOptions.value.length > 1) {
				// extract all options entries to parent
				emit('multipleAnswers', multipleOptions.value)
				enteredOptions.value = ''
				return
			}
			// in case of only one option, just show an error message because it is probably missuse of the feature
			showError(t('forms', 'Options should be separated by new line!'))
		}

		const buttons = computed(() => {
			return [
				{
					label: t('forms', 'Cancel'),
					callback: () => {
						emit('update:open', false)
					},
				},
				{
					label: t('forms', 'Add options'),
					type: 'primary' as const,
					icon: IconCheck,
					callback: () => {
						onMultipleOptions()
					},
				},
			]
		})

		return {
			enteredOptions,
			buttons,
			multipleOptions,
			onMultipleOptions,
			t,
		}
	},
})
</script>

<style scoped>
:deep(.options-modal) {
	padding-block: 0px 12px;
	padding-inline: 8px 20px;
}

:deep(.v-select) {
	width: 100%;
	margin-top: 10px !important;
	display: flex;
	flex-direction: column;
	gap: 2px 0;
}

.options-text-area {
	height: 210px;
}
</style>
