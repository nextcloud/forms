<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<AppNavigationItem
		ref="navigationItem"
		:exact="true"
		:icon="icon"
		:title="form.title"
		:to="{ name: 'edit', params: { hash: form.hash } }">
		<template v-if="!loading" #actions>
			<ActionLink
				:href="formLink"
				:icon="copied && copySuccess ? 'icon-checkmark-color' : 'icon-clippy'"
				target="_blank"
				@click.stop.prevent="copyLink">
				{{ clipboardTooltip }}
			</ActionLink>
			<ActionRouter :close-after-click="true"
				:exact="true"
				icon="icon-comment"
				:to="{ name: 'results', params: { hash: form.hash } }">
				{{ t('forms', 'Responses') }}
			</ActionRouter>
			<!-- <ActionRouter :close-after-click="true"
				:exact="true"
				icon="icon-clone"
				:to="{ name: 'clone', params: { hash: form.hash } }">
				{{ t('forms', 'Clone form') }}
			</ActionRouter> -->
			<ActionSeparator />
			<ActionButton :close-after-click="true" icon="icon-delete" @click="onDeleteForm">
				{{ t('forms', 'Delete form') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionRouter from '@nextcloud/vue/dist/Components/ActionRouter'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import Vue from 'vue'
import VueClipboard from 'vue-clipboard2'

Vue.use(VueClipboard)

export default {
	name: 'AppNavigationForm',

	components: {
		AppNavigationItem,
		ActionButton,
		ActionLink,
		ActionRouter,
		ActionSeparator,
	},

	props: {
		form: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			copySuccess: true,
			copied: false,
			loading: false,
		}
	},

	computed: {
		/**
		 * Map form state to bullet color
		 *
		 * @returns {string} hex color
		 */
		bulletColor() {
			const style = getComputedStyle(document.body)
			if (this.form.expires && moment().unix() > this.form.expires) {
				return style.getPropertyValue('--color-error').slice(-6)
			}
			return style.getPropertyValue('--color-success').slice(-6)
		},

		icon() {
			if (this.loading) {
				return 'icon-loading-small'
			}
			if (this.isExpired) {
				return 'icon-checkmark'
			}
			return 'icon-forms'
		},

		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},

		/**
		 * Return the form share link
		 * @returns {string}
		 */
		formLink() {
			return window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/${this.form.hash}`)
		},

		/**
		 * Clipboard v-tooltip message
		 * @returns {string}
		 */
		clipboardTooltip() {
			if (this.copied) {
				return this.copySuccess
					? t('forms', 'Form link copied')
					: t('forms', 'Cannot copy, please copy the link manually')
			}
			return t('forms', 'Copy share link')
		},
	},

	methods: {
		async onDeleteForm() {
			if (!confirm(t('forms', 'Are you sure you want to delete the form “{title}”?', { title: this.form.title }))) {
				return
			}

			// All good, let's delete
			this.loading = true
			try {
				await axios.delete(generateUrl('/apps/forms/api/v1/form/{id}', { id: this.form.id }))
				this.$emit('delete', this.form.id)
			} catch (error) {
				showError(t('forms', 'Error while deleting form “{title}”', { title: this.form.title }))
				console.error(error.response)
			} finally {
				this.loading = false
			}
		},

		async copyLink() {
			try {
				await this.$copyText(this.formLink)
				// make sure the menu stays open despite the click outside
				this.$refs.navigationItem.menuOpened = true
				this.copySuccess = true
				this.copied = true
			} catch (error) {
				this.copySuccess = false
				this.copied = true
				console.error(error)
			} finally {
				setTimeout(() => {
					this.copySuccess = false
					this.copied = false
				}, 4000)
			}
		},

	},

}
</script>
