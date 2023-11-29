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
	<NcContent app-name="forms">
		<NcAppNavigation v-if="canCreateForms || hasForms">
			<NcAppNavigationNew v-if="canCreateForms"
				:text="t('forms', 'New form')"
				@click="onNewForm">
				<template #icon>
					<IconPlus :size="20" decorative />
				</template>
			</NcAppNavigationNew>
			<template #list>
				<!-- Form-Owner-->
				<NcAppNavigationCaption v-if="!noOwnedForms" :name="t('forms', 'Your Forms')" />
				<AppNavigationForm v-for="form in forms"
					:key="form.id"
					:form="form"
					:read-only="false"
					@open-sharing="openSharing"
					@mobile-close-navigation="mobileCloseNavigation"
					@clone="onCloneForm"
					@delete="onDeleteForm" />

				<!-- Shared Forms-->
				<NcAppNavigationCaption v-if="!noSharedForms" :name="t('forms', 'Shared with you')" />
				<AppNavigationForm v-for="form in sharedForms"
					:key="form.id"
					:form="form"
					:read-only="true"
					@open-sharing="openSharing"
					@mobile-close-navigation="mobileCloseNavigation" />
			</template>
		</NcAppNavigation>

		<!-- No forms & loading emptycontents -->
		<NcAppContent v-if="loading || !hasForms || !routeHash || !routeAllowed">
			<NcEmptyContent v-if="loading"
				class="forms-emptycontent"
				:name="t('forms', 'Loading forms …')">
				<template #icon>
					<NcLoadingIcon :size="64" />
				</template>
			</NcEmptyContent>

			<NcEmptyContent v-else-if="!hasForms"
				class="forms-emptycontent"
				:name="t('forms', 'No forms created yet')">
				<template #icon>
					<FormsIcon :size="64" />
				</template>
				<template v-if="canCreateForms" #action>
					<NcButton type="primary" @click="onNewForm">
						{{ t('forms', 'Create a form') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<NcEmptyContent v-else
				class="forms-emptycontent"
				:name="canCreateForms ? t('forms', 'Select a form or create a new one') : t('forms', 'Please select a form')">
				<template #icon>
					<FormsIcon :size="64" />
				</template>
				<template v-if="canCreateForms" #action>
					<NcButton type="primary" @click="onNewForm">
						{{ t('forms', 'Create new form') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</NcAppContent>

		<!-- No errors show router content -->
		<template v-else>
			<router-view :form.sync="selectedForm"
				:sidebar-opened.sync="sidebarOpened"
				@open-sharing="openSharing" />
			<router-view v-if="!selectedForm.partial && canEdit"
				:form="selectedForm"
				:sidebar-opened.sync="sidebarOpened"
				:active.sync="sidebarActive"
				name="sidebar" />
		</template>
	</NcContent>
</template>

<script>
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'

import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppNavigationNew from '@nextcloud/vue/dist/Components/NcAppNavigationNew.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

import IconPlus from 'vue-material-design-icons/Plus.vue'

import FormsIcon from './components/Icons/FormsIcon.vue'
import AppNavigationForm from './components/AppNavigationForm.vue'
import PermissionTypes from './mixins/PermissionTypes.js'
import OcsResponse2Data from './utils/OcsResponse2Data.js'
import logger from './utils/Logger.js'

export default {
	name: 'Forms',

	components: {
		AppNavigationForm,
		FormsIcon,
		IconPlus,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationCaption,
		NcAppNavigationNew,
		NcButton,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
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
		canEdit() {
			return this.selectedForm.permissions.includes(this.PERMISSION_TYPES.PERMISSION_EDIT)
		},
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

	mounted() {
		subscribe('forms:last-updated:set', (id) => this.onLastUpdatedByEventBus(id))
	},

	unmounted() {
		unsubscribe('forms:last-updated:set', (id) => this.onLastUpdatedByEventBus(id))
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
			if (hash !== this.routeHash) {
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
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2.2/forms'))
				this.forms = OcsResponse2Data(response)
			} catch (error) {
				logger.error('Error while loading owned forms list', { error })
				showError(t('forms', 'An error occurred while loading the forms list'))
			}

			// Load shared forms
			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2.2/shared_forms'))
				this.sharedForms = OcsResponse2Data(response)
			} catch (error) {
				logger.error('Error while loading shared forms list', { error })
				showError(t('forms', 'An error occurred while loading the forms list'))
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
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2.2/partial_form/{hash}', { hash }))
				const form = OcsResponse2Data(response)

				// If the user has (at least) submission-permissions, add it to the shared forms
				if (form.permissions.includes(this.PERMISSION_TYPES.PERMISSION_SUBMIT)) {
					this.sharedForms.push(form)
				}
			} catch (error) {
				logger.error(`Form ${hash} not found`, { error })
				showError(t('forms', 'Form not found'))
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2.2/form'))
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({ name: 'edit', params: { hash: newForm.hash } })
				this.mobileCloseNavigation()
			} catch (error) {
				logger.error('Unable to create new form', { error })
				showError(t('forms', 'Unable to create a new form'))
			}
		},

		/**
		 * Request to clone a form, store returned form and open it.
		 *
		 * @param {number} id id of the form to clone
		 */
		async onCloneForm(id) {
			try {
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2.2/form/clone/{id}', { id }))
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({ name: 'edit', params: { hash: newForm.hash } })
				this.mobileCloseNavigation()
			} catch (error) {
				logger.error(`Unable to copy form ${id}`, { error })
				showError(t('forms', 'Unable to copy form'))
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

		/**
		 * Update last updated timestamp in given form
		 *
		 * @param {number} id the form id
		 */
		onLastUpdatedByEventBus(id) {
			const formIndex = this.forms.findIndex(form => form.id === id)
			if (formIndex !== -1) {
				this.forms[formIndex].lastUpdated = moment().unix()
				this.forms.sort((b, a) => a.lastUpdated - b.lastUpdated)
			} else {
				const sharedFormIndex = this.sharedForms.findIndex(form => form.id === id)
				this.sharedForms[sharedFormIndex].lastUpdated = moment().unix()
				this.sharedForms.sort((b, a) => a.lastUpdated - b.lastUpdated)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.forms-emptycontent {
	height: 100%;
}
</style>
