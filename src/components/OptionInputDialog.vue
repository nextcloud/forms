<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		content-classes="options-modal"
		:name="t('forms', 'Add multiple options')"
		:open="open"
		:buttons="buttons"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<NcTextArea
			v-model:value="enteredOptions"
			:label="t('forms', 'Add multiple options (one per line)')"
			:placeholder="t('forms', 'Add multiple options (one per line)')"
			resize="vertical"
			rows="10" />
		<NcSelect
			:input-label="t('forms', 'Options')"
			multiple
			disabled
			:value="multipleOptions" />
	</NcDialog>
</template>

<script>
import IconCheck from '@mdi/svg/svg/check.svg?raw'
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
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

	emits: ['update:open', 'multiple-answers'],

	data() {
		return {
			enteredOptions: '',
		}
	},

	computed: {
		buttons() {
			return [
				{
					label: t('forms', 'Cancel'),
					callback: () => {
						this.$emit('update:open', false)
					},
				},
				{
					label: t('forms', 'Add options'),
					type: 'primary',
					icon: IconCheck,
					callback: () => {
						this.onMultipleOptions()
					},
				},
			]
		},

		multipleOptions() {
			const allOptions = this.enteredOptions.split(/\r?\n/g)
			return allOptions.filter((answer) => {
				return answer.trim().length > 0
			})
		},
	},

	methods: {
		t,

		onMultipleOptions() {
			this.$emit('update:open', false)
			if (this.multipleOptions.length > 1) {
				// extract all options entries to parent
				this.$emit('multiple-answers', this.multipleOptions)
				this.enteredOptions = ''
				return
			}
			// in case of only one option, just show an error message because it is probably missuse of the feature
			showError(t('forms', 'Options should be separated by new line!'))
		},
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
</style>
