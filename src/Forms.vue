<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent appName="forms">
		<NcAppNavigation
			v-if="canCreateForms || hasForms"
			:aria-label="t('forms', 'Forms navigation')">
			<NcAppNavigationNew
				v-if="canCreateForms"
				:text="t('forms', 'New form')"
				@click="onNewForm">
				<template #icon>
					<NcIconSvgWrapper :svg="IconPlus" />
				</template>
			</NcAppNavigationNew>

			<!-- Form-Owner-->
			<template v-if="ownedForms.length > 0">
				<NcAppNavigationCaption
					isHeading
					class="forms-navigation__list-heading"
					headingId="forms-navigation-your-forms"
					:name="t('forms', 'Your forms')" />
				<ul aria-labelledby="forms-navigation-your-forms">
					<AppNavigationForm
						v-for="form in ownedForms"
						:key="form.id"
						:form="form"
						@openSharing="openSharing"
						@mobileCloseNavigation="mobileCloseNavigation"
						@clone="onCloneForm"
						@delete="onDeleteForm" />
				</ul>
			</template>

			<!-- Shared Forms-->
			<template v-if="sharedForms.length > 0">
				<NcAppNavigationCaption
					isHeading
					class="forms-navigation__list-heading"
					headingId="forms-navigation-shared-forms"
					:name="t('forms', 'Shared with you')" />
				<ul aria-labelledby="forms-navigation-shared-forms">
					<AppNavigationForm
						v-for="form in sharedForms"
						:key="form.id"
						:form="form"
						readOnly
						@openSharing="openSharing"
						@clone="onCloneForm"
						@mobileCloseNavigation="mobileCloseNavigation" />
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
							<NcIconSvgWrapper :svg="IconArchive" />
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
				:name="t('forms', 'Loading forms …')">
				<template #icon>
					<NcLoadingIcon :size="64" />
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="!hasForms"
				class="forms-emptycontent"
				:name="t('forms', 'No forms created yet')">
				<template #icon>
					<NcIconSvgWrapper :svg="FormsIcon" :size="64" />
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
					<NcIconSvgWrapper :svg="FormsIcon" :size="64" />
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
				isLoggedIn
				:sidebarOpened="sidebarOpened"
				@update:form="updateSelectedForm"
				@update:sidebarOpened="sidebarOpened = $event"
				@openSharing="openSharing" />
			<Sidebar
				v-if="
					!selectedForm.partial
					&& (canEdit || (allowComments && selectedForm.allowComments))
				"
				:form="selectedForm"
				:sidebarOpened="sidebarOpened"
				:active="sidebarActive"
				@update:sidebarOpened="sidebarOpened = $event"
				@update:active="sidebarActive = $event" />
		</template>

		<!-- Archived forms modal -->
		<ArchivedFormsModal
			v-model:open="showArchivedForms"
			:forms="archivedForms"
			@clone="onCloneForm" />
	</NcContent>
</template>

<script lang="ts">
import type { FormsForm } from './models/Entities.d.ts'

import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconArchive from '@material-symbols/svg-400/outlined/archive.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue'
import { defineComponent } from 'vue'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AppNavigationForm from './components/AppNavigationForm.vue'
import ArchivedFormsModal from './components/ArchivedFormsModal.vue'
import Sidebar from './views/Sidebar.vue'
import FormsIcon from '../img/forms-dark.svg?raw'
import { FormState } from './models/Constants.ts'
import logger from './utils/Logger.ts'
import OcsResponse2Data from './utils/OcsResponse2Data.ts'

const formsAppName = 'forms'

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Forms',

	components: {
		AppNavigationForm,
		ArchivedFormsModal,
		NcIconSvgWrapper,
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
		const forms = ref<FormsForm[]>([])
		const allSharedForms = ref<FormsForm[]>([])
		const showArchivedForms = ref(false)
		const appConfig = loadState(formsAppName, 'appConfig') as {
			canCreateForms?: boolean
			allowComments?: boolean
		}
		const canCreateForms = ref(Boolean(appConfig?.canCreateForms))
		const allowComments = ref(Boolean(appConfig?.allowComments))
		const deletedFormHash = ref<string | null>(null)

		const PERMISSION_TYPES = {
			PERMISSION_EDIT: 'edit',
			PERMISSION_SUBMIT: 'submit',
		}

		const routeHash = computed<string | undefined>(() => {
			const hash = route.params.hash
			if (Array.isArray(hash)) {
				return hash[0]
			}
			return hash as string | undefined
		})

		const routeAllowed = computed<boolean>(() => {
			if (loading.value && loadState(formsAppName, 'formId') === 'invalid') {
				return false
			}

			if (!routeHash.value) {
				return false
			}

			// Don't try to fetch if this form was just deleted
			if (deletedFormHash.value === routeHash.value) {
				return false
			}

			const form = [...forms.value, ...allSharedForms.value].find(
				(form) => form.hash === routeHash.value,
			)

			if (form === undefined) {
				fetchPartialForm(routeHash.value)
				return false
			}

			const resultRoutes = ['results', 'results.summary', 'results.responses']
			if (resultRoutes.includes(String(route.name ?? ''))) {
				return (
					form.permissions.includes('results')
					|| (form.submissionCount ?? 0) > 0
				)
			}

			return form?.permissions.includes(String(route.name ?? ''))
		})

		const selectedForm = computed<FormsForm | Record<string, never>>(() => {
			if (routeAllowed.value) {
				return (
					[...forms.value, ...allSharedForms.value].find(
						(form) => form.hash === routeHash.value,
					) || {}
				)
			}
			return {}
		})

		const updateSelectedForm = (form: FormsForm): void => {
			sidebarOpened.value = false

			const index = forms.value.findIndex((f) => f.hash === form.hash)
			if (index > -1) {
				forms.value[index] = form
				return
			}

			const sharedIndex = allSharedForms.value.findIndex(
				(f) => f.hash === form.hash,
			)
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
			return forms.value.filter(
				(form) => form.state !== FormState.FormArchived,
			)
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

		const mobileCloseNavigation = () => {
			if (isMobile.value) {
				emit('toggle-navigation', { open: false })
			}
		}

		const openSharing = (hash: string): void => {
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

			// Load shared forms
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

		/**
		 * Clean up stale localStorage entries for forms that are no longer available.
		 * Removes localStorage keys matching the pattern `nextcloud_forms_*_activeResponseView`
		 * where the form hash no longer exists in the current forms list.
		 */
		const cleanupStaleLocalStorageEntries = (): void => {
			try {
				// Get all current form hashes
				const currentFormHashes = new Set(
					[...forms.value, ...allSharedForms.value].map(
						(form) => form.hash,
					),
				)

				// Iterate through all localStorage keys
				const keysToRemove = []
				for (let i = 0; i < localStorage.length; i++) {
					const key = localStorage.key(i)
					if (
						key
						&& key.startsWith('nextcloud_forms_')
						&& key.endsWith('_activeResponseView')
					) {
						// Extract hash from key: nextcloud_forms_<hash>_activeResponseView
						const hash = key.substring(
							'nextcloud_forms_'.length,
							key.length - '_activeResponseView'.length,
						)
						// If form hash is not in current forms, mark for removal
						if (!currentFormHashes.has(hash)) {
							keysToRemove.push(key)
						}
					}
				}

				// Remove stale entries
				keysToRemove.forEach((key) => {
					localStorage.removeItem(key)
					logger.debug(`Removed stale localStorage entry: ${key}`)
				})
			} catch (err) {
				logger.debug('Error cleaning up stale localStorage entries', {
					error: err,
				})
			}
		}

		/**
		 * Fetch a partial form by its hash after initial load completes.
		 *
		 * @param hash The hash of the form to fetch.
		 */
		async function fetchPartialForm(hash: string): Promise<void> {
			await new Promise<void>((resolve) => {
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
							id: loadState(formsAppName, 'formId'),
						}),
					)
					const form = OcsResponse2Data<FormsForm>(response)

					if (
						form.permissions.includes(PERMISSION_TYPES.PERMISSION_SUBMIT)
					) {
						allSharedForms.value.push(form)
					}
				} catch (error: unknown) {
					logger.error(`Form ${hash} not found`, { error })
					showError(t('forms', 'Form not found'))

					if (
						typeof error === 'object'
						&& error !== null
						&& 'response' in error
						&& [403, 404].includes(
							(error as { response?: { status?: number } }).response
								?.status ?? 0,
						)
					) {
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
				const newForm = OcsResponse2Data<FormsForm>(response)
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

		const onCloneForm = async (id: number): Promise<void> => {
			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms?fromId={id}', {
						id,
					}),
				)
				const newForm = OcsResponse2Data<FormsForm>(response)
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

		const onDeleteForm = async (id: number): Promise<void> => {
			const formIndex = forms.value.findIndex((form) => form.id === id)
			if (formIndex < 0) {
				return
			}
			const deletedHash = forms.value[formIndex].hash

			forms.value.splice(formIndex, 1)
			deletedFormHash.value = deletedHash

			// Remove localStorage entry for this form's active response view
			try {
				localStorage.removeItem(
					`nextcloud_forms_${deletedHash}_activeResponseView`,
				)
			} catch (err) {
				logger.debug('Error removing localStorage entry for deleted form', {
					error: err,
				})
			}

			if (deletedHash === routeHash.value && route.name !== 'root') {
				// Navigate to root without triggering route guards
				router.replace({ name: 'root' })
			}
		}

		// Reset deletedFormHash when navigating away from the deleted form
		watch(
			() => route.name,
			(newRouteName: string | symbol | null | undefined) => {
				if (newRouteName === 'root') {
					deletedFormHash.value = null
				}
			},
		)

		const onLastUpdatedByEventBus = (id: number): void => {
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

		const onLastUpdatedByEventBusEvent = (event: unknown): void => {
			const id = Number(event)
			if (Number.isFinite(id)) {
				onLastUpdatedByEventBus(id)
			}
		}

		const onOwnershipTransferredEvent = (event: unknown): void => {
			const id = Number(event)
			if (Number.isFinite(id)) {
				void onDeleteForm(id)
			}
		}

		onMounted(async () => {
			await loadForms()
			cleanupStaleLocalStorageEntries()
			subscribe('forms:last-updated:set', onLastUpdatedByEventBusEvent)
			subscribe('forms:ownership-transfered', onOwnershipTransferredEvent)
		})

		onUnmounted(() => {
			unsubscribe('forms:last-updated:set', onLastUpdatedByEventBusEvent)
			unsubscribe('forms:ownership-transfered', onOwnershipTransferredEvent)
		})

		return {
			t,
			loading,
			sidebarOpened,
			sidebarActive,
			forms,
			allSharedForms,
			showArchivedForms,
			canCreateForms,
			allowComments,
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
			IconPlus,
			IconArchive,
			FormsIcon,
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
