<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="forms">
		<NcAppNavigation
			v-if="canCreateForms || hasForms"
			:aria-label="t('forms', 'Forms navigation')">
			<NcAppNavigationNew
				v-if="canCreateForms"
				:text="t('forms', 'New form')"
				@click="onNewForm">
				<template #icon>
					<IconPlus :size="20" decorative />
				</template>
			</NcAppNavigationNew>

			<!-- Form-Owner-->
			<template v-if="ownedForms.length > 0">
				<NcAppNavigationCaption
					is-heading
					class="forms-navigation__list-heading"
					heading-id="forms-navigation-your-forms"
					:name="t('forms', 'Your forms')" />
				<ul aria-labelledby="forms-navigation-your-forms">
					<AppNavigationForm
						v-for="form in ownedForms"
						:key="form.id"
						:form="form"
						:read-only="false"
						@open-sharing="openSharing"
						@mobile-close-navigation="mobileCloseNavigation"
						@clone="onCloneForm"
						@delete="onDeleteForm" />
				</ul>
			</template>

			<!-- Shared Forms-->
			<template v-if="sharedForms.length > 0">
				<NcAppNavigationCaption
					is-heading
					class="forms-navigation__list-heading"
					heading-id="forms-navigation-shared-forms"
					:name="t('forms', 'Shared with you')" />
				<ul aria-labelledby="forms-navigation-shared-forms">
					<AppNavigationForm
						v-for="form in sharedForms"
						:key="form.id"
						:form="form"
						read-only
						@open-sharing="openSharing"
						@mobile-close-navigation="mobileCloseNavigation" />
				</ul>
			</template>

			<template #footer>
				<div v-if="archivedForms.length > 0" class="forms-navigation-footer">
					<NcButton
						alignment="start"
						class="forms__archived-forms-toggle"
						variant="tertiary"
						wide
						@click="showArchivedForms = true">
						<template #icon>
							<IconArchive :size="20" />
						</template>
						{{ t('forms', 'Archived forms') }}
					</NcButton>
				</div>
			</template>
		</NcAppNavigation>

		<!-- No forms & loading emptycontents -->
		<NcAppContent v-if="loading || !routeHash || !routeAllowed">
			<NcEmptyContent
				v-if="loading"
				class="forms-emptycontent"
				:name="t('forms', 'Loading forms …')">
				<template #icon>
					<NcLoadingIcon :size="64" />
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="!hasForms"
				class="forms-emptycontent"
				:name="t('forms', 'No forms created yet')">
				<template #icon>
					<FormsIcon :size="64" />
				</template>
				<template v-if="canCreateForms" #action>
					<NcButton variant="primary" @click="onNewForm">
						{{ t('forms', 'Create a form') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else
				class="forms-emptycontent"
				:name="
					canCreateForms
						? t('forms', 'Select a form or create a new one')
						: t('forms', 'Please select a form')
				">
				<template #icon>
					<FormsIcon :size="64" />
				</template>
				<template v-if="canCreateForms" #action>
					<NcButton variant="primary" @click="onNewForm">
						{{ t('forms', 'Create new form') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</NcAppContent>

		<!-- No errors show router content -->
		<template v-else>
			<router-view
				:form.sync="selectedForm"
				:sidebar-opened.sync="sidebarOpened"
				@open-sharing="openSharing" />
			<Sidebar
				v-if="!selectedForm.partial && canEdit"
				:form="selectedForm"
				:sidebar-opened.sync="sidebarOpened"
				:active.sync="sidebarActive" />
		</template>

		<!-- Archived forms modal -->
		<ArchivedFormsModal
			:open.sync="showArchivedForms"
			:forms="archivedForms"
			@clone="onCloneForm" />
	</NcContent>
</template>

<script>
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import { useIsMobile } from '@nextcloud/vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import IconArchive from 'vue-material-design-icons/Archive.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import ArchivedFormsModal from './components/ArchivedFormsModal.vue'
import AppNavigationForm from './components/AppNavigationForm.vue'
import FormsIcon from './components/Icons/FormsIcon.vue'
import OcsResponse2Data from './utils/OcsResponse2Data.js'
import PermissionTypes from './mixins/PermissionTypes.js'
import Sidebar from './views/Sidebar.vue'
import logger from './utils/Logger.js'
import { FormState } from './models/Constants.ts'

export default {
	name: 'Forms',

	components: {
		AppNavigationForm,
		ArchivedFormsModal,
		FormsIcon,
		IconArchive,
		IconPlus,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationCaption,
		NcAppNavigationNew,
		NcButton,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
		Sidebar,
	},

	mixins: [PermissionTypes],

	setup() {
		return {
			isMobile: useIsMobile(),
		}
	},

	data() {
		return {
			loading: true,
			sidebarOpened: false,
			sidebarActive: 'forms-sharing',
			forms: [],
			allSharedForms: [],

			showArchivedForms: false,

			canCreateForms: loadState(appName, 'appConfig').canCreateForms,
		}
	},

	computed: {
		canEdit() {
			return this.selectedForm.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
			)
		},

		hasForms() {
			return this.allSharedForms.length > 0 || this.forms.length > 0
		},

		/**
		 * All own active forms
		 */
		ownedForms() {
			return this.forms.filter((form) => form.state !== FormState.FormArchived)
		},

		/**
		 * All active shared forms
		 */
		sharedForms() {
			return this.allSharedForms.filter(
				(form) => form.state !== FormState.FormArchived,
			)
		},

		/**
		 * All forms that have been archived
		 */
		archivedForms() {
			return [...this.forms, ...this.allSharedForms].filter(
				(form) => form.state === FormState.FormArchived,
			)
		},

		routeHash() {
			return this.$route.params.hash
		},

		// If the user is allowed to access this route
		routeAllowed() {
			// Check formId from initial state on app initialization
			if (this.loading && loadState(appName, 'formId') === 'invalid') {
				return false
			}

			// Not allowed, if no hash
			if (!this.routeHash) {
				return false
			}

			// Try to find form in owned & shared list
			const form = [...this.forms, ...this.allSharedForms].find(
				(form) => form.hash === this.routeHash,
			)

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
					return this.forms
						.concat(this.allSharedForms)
						.find((form) => form.hash === this.routeHash)
				}
				return {}
			},
			set(form) {
				// always close sidebar
				this.sidebarOpened = false

				// If a owned form
				let index = this.forms.findIndex(
					(search) => search.hash === this.routeHash,
				)
				if (index > -1) {
					this.$set(this.forms, index, form)
					return
				}
				// Otherwise a shared form
				index = this.allSharedForms.findIndex(
					(search) => search.hash === this.routeHash,
				)
				if (index > -1) {
					this.$set(this.allSharedForms, index, form)
				}
			},
		},
	},

	beforeMount() {
		this.loadForms()
	},

	mounted() {
		subscribe('forms:last-updated:set', (id) => this.onLastUpdatedByEventBus(id))
		subscribe('forms:ownership-transfered', (id) => this.onDeleteForm(id))
	},

	unmounted() {
		unsubscribe('forms:last-updated:set', (id) =>
			this.onLastUpdatedByEventBus(id),
		)
		unsubscribe('forms:ownership-transfered', (id) => this.onDeleteForm(id))
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
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms'),
				)
				this.forms = OcsResponse2Data(response)
			} catch (error) {
				logger.error('Error while loading owned forms list', { error })
				showError(
					t('forms', 'An error occurred while loading the forms list'),
				)
			}

			// Load shared forms
			try {
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms?type=shared'),
				)
				this.allSharedForms = OcsResponse2Data(response)
			} catch (error) {
				logger.error('Error while loading shared forms list', {
					error,
				})
				showError(
					t('forms', 'An error occurred while loading the forms list'),
				)
			}

			this.loading = false
		},

		/**
		 * Fetch a partial form by its hash and add it to the shared forms list.
		 *
		 * @param {string} hash the hash of the form to load
		 */
		async fetchPartialForm(hash) {
			await new Promise((resolve) => {
				const wait = () => {
					if (this.loading) {
						window.setTimeout(wait, 250)
					} else {
						resolve()
					}
				}
				wait()
			})

			this.loading = true
			if (
				[...this.forms, ...this.allSharedForms].find(
					(form) => form.hash === hash,
				) === undefined
			) {
				try {
					const response = await axios.get(
						generateOcsUrl('apps/forms/api/v3/forms/{id}', {
							id: loadState(appName, 'formId'),
						}),
					)
					const form = OcsResponse2Data(response)

					// If the user has (at least) submission-permissions, add it to the shared forms
					if (
						form.permissions.includes(
							this.PERMISSION_TYPES.PERMISSION_SUBMIT,
						)
					) {
						this.allSharedForms.push(form)
					}
				} catch (error) {
					logger.error(`Form ${hash} not found`, { error })
					showError(t('forms', 'Form not found'))

					if ([403, 404].includes(error.response?.status)) {
						if (this.$route.name !== 'root') {
							this.$router.push({ name: 'root' })
						}
					}
				}
			}

			this.loading = false
		},

		async onNewForm() {
			try {
				// Request a new empty form
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms'),
				)
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({
					name: 'edit',
					params: { hash: newForm.hash },
				})
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
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms?fromId={id}', {
						id,
					}),
				)
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({
					name: 'edit',
					params: { hash: newForm.hash },
				})
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
			const formIndex = this.forms.findIndex((form) => form.id === id)
			const deletedHash = this.forms[formIndex].hash

			this.forms.splice(formIndex, 1)

			// Redirect if current form has been deleted
			if (deletedHash === this.routeHash && this.$route.name !== 'root') {
				this.$router.push({ name: 'root' })
			}
		},

		/**
		 * Update last updated timestamp in given form
		 *
		 * @param {number} id the form id
		 */
		onLastUpdatedByEventBus(id) {
			const formIndex = this.forms.findIndex((form) => form.id === id)
			if (formIndex !== -1) {
				this.forms[formIndex].lastUpdated = Math.floor(Date.now() / 1000)
				this.forms.sort((b, a) => a.lastUpdated - b.lastUpdated)
			} else {
				const sharedFormIndex = this.allSharedForms.findIndex(
					(form) => form.id === id,
				)
				this.allSharedForms[sharedFormIndex].lastUpdated = Math.floor(Date.now() / 1000)
				this.allSharedForms.sort((b, a) => a.lastUpdated - b.lastUpdated)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.forms-navigation-footer {
	display: flex;
	flex-direction: column;
	padding: var(--app-navigation-padding);
}

// Fix the margin of the lists
.forms-navigation__list-heading {
	margin-block: calc(var(--default-grid-baseline) * 2) 0 !important;

	:deep(h2) {
		// Make the list more condensed
		margin-block: 0;
	}
}

.forms-emptycontent {
	height: 100%;
}
</style>
