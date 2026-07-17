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
import { translate as t } from '@nextcloud/l10n'
import QRCode from 'qrcode'
import { defineComponent } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import logger from '../utils/Logger.ts'

interface QRDialogData {
	uri: string
	isOpen: boolean
}

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

	setup() {
		return {
			t,
		}
	},

	data(): QRDialogData {
		return {
			uri: '',
			isOpen: false,
		}
	},

	watch: {
		text: {
			immediate: true,
			handler(): void {
				this.generateQr()
				this.isOpen = !!this.text
			},
		},
	},

	methods: {
		async generateQr(): Promise<void> {
			if (this.text) {
				try {
					this.uri = await QRCode.toDataURL(this.text, {
						width: 256,
					})
				} catch (err) {
					logger.error(err instanceof Error ? err : String(err))
				}
			} else {
				this.uri = ''
			}
		},
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
