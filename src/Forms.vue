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
					:name="t('forms', 'Your forms')">
					<template #actions>
						<NcActionButton @click="onUploadForm()">
							<template #icon>
								<NcIconSvgWrapper :svg="IconUpload" />
							</template>
							{{ t('forms', 'Import form') }}
						</NcActionButton>
					</template>
				</NcAppNavigationCaption>
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
					<div class="form-buttons">
						<NcButton variant="primary" @click="onNewForm">
							{{ t('forms', 'Create a form') }}
						</NcButton>
						<NcButton variant="secondary" @click="onUploadForm">
							{{ t('forms', 'Import a form') }}
						</NcButton>
					</div>
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
					<div class="form-buttons">
						<NcButton variant="primary" @click="onNewForm">
							{{ t('forms', 'Create new form') }}
						</NcButton>
						<NcButton variant="secondary" @click="onUploadForm">
							{{ t('forms', 'Import a form') }}
						</NcButton>
					</div>
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

		<!-- Import form modal -->
		<NcDialog
			v-model:open="showVersionMismatch"
			contentClasses="modal-content"
			:name="t('forms', 'Version mismatch')"
			outTransition
			@close="closeModal">
			<template #default>
				<!-- eslint-disable vue/no-v-html -->
				<p>
					{{
						t(
							'forms',
							'The version of the uploaded form is newer than the installed app version. Do you still want to import the form?',
						)
					}}
				</p>
			</template>
			<template #actions>
				<NcButton variant="error" @click="onImportForm">
					{{ t('forms', 'I understand, import this form') }}
				</NcButton>
			</template>
		</NcDialog>

		<!-- Archived forms modal -->
		<ArchivedFormsModal
			v-model:open="showArchivedForms"
			:forms="archivedForms"
			@clone="onCloneForm" />
	</NcContent>
</template>

<script>
import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconArchive from '@material-symbols/svg-400/outlined/archive.svg?raw'
import IconUpload from '@material-symbols/svg-400/outlined/upload.svg?raw'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { useIsMobile } from '@nextcloud/vue'
import semverCompare from 'semver/functions/compare'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AppNavigationForm from './components/AppNavigationForm.vue'
import ArchivedFormsModal from './components/ArchivedFormsModal.vue'
import Sidebar from './views/Sidebar.vue'
import FormsIcon from '../img/forms-dark.svg?raw'
import { version } from '../package.json'
import PermissionTypes from './mixins/PermissionTypes.ts'
import { FormState } from './models/Constants.ts'
import logger from './utils/Logger.ts'
import OcsResponse2Data from './utils/OcsResponse2Data.ts'

const appName = 'forms'

export default {
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
		NcActionButton,
		NcDialog,
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
		const showVersionMismatch = ref(false)
		let formForImport = undefined
		const canCreateForms = ref(loadState(appName, 'appConfig').canCreateForms)
		const allowComments = ref(loadState(appName, 'appConfig').allowComments)
		const deletedFormHash = ref(null)

		const PERMISSION_TYPES = PermissionTypes.data().PERMISSION_TYPES

		const routeHash = computed(() => route.params.hash)

		const routeAllowed = computed(() => {
			if (loading.value && loadState(appName, 'formId') === 'invalid') {
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

			if (route.name === 'results') {
				return (
					form.permissions.includes(route.name) || form.submissionCount > 0
				)
			}

			return form?.permissions.includes(route.name)
		})

		const selectedForm = computed(() => {
			if (routeAllowed.value) {
				return (
					[...forms.value, ...allSharedForms.value].find(
						(form) => form.hash === routeHash.value,
					) || {}
				)
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
		const cleanupStaleLocalStorageEntries = () => {
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
		 * @param {string} hash The hash of the form to fetch.
		 */
		async function fetchPartialForm(hash) {
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
						form.permissions.includes(PERMISSION_TYPES.PERMISSION_SUBMIT)
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

		const onImportForm = async () => {
			showVersionMismatch.value = false
			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms?import=1'),
					{ formData: formForImport },
				)
				const newForm = OcsResponse2Data(response)
				forms.value.unshift(newForm)
				router.push({
					name: 'edit',
					params: { hash: newForm.hash },
				})
				mobileCloseNavigation()
			} catch (error) {
				logger.error(`Unable to import form`, { error })
				showError(t('forms', 'Unable to import form'))
			}
		}

		const onUploadForm = () => {
			// Open file pickers
			const fileInput = document.createElement('input')
			fileInput.type = 'file'
			fileInput.accept = 'application/json'
			fileInput.click()

			fileInput.addEventListener('change', () => {
				const file = fileInput.files[0]
				if (file.type !== 'application/json' || file.size > 1000 * 1000) {
					showError(t('forms', 'Invalid file type or file too large'))
					return
				}

				const reader = new FileReader()
				reader.addEventListener('load', async () => {
					let formObject
					try {
						formObject = JSON.parse(reader.result)
					} catch (error) {
						logger.error('Invalid JSON in uploaded file', { error })
						showError(t('forms', 'Invalid JSON file'))
						return
					}
					if (!formObject.appVersion || !formObject.form) {
						showError(t('forms', 'Invalid form file format'))
						return
					}
					formForImport = formObject.form
					if (semverCompare(version, formObject.appVersion) === -1) {
						showVersionMismatch.value = true
					} else {
						await onImportForm()
					}
				})
				reader.readAsText(file)
			})
		}

		const closeModal = () => {
			showVersionMismatch.value = false
			formForImport = undefined
		}
		const onDownloadForm = async (id) => {
			try {
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id,
					}),
				)
				const form = OcsResponse2Data(response)

				// download only required values
				const download = {
					appVersion: version,
					form: {
						...form,
						// Remove unused values
						...[
							'hash',
							'ownerId',
							'created',
							'access',
							'lastUpdated',
							'lockedBy',
							'lockedUntil',
							'shares',
							'permissions',
							'canSubmit',
							'isMaxSubmissionsReached',
							'submissionCount',
						].reduce((prev, curr) => {
							prev[curr] = undefined
							return prev
						}, {}),

						id: undefined,
						questions: form.questions,
					},
				}
				// create blob and download
				const blob = new Blob([JSON.stringify(download)])
				const url = URL.createObjectURL(blob)
				const a = document.createElement('a')
				a.href = url
				const formTitle = form.title ? form.title : t('forms', 'New form')
				a.download = `${formTitle}.json`
				a.click()
				URL.revokeObjectURL(url)
			} catch (error) {
				logger.error(`Unable to download form ${id}`, { error })
				showError(t('forms', 'Unable to download form'))
			}
		}

		const onDeleteForm = async (id) => {
			const formIndex = forms.value.findIndex((form) => form.id === id)
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
			(newRouteName) => {
				if (newRouteName === 'root') {
					deletedFormHash.value = null
				}
			},
		)

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

		onMounted(async () => {
			await loadForms()
			cleanupStaleLocalStorageEntries()
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
			showVersionMismatch,
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
			onDownloadForm,
			onUploadForm,
			onDeleteForm,
			onImportForm,
			closeModal,
			onLastUpdatedByEventBus,
			IconPlus,
			IconArchive,
			IconUpload,
			FormsIcon,
		}
	},
}
</script>

<style scoped lang="scss">
.form-buttons {
	display: flex;
	justify-content: flex-end;
	gap: 6px;
}

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
