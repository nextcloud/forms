<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		closeOnClickOutside
		:name="title"
		:open="isOpen"
		@close="isOpen = false"
		@update:open="$emit('closed', true)">
		<div class="qr-dialog__content">
			<img
				:src="uri"
				:title="text"
				:alt="
					t('forms', 'QR code representation of {text}', {
						text: text,
					})
				" />
		</div>
	</NcDialog>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import QRCode from 'qrcode'
import { defineComponent, ref, watch } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import logger from '../utils/Logger.ts'

export default defineComponent({
	name: 'QRDialog',

	components: {
		NcDialog,
	},

	props: {
		title: {
			type: String,
			default: '',
		},

		text: {
			type: String,
			default: '',
		},
	},

	emits: ['closed'],

	setup(props) {
		const uri = ref('')
		const isOpen = ref(false)

		const generateQr = async (): Promise<void> => {
			if (props.text) {
				try {
					uri.value = await QRCode.toDataURL(props.text, {
						width: 256,
					})
				} catch (err) {
					logger.error(err instanceof Error ? err : String(err))
				}
			} else {
				uri.value = ''
			}
		}

		watch(
			() => props.text,
			async () => {
				await generateQr()
				isOpen.value = !!props.text
			},
			{ immediate: true },
		)

		return {
			isOpen,
			uri,
			t,
			generateQr,
		}
	},
})
</script>

<style lang="scss">
.qr-dialog__content {
	display: flex;
	justify-content: space-around;
	width: 100%;
}
</style>
