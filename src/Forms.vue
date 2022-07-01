<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Content app-name="forms">
		<AppNavigation v-if="canCreateForms || hasForms">
			<AppNavigationNew v-if="canCreateForms"
				button-class="icon-add"
				:text="t('forms', 'New form')"
				@click="onNewForm" />
			<template #list>
				<!-- Form-Owner-->
				<AppNavigationCaption v-if="!noOwnedForms" :title="t('forms', 'Your Forms')" />
				<AppNavigationForm v-for="form in forms"
					:key="form.id"
					:form="form"
					:read-only="false"
					@open-sharing="openSharing"
					@mobile-close-navigation="mobileCloseNavigation"
					@clone="onCloneForm"
					@delete="onDeleteForm" />

				<!-- Shared Forms-->
				<AppNavigationCaption v-if="!noSharedForms" :title="t('forms', 'Shared with you')" />
				<AppNavigationForm v-for="form in sharedForms"
					:key="form.id"
					:form="form"
					:read-only="true"
					@mobile-close-navigation="mobileCloseNavigation" />
			</template>
		</AppNavigation>

		<!-- No forms & loading emptycontents -->
		<AppContent v-if="loading || !hasForms || !routeHash || !routeAllowed">
			<EmptyContent v-if="loading" icon="icon-loading">
				{{ t('forms', 'Loading forms …') }}
			</EmptyContent>
			<EmptyContent v-else-if="!hasForms">
				{{ t('forms', 'No forms created yet') }}
				<template v-if="canCreateForms" #action>
					<button class="primary" @click="onNewForm">
						{{ t('forms', 'Create a form') }}
					</button>
				</template>
			</EmptyContent>

			<EmptyContent v-else>
				<span v-if="canCreateForms">{{ t('forms', 'Select a form or create a new one') }}</span>
				<span v-else>{{ t('forms', 'Please select a form') }}</span>
				<template v-if="canCreateForms" #action>
					<button class="primary" @click="onNewForm">
						{{ t('forms', 'Create new form') }}
					</button>
				</template>
			</EmptyContent>
		</AppContent>

		<!-- No errors show router content -->
		<template v-else>
			<router-view :form.sync="selectedForm"
				:sidebar-opened.sync="sidebarOpened"
				@open-sharing="openSharing" />
			<router-view v-if="!selectedForm.partial"
				:form="selectedForm"
				:opened.sync="sidebarOpened"
				:active.sync="sidebarActive"
				name="sidebar" />
		</template>
	</Content>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import Content from '@nextcloud/vue/dist/Components/Content'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'

import AppNavigationForm from './components/AppNavigationForm.vue'
import EmptyContent from './components/EmptyContent.vue'
import PermissionTypes from './mixins/PermissionTypes.js'
import OcsResponse2Data from './utils/OcsResponse2Data.js'

export default {
	name: 'Forms',

	components: {
		AppNavigationForm,
		AppContent,
		AppNavigation,
		AppNavigationCaption,
		AppNavigationNew,
		Content,
		EmptyContent,
	},

	mixins: [isMobile, PermissionTypes],

	data() {
		return {
			loading: true,
			sidebarOpened: false,
			sidebarActive: 'forms-sharing',
			forms: [],
			sharedForms: [],

			canCreateForms: loadState(appName, 'appConfig').canCreateForms,
		}
	},

	computed: {
		hasForms() {
			return !this.noOwnedForms || !this.noSharedForms
		},
		noOwnedForms() {
			return this.forms?.length === 0
		},
		noSharedForms() {
			return this.sharedForms?.length === 0
		},

		routeHash() {
			return this.$route.params.hash
		},

		// If the user is allowed to access this route
		routeAllowed() {
			// Not allowed, if no hash
			if (!this.routeHash) {
				return false
			}

			// Try to find form in owned & shared list
			const form = [...this.forms, ...this.sharedForms]
				.find(form => form.hash === this.routeHash)

			// If no form found, load it from server. Route will be automatically re-evaluated.
			if (form === undefined) {
				this.fetchPartialForm(this.routeHash)
				return false
			}

			// Return whether route is in the permissions-list
			return form?.permissions.includes(this.$route.name)
		},

		selectedForm: {
			get() {
				if (this.routeAllowed) {
					return this.forms.concat(this.sharedForms).find(form => form.hash === this.routeHash)
				}
				return {}
			},
			set(form) {
				// If a owned form
				let index = this.forms.findIndex(search => search.hash === this.routeHash)
				if (index > -1) {
					this.$set(this.forms, index, form)
					return
				}
				// Otherwise a shared form
				index = this.sharedForms.findIndex(search => search.hash === this.routeHash)
				if (index > -1) {
					this.$set(this.sharedForms, index, form)
				}
			},
		},
	},

	beforeMount() {
		this.loadForms()
	},

	methods: {
		/**
		 * Closes the App-Navigation on mobile-devices
		 */
		mobileCloseNavigation() {
			if (this.isMobile) {
				emit('toggle-navigation', { open: false })
			}
		},

		/**
		 * Open a form and its sidebar for sharing
		 *
		 * @param {string} hash the hash of the form to load
		 */
		openSharing(hash) {
			if (hash !== this.routeHash || this.$route.name !== 'edit') {
				this.$router.push({ name: 'edit', params: { hash } })
			}
			this.sidebarActive = 'forms-sharing'
			this.sidebarOpened = true
		},

		/**
		 * Initial forms load
		 */
		async loadForms() {
			this.loading = true

			// Load Owned forms
			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2/forms'))
				this.forms = OcsResponse2Data(response)
			} catch (error) {
				showError(t('forms', 'An error occurred while loading the forms list'))
				console.error(error)
			}

			// Load shared forms
			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2/shared_forms'))
				this.sharedForms = OcsResponse2Data(response)
			} catch (error) {
				showError(t('forms', 'An error occurred while loading the forms list'))
				console.error(error)
			}

			this.loading = false
		},

		/**
		 * Fetch a partial form by its hash and add it to the shared forms list.
		 *
		 * @param {string} hash the hash of the form to load
		 */
		async fetchPartialForm(hash) {
			this.loading = true

			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2/partial_form/{hash}', { hash }))
				const form = OcsResponse2Data(response)

				// If the user has (at least) submission-permissions, add it to the shared forms
				if (form.permissions.includes(this.PERMISSION_TYPES.PERMISSION_SUBMIT)) {
					this.sharedForms.push(form)
				}
			} catch (error) {
				showError(t('forms', 'Form not found'))
				console.error(error)
			} finally {
				this.loading = false
			}
		},

		/**
		 *
		 */
		async onNewForm() {
			try {
				// Request a new empty form
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2/form'))
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({ name: 'edit', params: { hash: newForm.hash } })
				this.mobileCloseNavigation()
			} catch (error) {
				showError(t('forms', 'Unable to create a new form'))
				console.error(error)
			}
		},

		/**
		 * Request to clone a form, store returned form and open it.
		 *
		 * @param {number} id id of the form to clone
		 */
		async onCloneForm(id) {
			try {
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2/form/clone/{id}', { id }))
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({ name: 'edit', params: { hash: newForm.hash } })
				this.mobileCloseNavigation()
			} catch (error) {
				showError(t('forms', 'Unable to copy form'))
				console.error(error)
			}
		},

		/**
		 * Remove form from forms list after successful server deletion request
		 *
		 * @param {number} id the form id
		 */
		async onDeleteForm(id) {
			const formIndex = this.forms.findIndex(form => form.id === id)
			const deletedHash = this.forms[formIndex].hash

			this.forms.splice(formIndex, 1)

			// Redirect if current form has been deleted
			if (deletedHash === this.routeHash) {
				this.$router.push({ name: 'root' })
			}
		},
	},
}
</script>
