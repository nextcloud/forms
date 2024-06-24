<!--
  - @copyright Copyright (c) 2024 Felix Beichler <35049588+Himmelxd@users.noreply.github.com>
  -
  - @author Felix Beichler <35049588+Himmelxd@users.noreply.github.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
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
					t('forms', 'QR code representation of {text}', { text: text })
				" />
		</div>
	</NcDialog>
</template>

<script>
import QRCode from 'qrcode'

import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'

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
					this.uri = await QRCode.toDataURL(this.text, { width: 256 })
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
