<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		close-on-click-outside
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

<script>
import QRCode from 'qrcode'

import NcDialog from '@nextcloud/vue/components/NcDialog'

export default {
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

	data() {
		return {
			uri: {
				type: String,
				default: '',
			},
			isOpen: {
				type: Boolean,
				default: false,
			},
		}
	},

	watch: {
		text: {
			immediate: true,
			handler() {
				this.generateQr()
				this.isOpen = !!this.text
			},
		},
	},

	methods: {
		async generateQr() {
			if (this.text) {
				try {
					this.uri = await QRCode.toDataURL(this.text, {
						width: 256,
					})
				} catch (err) {
					console.error(err)
				}
			} else {
				this.uri = null
			}
		},
	},
}
</script>

<style lang="scss">
.qr-dialog__content {
	display: flex;
	justify-content: space-around;
	width: 100%;
}
</style>
