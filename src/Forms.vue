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
				:form="selectedForm"
				:sidebar-opened="sidebarOpened"
				@update:form="updateSelectedForm"
				@update:sidebar-opened="sidebarOpened = $event"
				@open-sharing="openSharing" />
			<Sidebar
				v-if="!selectedForm.partial && canEdit"
				:form="selectedForm"
				:sidebar-opened="sidebarOpened"
				:active="sidebarActive"
				@update:sidebar-opened="sidebarOpened = $event"
				@update:active="sidebarActive = $event" />
		</template>
		<!-- Archived forms modal -->
		<ArchivedFormsModal
			v-model:open="showArchivedForms"
			:forms="archivedForms"
			@clone="onCloneForm" />
	</NcContent>
</template>

<script>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconArchive from 'vue-material-design-icons/ArchiveOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import AppNavigationForm from './components/AppNavigationForm.vue'
import ArchivedFormsModal from './components/ArchivedFormsModal.vue'
import FormsIcon from './components/Icons/FormsIcon.vue'
import Sidebar from './views/Sidebar.vue'
import PermissionTypes from './mixins/PermissionTypes.js'
import { FormState } from './models/Constants.ts'
import logger from './utils/Logger.js'
import OcsResponse2Data from './utils/OcsResponse2Data.js'

const appName = 'forms'

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

	setup() {
		const route = useRoute()
		const router = useRouter()
		const isMobile = useIsMobile()

		const loading = ref(true)
		const sidebarOpened = ref(false)
		const sidebarActive = ref('forms-sharing')
		const forms = ref([])
		const allSharedForms = ref([])
		const showArchivedForms = ref(false)
		const canCreateForms = ref(loadState(appName, 'appConfig').canCreateForms)

		const PERMISSION_TYPES = PermissionTypes.data().PERMISSION_TYPES

		const selectedForm = computed(() => {
			if (routeAllowed.value) {
				return [...forms.value, ...allSharedForms.value].find(
					(form) => form.hash === routeHash.value,
				) || {}
			}
			return {}
		})

		const updateSelectedForm = (form) => {
			sidebarOpened.value = false

			const index = forms.value.findIndex((f) => f.hash === form.hash)
			if (index > -1) {
				forms.value[index] = form
				return
			}

			const sharedIndex = allSharedForms.value.findIndex((f) => f.hash === form.hash)
			if (sharedIndex > -1) {
				allSharedForms.value[sharedIndex] = form
			}
		}

		const canEdit = computed(() => {
			return selectedForm.value.permissions?.includes(
				PERMISSION_TYPES.PERMISSION_EDIT,
			)
		})

		const hasForms = computed(() => {
			return allSharedForms.value.length > 0 || forms.value.length > 0
		})

		const ownedForms = computed(() => {
			return forms.value.filter((form) => form.state !== FormState.FormArchived)
		})

		const sharedForms = computed(() => {
			return allSharedForms.value.filter(
				(form) => form.state !== FormState.FormArchived,
			)
		})

		const archivedForms = computed(() => {
			return [...forms.value, ...allSharedForms.value].filter(
				(form) => form.state === FormState.FormArchived,
			)
		})

		const routeHash = computed(() => route.params.hash)

		const routeAllowed = computed(() => {
			if (loading.value && loadState(appName, 'formId') === 'invalid') {
				return false
			}

			if (!routeHash.value) {
				return false
			}

			const form = [...forms.value, ...allSharedForms.value].find(
				(form) => form.hash === routeHash.value,
			)

			if (form === undefined) {
				fetchPartialForm(routeHash.value)
				return false
			}

			return form?.permissions.includes(route.name)
		})

		const mobileCloseNavigation = () => {
			if (isMobile.value) {
				emit('toggle-navigation', { open: false })
			}
		}

		const openSharing = (hash) => {
			if (hash !== routeHash.value) {
				router.push({ name: 'edit', params: { hash } })
			}
			sidebarActive.value = 'forms-sharing'
			sidebarOpened.value = true
		}

		const loadForms = async () => {
			loading.value = true

			try {
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms'),
				)
				forms.value = OcsResponse2Data(response)
			} catch (error) {
				logger.error('Error while loading owned forms list', { error })
				showError(
					t('forms', 'An error occurred while loading the forms list'),
				)
			}

			try {
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms?type=shared'),
				)
				allSharedForms.value = OcsResponse2Data(response)
			} catch (error) {
				logger.error('Error while loading shared forms list', {
					error,
				})
				showError(
					t('forms', 'An error occurred while loading the forms list'),
				)
			}

			loading.value = false
		}

		const fetchPartialForm = async (hash) => {
			await new Promise((resolve) => {
				const wait = () => {
					if (loading.value) {
						window.setTimeout(wait, 250)
					} else {
						resolve()
					}
				}
				wait()
			})

			loading.value = true
			if (
				[...forms.value, ...allSharedForms.value].find(
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

					if (
						form.permissions.includes(
							PERMISSION_TYPES.PERMISSION_SUBMIT,
						)
					) {
						allSharedForms.value.push(form)
					}
				} catch (error) {
					logger.error(`Form ${hash} not found`, { error })
					showError(t('forms', 'Form not found'))

					if ([403, 404].includes(error.response?.status)) {
						if (route.name !== 'root') {
							router.push({ name: 'root' })
						}
					}
				}
			}

			loading.value = false
		}

		const onNewForm = async () => {
			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms'),
				)
				const newForm = OcsResponse2Data(response)
				forms.value.unshift(newForm)
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

		const onCloneForm = async (id) => {
			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms?fromId={id}', {
						id,
					}),
				)
				const newForm = OcsResponse2Data(response)
				forms.value.unshift(newForm)
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

		const onDeleteForm = async (id) => {
			const formIndex = forms.value.findIndex((form) => form.id === id)
			const deletedHash = forms.value[formIndex].hash

			forms.value.splice(formIndex, 1)

			if (deletedHash === routeHash.value && route.name !== 'root') {
				router.push({ name: 'root' })
			}
		}

		const onLastUpdatedByEventBus = (id) => {
			const formIndex = forms.value.findIndex((form) => form.id === id)
			if (formIndex !== -1) {
				forms.value[formIndex].lastUpdated = moment().unix()
				forms.value.sort((b, a) => a.lastUpdated - b.lastUpdated)
			} else {
				const sharedFormIndex = allSharedForms.value.findIndex(
					(form) => form.id === id,
				)
				allSharedForms.value[sharedFormIndex].lastUpdated = moment().unix()
				allSharedForms.value.sort((b, a) => a.lastUpdated - b.lastUpdated)
			}
		}

		onMounted(() => {
			loadForms()
			subscribe('forms:last-updated:set', onLastUpdatedByEventBus)
			subscribe('forms:ownership-transfered', onDeleteForm)
		})

		onUnmounted(() => {
			unsubscribe('forms:last-updated:set', onLastUpdatedByEventBus)
			unsubscribe('forms:ownership-transfered', onDeleteForm)
		})

		return {
			loading,
			sidebarOpened,
			sidebarActive,
			forms,
			allSharedForms,
			showArchivedForms,
			canCreateForms,
			isMobile,
			selectedForm,
			updateSelectedForm,
			canEdit,
			hasForms,
			ownedForms,
			sharedForms,
			archivedForms,
			routeHash,
			routeAllowed,
			mobileCloseNavigation,
			openSharing,
			loadForms,
			fetchPartialForm,
			onNewForm,
			onCloneForm,
			onDeleteForm,
			onLastUpdatedByEventBus,
		}
	},
}
</script>

<style scoped lang="scss">
.forms-navigation-footer {
	display: flex;
	flex-direction: column;
	padding: var(--app-navigation-padding);
}

.forms-navigation__list-heading {
	margin-block: calc(var(--default-grid-baseline) * 2) 0 !important;

	:deep(h2) {
		margin-block: 0;
	}
}

.forms-emptycontent {
	height: 100%;
}
</style>
