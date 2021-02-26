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
		:icon="icon"
		:title="formTitle"
		:to="{
			name: routerTarget,
			params: { hash: form.hash }
		}"
		@click="mobileCloseNavigation">
		<template v-if="!loading && !readOnly" #actions>
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
				:to="{ name: 'results', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				{{ t('forms', 'Responses') }}
			</ActionRouter>
			<ActionButton :close-after-click="true" icon="icon-clone" @click="onCloneForm">
				{{ t('forms', 'Copy form') }}
			</ActionButton>
			<ActionSeparator />
			<ActionButton :close-after-click="true" icon="icon-delete" @click="onDeleteForm">
				{{ t('forms', 'Delete form') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionRouter from '@nextcloud/vue/dist/Components/ActionRouter'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import axios from '@nextcloud/axios'
import Clipboard from 'v-clipboard'
import moment from '@nextcloud/moment'
import Vue from 'vue'

Vue.use(Clipboard)

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
		readOnly: {
			type: Boolean,
			default: true,
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
		 * Return form title, or placeholder if not set
		 * @returns {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
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
			return t('forms', 'Share link')
		},

		/**
		 * Route to use, depending on readOnly
		 * @returns {string} Route to 'submit' or 'formRoot'
		 */
		routerTarget() {
			if (this.readOnly) {
				return 'submit'
			}

			return 'formRoot'
		},
	},

	methods: {
		/**
		 * Closes the App-Navigation on mobile-devices
		 */
		mobileCloseNavigation() {
			this.$emit('mobile-close-navigation')
		},

		onCloneForm() {
			this.$emit('clone', this.form.id)
		},

		async onDeleteForm() {
			if (!confirm(t('forms', 'Are you sure you want to delete {title}?', { title: this.formTitle }))) {
				return
			}

			// All good, let's delete
			this.loading = true
			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v1', 2) + `form/${this.form.id}`)
				this.$emit('delete', this.form.id)
			} catch (error) {
				showError(t('forms', 'Error while deleting {title}', { title: this.formTitle }))
				console.error(error.response)
			} finally {
				this.loading = false
			}
		},

		async copyLink(event) {
			if (this.$clipboard(this.formLink)) {
				this.copySuccess = true
				this.copied = true
			} else {
				this.copySuccess = false
				this.copied = true
				console.debug('Not possible to copy share link')
			}
			// Set back focus as clipboard removes focus
			event.target.focus()

			setTimeout(() => {
				this.copySuccess = false
				this.copied = false
			}, 4000)
		},

	},

}
</script>
