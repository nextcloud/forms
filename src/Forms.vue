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
import { ref, computed, onMounted, onUnmounted, defineComponent } from 'vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationCaption from '@nextcloud/vue/dist/Components/NcAppNavigationCaption.js'
import NcAppNavigationNew from '@nextcloud/vue/dist/Components/NcAppNavigationNew.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

import IconArchive from 'vue-material-design-icons/Archive.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import ArchivedFormsModal from './components/ArchivedFormsModal.vue'
import AppNavigationForm from './components/AppNavigationForm.vue'
import FormsIcon from './components/Icons/FormsIcon.vue'
import OcsResponse2Data from './utils/OcsResponse2Data.js'
import Sidebar from './views/Sidebar.vue'
import logger from './utils/Logger.js'
import { FormState } from './models/Constants.ts'

export default defineComponent({
	name: 'Forms',

	components: {
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationCaption,
		NcAppNavigationNew,
		NcButton,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
		IconArchive,
		IconPlus,
		ArchivedFormsModal,
		AppNavigationForm,
		FormsIcon,
		Sidebar,
	},

	setup(_, { emit }) {
		const appName = 'forms'
		const route = window.VueRouter.default.currentRoute
		const router = window.VueRouter.default
		const isMobile = useIsMobile()

		const loading = ref(true)
		const sidebarOpened = ref(false)
		const sidebarActive = ref('forms-sharing')
		const forms = ref([])
		const allSharedForms = ref([])
		const showArchivedForms = ref(false)
		const canCreateForms = ref(loadState(appName, 'appConfig').canCreateForms)

		const hasForms = computed(() => {
			return forms.value.length > 0 || allSharedForms.value.length > 0
		})

		const ownedForms = computed(() => {
			return forms.value.filter((form) => form.state === FormState.Active)
		})

		const sharedForms = computed(() => {
			return allSharedForms.value.filter(
				(form) => form.state === FormState.Active,
			)
		})

		const archivedForms = computed(() => {
			return [...forms.value, ...allSharedForms.value].filter(
				(form) => form.state === FormState.Archived,
			)
		})

		const routeHash = computed(() => route.params.hash)

		const routeAllowed = computed(() => {
			if (!routeHash.value) return false

			const form =
				forms.value.find((f) => f.hash === routeHash.value) ||
				allSharedForms.value.find((f) => f.hash === routeHash.value)

			if (!form) return false

			// Check if user has at least read permissions
			return form.permissions.includes('read')
		})

		// Methods
		const openSharing = (hash) => {
			if (hash !== routeHash.value) {
				router.push({ name: 'edit', params: { hash } })
			}
			sidebarActive.value = 'forms-sharing'
			sidebarOpened.value = true

			// If on mobile, close the navigation
			if (isMobile.value) {
				emit('toggle-navigation', { open: false })
			}
		}

		const loadForms = async () => {
			try {
				loading.value = true
				const [formsResponse, sharedFormsResponse] = await Promise.all([
					axios.get(generateOcsUrl('apps/forms/api/v3/forms')),
					axios.get(generateOcsUrl('apps/forms/api/v3/shared')),
				])

				forms.value = OcsResponse2Data(formsResponse)
				allSharedForms.value = OcsResponse2Data(sharedFormsResponse)

				// If we have a hash in the route, ensure the form is loaded
				if (route.params.hash) {
					await fetchPartialForm(route.params.hash)
				}
			} catch (error) {
				logger.error('Error loading forms', { error })
				showError(t('forms', 'An error occurred while loading forms'))
			} finally {
				loading.value = false
			}
		}

		const fetchPartialForm = async (hash) => {
			try {
				const response = await axios.get(
					generateOcsUrl(`apps/forms/api/v3/form/${hash}`),
				)
				const form = OcsResponse2Data(response)

				// Update the form in the appropriate array
				let index = forms.value.findIndex((f) => f.hash === hash)
				if (index > -1) {
					const newForms = [...forms.value]
					newForms[index] = form
					forms.value = newForms
				} else {
					index = allSharedForms.value.findIndex((f) => f.hash === hash)
					if (index > -1) {
						const newSharedForms = [...allSharedForms.value]
						newSharedForms[index] = form
						allSharedForms.value = newSharedForms
					}
				}

				return form
			} catch (error) {
				logger.error('Error while loading form', { error, hash })
				showError(t('forms', 'An error occurred while loading the form'))
				throw error
			}
		}

		const onNewForm = async () => {
			try {
				// Request a new empty form
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms'),
				)
				const newForm = OcsResponse2Data(response)
				updateFormInArrays(newForm)
				router.push({
					name: 'edit',
					params: { hash: newForm.hash },
				})
				mobileCloseNavigation()
			} catch (error) {
				logger.error('Unable to create new form', { error })
				showError(t('forms', 'Unable to create a new form'))
			}
		}

		const onDeleteForm = async (id) => {
			const formIndex = forms.value.findIndex((form) => form.id === id)
			if (formIndex === -1) return

			const deletedHash = forms.value[formIndex].hash

			try {
				await axios.delete(
					generateOcsUrl(`apps/forms/api/v3/form/${deletedHash}`),
				)

				// Remove the form from the forms array
				forms.value = forms.value.filter((form) => form.id !== id)

				// Redirect if current form has been deleted
				if (deletedHash === route.hash && route.name !== 'root') {
					router.push({ name: 'root' })
				}
			} catch (error) {
				logger.error(`Error deleting form ${id}`, { error })
				showError(t('forms', 'Error deleting form'))
			}
		}

		const onLastUpdatedByEventBus = (id) => {
			const formIndex = forms.value.findIndex((form) => form.id === id)
			if (formIndex !== -1) {
				const newForms = [...forms.value]
				newForms[formIndex] = {
					...newForms[formIndex],
					lastUpdated: Math.floor(Date.now() / 1000),
				}
				forms.value = newForms.sort((a, b) => b.lastUpdated - a.lastUpdated)
			} else {
				const sharedFormIndex = allSharedForms.value.findIndex(
					(form) => form.id === id,
				)

				if (sharedFormIndex !== -1) {
					const newSharedForms = [...allSharedForms.value]
					newSharedForms[sharedFormIndex] = {
						...newSharedForms[sharedFormIndex],
						lastUpdated: Math.floor(Date.now() / 1000),
					}
					allSharedForms.value = newSharedForms.sort(
						(a, b) => b.lastUpdated - a.lastUpdated,
					)
				}
			}
		}

		const mobileCloseNavigation = () => {
			if (isMobile.value) {
				emit('toggle-navigation', { open: false })
			}
			sidebarActive.value = 'forms-sharing'
			sidebarOpened.value = true
		}

		const updateFormInArrays = (form) => {
			let index = forms.value.findIndex((f) => f.hash === form.hash)
			if (index > -1) {
				const newForms = [...forms.value]
				newForms[index] = form
				forms.value = newForms
			} else {
				index = allSharedForms.value.findIndex((f) => f.hash === form.hash)
				if (index > -1) {
					const newSharedForms = [...allSharedForms.value]
					newSharedForms[index] = form
					allSharedForms.value = newSharedForms
				} else if (form.permissions?.includes('submit')) {
					allSharedForms.value.push(form)
				}
			}
		}

		onMounted(() => {
			loadForms()

			subscribe('forms:form:updated', onLastUpdatedByEventBus)

			return () => {
				unsubscribe('forms:form:updated', onLastUpdatedByEventBus)
			}
		})

		onMounted(() => {
			loadForms()
			subscribe('forms:last-updated:set', onLastUpdatedByEventBus)
			subscribe('forms:ownership-transfered', onDeleteForm)
		})

		onUnmounted(() => {
			unsubscribe('forms:last-updated:set', onLastUpdatedByEventBus)
			unsubscribe('forms:ownership-transfered', onDeleteForm)
		})

		const onCloneForm = async (id) => {
			try {
				const response = await axios.post(
					generateOcsUrl(`apps/forms/api/v3/forms?fromId=${id}`),
				)
				const newForm = OcsResponse2Data(response)
				updateFormInArrays(newForm)
				router.push({
					name: 'edit',
					params: { hash: newForm.hash },
				})
				mobileCloseNavigation()
			} catch (error) {
				logger.error(`Unable to copy form ${id}`, { error })
				showError(t('forms', 'Unable to copy form'))
			}
		}

		return {
			loading,
			sidebarOpened,
			sidebarActive,
			forms,
			allSharedForms,
			showArchivedForms,
			canCreateForms,
			isMobile,
			route,
			router,

			hasForms,
			ownedForms,
			sharedForms,
			archivedForms,
			routeHash,
			routeAllowed,
			selectedForm: {
				get() {
					if (!routeHash.value) return { partial: true }

					const form =
						forms.value.find((f) => f.hash === routeHash.value) ||
						allSharedForms.value.find((f) => f.hash === routeHash.value)

					return form || { partial: true, hash: routeHash.value }
				},
				set(form) {
					if (form.partial) {
						fetchPartialForm(form.hash)
						return
					}

					// Update in the appropriate array
					let index = forms.value.findIndex((f) => f.hash === form.hash)
					if (index > -1) {
						const newForms = [...forms.value]
						newForms[index] = form
						forms.value = newForms
					} else {
						index = allSharedForms.value.findIndex(
							(f) => f.hash === form.hash,
						)
						if (index > -1) {
							const newSharedForms = [...allSharedForms.value]
							newSharedForms[index] = form
							allSharedForms.value = newSharedForms
						}
					}
				},
			},

			openSharing,
			loadForms,
			fetchPartialForm,
			onNewForm,
			onCloneForm,
			onDeleteForm,
			onLastUpdatedByEventBus,
			mobileCloseNavigation,
			updateFormInArrays,
		}
	},
})
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
