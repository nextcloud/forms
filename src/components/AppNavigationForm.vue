<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<AppNavigationItem ref="navigationItem"
		:icon="icon"
		:title="formTitle"
		:to="{
			name: routerTarget,
			params: { hash: form.hash }
		}"
		@click="mobileCloseNavigation">
		<template v-if="!loading && !readOnly" #actions>
			<ActionButton :close-after-click="true" icon="icon-share" @click="onShareForm">
				{{ t('forms', 'Share form') }}
			</ActionButton>
			<ActionRouter :close-after-click="true"
				:exact="true"
				icon="icon-comment"
				:to="{ name: 'results', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				{{ t('forms', 'Results') }}
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
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionRouter from '@nextcloud/vue/dist/Components/ActionRouter'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'

export default {
	name: 'AppNavigationForm',

	components: {
		AppNavigationItem,
		ActionButton,
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
		 *
		 * @return {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
		},

		/**
		 * Route to use, depending on readOnly
		 *
		 * @return {string} Route to 'submit' or 'formRoot'
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

		onShareForm() {
			this.$emit('open-sharing', this.form.hash)
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
				await axios.delete(generateOcsUrl('apps/forms/api/v1.1/form/{id}', { id: this.form.id }))
				this.$emit('delete', this.form.id)
			} catch (error) {
				showError(t('forms', 'Error while deleting {title}', { title: this.formTitle }))
				console.error(error.response)
			} finally {
				this.loading = false
			}
		},
	},

}
</script>
