<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent :pageHeading="t('forms', 'Responses')">
		<NcDialog
			v-model:open="showLinkedFileNotAvailableDialog"
			:name="t('forms', 'Linked file not available')"
			:message="
				t(
					'forms',
					'Linked file is not available, would you like to link a new file?',
				)
			"
			:buttons="linkedFileNotAvailableButtons"
			size="normal"
			noClose />

		<TopBar
			:archived="isFormArchived"
			:locked="isFormLocked"
			:permissions="form?.permissions"
			:sidebarOpened="sidebarOpened"
			:submissionCount="form?.submissionCount"
			@shareForm="onShareForm" />

		<!-- Showing submissions -->
		<header>
			<h2 dir="auto">
				{{ formTitle }}
			</h2>
			<p>
				{{
					t('forms', '{amount} responses', {
						amount: filteredSubmissionsCount,
					})
				}}
			</p>

			<!-- View switcher between Summary and Responses -->
			<div class="response-actions">
				<PillMenu
					:active="activeResponseView"
					:disabled="noSubmissions"
					:options="responseViews"
					:groupLabel="t('forms', 'View mode')"
					class="response-actions__toggle"
					@update:active="onViewChange" />

				<!-- Action menu for cloud export and deletion -->
				<NcActions
					v-if="canExportSubmissions"
					:aria-label="t('forms', 'Options')"
					forceName
					:inline="isMobile ? 0 : 1"
					@blur="isDownloadActionOpened = false"
					@close="isDownloadActionOpened = false">
					<template v-if="!isDownloadActionOpened">
						<NcActionButton
							v-if="canEditForm && !form.fileId && !isFormLocked"
							@click="onLinkFile">
							<template #icon>
								<NcIconSvgWrapper :svg="IconLink" />
							</template>
							{{ t('forms', 'Create spreadsheet') }}
						</NcActionButton>
						<template v-if="canEditForm && form.fileId">
							<NcActionButton
								:href="fileUrl"
								type="tertiary-no-background">
								<template #icon>
									<NcIconSvgWrapper :svg="IconTable" />
								</template>
								{{ t('forms', 'Open spreadsheet') }}
							</NcActionButton>
							<NcActionButton closeAfterClick @click="onReExport">
								<template #icon>
									<NcIconSvgWrapper :svg="IconRefresh" />
								</template>
								{{ t('forms', 'Re-export spreadsheet') }}
							</NcActionButton>
							<NcActionButton
								closeAfterClick
								:disabled="isFormLocked"
								@click="onUnlinkFile">
								<template #icon>
									<NcIconSvgWrapper :svg="IconLinkVariantOff" />
								</template>
								{{ t('forms', 'Unlink spreadsheet') }}
							</NcActionButton>
							<NcActionSeparator v-if="!noSubmissions" />
						</template>
						<NcActionButton
							v-if="!noSubmissions"
							closeAfterClick
							@click="onStoreToFiles">
							<template #icon>
								<NcIconSvgWrapper :svg="IconFolder" />
							</template>
							{{ t('forms', 'Save copy to Files') }}
						</NcActionButton>
						<NcActionButton
							v-if="!noSubmissions"
							:closeAfterClick="false"
							isMenu
							@click="isDownloadActionOpened = true">
							<template #icon>
								<NcIconSvgWrapper :svg="IconDownload" />
							</template>
							{{ t('forms', 'Download') }}
						</NcActionButton>
						<NcActionButton
							v-if="canDeleteSubmissions && !noSubmissions"
							closeAfterClick
							@click="deleteAllSubmissions">
							<template #icon>
								<NcIconSvgWrapper :svg="IconDelete" />
							</template>
							{{ t('forms', 'Delete all responses') }}
						</NcActionButton>
					</template>

					<template v-else>
						<!-- Back to top-level button -->
						<NcActionButton @click="isDownloadActionOpened = false">
							<template #icon>
								<NcIconSvgWrapper :svg="IconChevronLeft" />
							</template>
							{{ t('forms', 'Download') }}
						</NcActionButton>
						<NcActionSeparator />
						<NcActionButton
							closeAfterClick
							@click="onDownloadFile('csv')">
							<template #icon>
								<NcIconSvgWrapper :svg="IconFileDelimited" />
							</template>
							CSV
						</NcActionButton>
						<NcActionButton
							closeAfterClick
							@click="onDownloadFile('ods')">
							<template #icon>
								<NcIconSvgWrapper :svg="IconTable" />
							</template>
							ODS
						</NcActionButton>
						<NcActionButton
							closeAfterClick
							@click="onDownloadFile('xlsx')">
							<template #icon>
								<NcIconSvgWrapper :svg="IconFileExcelOutline" />
							</template>
							XSLX
						</NcActionButton>
					</template>
				</NcActions>

				<div
					v-if="
						(!noSubmissions
							|| loadingResults
							|| submissionSearch.length > 0)
						&& !isSummaryView
					"
					class="search-wrapper">
					<NcTextField
						v-model="submissionSearch"
						:label="t('forms', 'Search')"
						trailingButtonIcon="close"
						:showTrailingButton="submissionSearch.length > 0"
						@trailingButtonClick="submissionSearch = ''">
						<template #icon>
							<NcIconSvgWrapper :svg="IconMagnify" />
						</template>
					</NcTextField>
				</div>
			</div>
		</header>

		<!-- Loading submissions -->
		<NcEmptyContent
			v-if="loadingResults"
			class="forms-emptycontent"
			:name="t('forms', 'Loading responses …')">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<!-- Empty search results -->
		<NcEmptyContent
			v-else-if="noFilteredSubmissions && submissionSearch.length > 0"
			:name="t('forms', 'No responses found')"
			class="forms-emptycontent"
			:description="
				t('forms', 'No responses found for \'{submissionSearch}\'', {
					submissionSearch,
				})
			">
			<template #icon>
				<NcIconSvgWrapper :svg="IconPoll" :size="64" />
			</template>
		</NcEmptyContent>

		<!-- No submissions -->
		<NcEmptyContent
			v-else-if="noSubmissions"
			:name="t('forms', 'No responses yet')"
			class="forms-emptycontent"
			:description="t('forms', 'Responses will show up here')">
			<template #icon>
				<NcIconSvgWrapper :svg="IconPoll" :size="64" />
			</template>
			<template #action>
				<div class="response-actions">
					<NcButton variant="primary" @click="onShareForm">
						<template #icon>
							<NcIconSvgWrapper :svg="IconShareVariant" />
						</template>
						{{ t('forms', 'Share form') }}
					</NcButton>
				</div>
			</template>
		</NcEmptyContent>

		<!-- Summary view for visualization -->
		<section v-else-if="isSummaryView">
			<ResultsSummary
				v-for="question in questions"
				:key="question.id"
				:question="question"
				:submissions="submissions" />
		</section>

		<!-- Responses view for individual responses -->
		<section v-else>
			<Submission
				v-for="submission in submissions"
				:key="submission.id"
				:formHash="form.hash"
				:submission="submission"
				:questions="questions"
				:highlight="submissionSearch"
				:canDeleteSubmission="canDeleteSubmission(submission.userId)"
				:canEditSubmission="canEditSubmission(submission.userId)"
				@delete="deleteSubmission(submission.id)" />

			<PaginationToolbar
				v-model:limit="limit"
				v-model:offset="offset"
				class="bottom-pagination"
				:totalItemsCount="filteredSubmissionsCount" />
		</section>

		<!-- Confirmation dialog for deleting all submissions -->
		<NcDialog
			v-model:open="showConfirmDeleteDialog"
			:name="t('forms', 'Delete responses')"
			:message="t('forms', 'Are you sure you want to delete all responses?')"
			:buttons="confirmDeleteButtons" />
	</NcAppContent>
</template>

<script lang="ts">
import type { INode } from '@nextcloud/files'
import type { FormsQuestion } from '../models/Entities.d.ts'

import IconPoll from '@material-symbols/svg-400/outlined/bar_chart.svg?raw'
import IconCancel from '@material-symbols/svg-400/outlined/block.svg?raw'
import IconChevronLeft from '@material-symbols/svg-400/outlined/chevron_left.svg?raw'
import IconFileDelimited from '@material-symbols/svg-400/outlined/csv.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconDownload from '@material-symbols/svg-400/outlined/download.svg?raw'
import IconFolder from '@material-symbols/svg-400/outlined/folder.svg?raw'
import IconLink from '@material-symbols/svg-400/outlined/link.svg?raw'
import IconLinkVariantOff from '@material-symbols/svg-400/outlined/link_off.svg?raw'
import IconRefresh from '@material-symbols/svg-400/outlined/refresh.svg?raw'
import IconMagnify from '@material-symbols/svg-400/outlined/search.svg?raw'
import IconShareVariant from '@material-symbols/svg-400/outlined/share.svg?raw'
import IconFileExcelOutline from '@material-symbols/svg-400/outlined/table.svg?raw'
import IconTable from '@material-symbols/svg-400/outlined/table_chart.svg?raw'
import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { FileType } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { useIsSmallMobile } from '@nextcloud/vue'
import debounce from 'debounce'
import { computed, defineComponent, nextTick, onBeforeMount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import PaginationToolbar from '../components/PaginationToolbar.vue'
import PillMenu from '../components/PillMenu.vue'
import ResultsSummary from '../components/Results/ResultsSummary.vue'
import Submission from '../components/Results/Submission.vue'
import TopBar from '../components/TopBar.vue'
import { useViewForm } from '../composables/useViewForm.ts'
import answerTypes from '../models/AnswerTypes.ts'
import { FormState, INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import { PERMISSION_TYPES } from '../models/Permissions.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import SetWindowTitle from '../utils/SetWindowTitle.ts'

type SupportedFileFormat = 'ods' | 'csv' | 'xlsx'

interface ResponseView {
	title: string
	id: 'summary' | 'responses'
}

const summaryResponseView: ResponseView = {
	title: t('forms', 'Summary'),
	id: 'summary',
}

const responsesResponseView: ResponseView = {
	title: t('forms', 'Responses'),
	id: 'responses',
}

interface SubmissionAnswer {
	id: number
	questionId: number
	text: string
	fileId?: number | null
	[key: string]: unknown
}

interface SubmissionRecord {
	id: number
	userId: string
	userDisplayName: string
	timestamp: number | string
	answers: SubmissionAnswer[]
	[key: string]: unknown
}

interface ResultsResponse {
	submissions: SubmissionRecord[]
	questions: FormsQuestion[]
	filteredSubmissionsCount: number
}

interface DialogButton {
	label: string
	icon: string
	variant: 'primary' | 'secondary' | 'tertiary' | 'error' | 'warning' | 'success'
	callback: () => void | Promise<void>
}

interface PickerLike {
	pick: () => Promise<string>
}

const SUPPORTED_FILE_FORMATS: Record<SupportedFileFormat, string> = {
	ods: IconTable,
	csv: IconFileDelimited,
	xlsx: IconFileExcelOutline,
}

let fileFormat: SupportedFileFormat = 'csv'

const responseViews: ResponseView[] = [summaryResponseView, responsesResponseView]

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Results',

	components: {
		NcActionButton,
		NcActionSeparator,
		NcActions,
		NcAppContent,
		NcButton,
		NcDialog,
		NcIconSvgWrapper,
		NcTextField,
		PaginationToolbar,
		NcEmptyContent,
		NcLoadingIcon,
		PillMenu,
		ResultsSummary,
		Submission,
		TopBar,
	},

	props: {
		hash: {
			type: String,
			default: '',
		},

		form: {
			type: Object,
			required: true,
		},

		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:form', 'open-sharing'],

	setup(props, { emit }) {
		const route = useRoute()
		const router = useRouter()
		const viewForm = useViewForm({ form: () => props.form, emit })

		const questions = ref<FormsQuestion[]>([])
		const submissions = ref<SubmissionRecord[]>([])
		const filteredSubmissionsCount = ref(0)
		const isDownloadActionOpened = ref(false)
		const loadingResults = ref(true)
		const skipReloadOnOffsetChange = ref(false)
		const picker = ref<PickerLike | null>(null)
		const showConfirmDeleteDialog = ref(false)
		const submissionSearch = ref('')
		const limit = ref(20)
		const offset = ref(0)
		const isMobile = useIsSmallMobile()

		const linkedFileNotAvailableButtons = computed<DialogButton[]>(() => [
			{
				label: t('forms', 'Unlink spreadsheet'),
				icon: IconLinkVariantOff,
				variant: 'error',
				callback: () => {
					void onUnlinkFile()
				},
			},
			{
				label: t('forms', 'Create spreadsheet'),
				icon: IconLink,
				variant: 'primary',
				callback: () => {
					void onLinkFile()
				},
			},
		])

		const confirmDeleteButtons = computed<DialogButton[]>(() => [
			{
				label: t('forms', 'Cancel'),
				icon: IconCancel,
				variant: 'tertiary',
				callback: () => {
					closeDeleteConfirmation()
				},
			},
			{
				label: t('forms', 'Delete responses'),
				icon: IconDelete,
				variant: 'error',
				callback: () => {
					void deleteAllSubmissionsConfirmed()
				},
			},
		])

		const isSummaryView = computed<boolean>(
			() => route.name === 'results.summary',
		)
		const activeResponseView = computed<ResponseView>(() => {
			return isSummaryView.value ? summaryResponseView : responsesResponseView
		})
		const isFormArchived = computed<boolean>(() => {
			return props.form.state === FormState.FormArchived
		})
		const canExportSubmissions = computed<boolean>(() => {
			return props.form.permissions.includes(
				PERMISSION_TYPES.PERMISSION_RESULTS,
			)
		})
		const canDeleteSubmissions = computed<boolean>(() => {
			return (
				props.form.permissions.includes(
					PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
				) && !isFormArchived.value
			)
		})
		const canEditForm = computed<boolean>(() => {
			return props.form.permissions.includes(PERMISSION_TYPES.PERMISSION_EDIT)
		})
		const noSubmissions = computed<boolean>(() => {
			return props.form.submissionCount === 0
		})
		const noFilteredSubmissions = computed<boolean>(() => {
			return submissions.value.length === 0
		})
		const fileUrl = computed<string>(() => {
			if (props.form.fileId) {
				return generateUrl('/f/{fileId}', { fileId: props.form.fileId })
			}
			return window.location.href
		})
		const showLinkedFileNotAvailableDialog = computed<boolean>(() => {
			if (props.form.partial) {
				return false
			}

			return (
				canEditForm.value
				&& Boolean(props.form.fileId)
				&& !props.form.filePath
				&& !viewForm.isFormLocked.value
			)
		})

		/**
		 * Save the active response view preference to localStorage for the current form.
		 *
		 * @param viewId - The ID of the view ('summary' or 'responses')
		 */
		function saveActiveResponseViewToLocalStorage(
			viewId: ResponseView['id'],
		): void {
			try {
				const storageKey = getActiveResponseViewStorageKey()
				if (!storageKey) {
					return
				}

				localStorage.setItem(storageKey, viewId)
			} catch (err) {
				logger.debug('Error saving activeResponseView to localStorage', {
					error: err,
				})
			}
		}

		/**
		 * Get storage key for active response view
		 */
		function getActiveResponseViewStorageKey(): string | null {
			const formHash = props.form.hash
			if (!formHash) {
				return null
			}

			return `nextcloud_forms_${formHash}_activeResponseView`
		}

		/**
		 * Navigate to an explicit route for the selected response view.
		 *
		 * @param view The selected response view object
		 */
		async function onViewChange(view: ResponseView): Promise<void> {
			if (!view?.id) {
				return
			}
			const targetName = `results.${view.id}`
			if (route.name === targetName) {
				await loadFormResults()
				return
			}

			try {
				await router.push({
					name: targetName,
					params: {
						hash: props.form.hash,
					},
				})
			} catch (error) {
				logger.debug('Navigation cancelled', { error })
			}
		}

		/**
		 * Unlink the results file
		 */
		async function onUnlinkFile(): Promise<void> {
			await axios.patch(
				generateOcsUrl('apps/forms/api/v3/forms/{formId}', {
					formId: props.form.id,
				}),
				{
					keyValuePairs: {
						fileId: null,
						fileFormat: null,
					},
				},
			)

			const updatedForm = {
				...props.form,
				fileFormat: null,
				fileId: null,
				filePath: null,
			}
			emit('update:form', updatedForm)
			emitEvent('forms:last-updated:set', props.form.id)
		}

		/**
		 * Load form results
		 */
		async function loadFormResults(): Promise<void> {
			loadingResults.value = true
			logger.debug(`Loading responses for form ${props.form.hash}`)

			try {
				let response = null
				if (isSummaryView.value) {
					response = await axios.get(
						generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
							id: props.form.id,
						}),
					)
				} else {
					response = await axios.get(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/submissions?limit={limit}&offset={offset}&query={query}',
							{
								id: props.form.id,
								limit: limit.value,
								offset: offset.value,
								query: submissionSearch.value,
							},
						),
					)
				}
				const data = OcsResponse2Data<ResultsResponse>(response)

				submissions.value = formatDateAnswers(
					data.submissions,
					data.questions,
				)
				questions.value = data.questions
				filteredSubmissionsCount.value = data.filteredSubmissionsCount
			} catch (error) {
				logger.error('Error while loading responses', { error })
				showError(t('forms', 'An error occurred while loading responses'))
			} finally {
				loadingResults.value = false
			}
		}

		/**
		 * Download the results file
		 *
		 * @param nextFileFormat the file format
		 */
		async function onDownloadFile(
			nextFileFormat: SupportedFileFormat,
		): Promise<void> {
			const exportUrl =
				generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
					id: props.form.id,
				})
				+ '?requesttoken='
				+ encodeURIComponent(getRequestToken() ?? '')
				+ '&fileFormat='
				+ nextFileFormat

			window.open(exportUrl, '_self')
		}

		/**
		 * Link a file for exporting submissions
		 */
		async function onLinkFile(): Promise<void> {
			try {
				const path = await getPicker().pick()
				try {
					await axios.patch(
						generateOcsUrl('apps/forms/api/v3/forms/{id}', {
							id: props.form.id,
						}),
						{
							keyValuePairs: {
								path,
								fileFormat,
							},
						},
					)
					await viewForm.fetchFullForm(props.form.id)
					await loadFormResults()

					showSuccess(
						t('forms', 'File {file} successfully linked', {
							file: props.form.filePath?.split('/').pop() ?? '',
						}),
					)
					emitEvent('forms:last-updated:set', props.form.id)
				} catch (error) {
					logger.error('Error while exporting to Files and linking', {
						error,
					})
					showError(
						t('forms', 'There was an error while linking the file'),
					)
				}
			} catch (error) {
				logger.debug('No file selected', { error })
			}
		}

		/**
		 * Store results to files
		 */
		async function onStoreToFiles(): Promise<void> {
			try {
				const path = await getPicker().pick()
				try {
					const response = await axios.post(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/submissions/export',
							{
								id: props.form.id,
							},
						),
						{
							path,
							fileFormat,
						},
					)

					showSuccess(
						t('forms', 'Export successful to {file}', {
							file: OcsResponse2Data<string>(response),
						}),
					)
				} catch (error) {
					logger.error('Error while exporting to Files', { error })
					showError(
						t('forms', 'There was an error while exporting to Files'),
					)
				}
			} catch (error) {
				logger.debug('No file selected', { error })
			}
		}

		/**
		 * Re-export the results
		 */
		async function onReExport(): Promise<void> {
			if (!props.form.fileId) {
				showError(t('forms', 'File is not linked'))
				return
			}

			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/submissions/export',
						{
							id: props.form.id,
						},
					),
					{
						path: props.form.filePath,
						fileFormat: props.form.fileFormat,
					},
				)

				showSuccess(
					t('forms', 'Export successful to {file}', {
						file: OcsResponse2Data<string>(response),
					}),
				)
			} catch (error) {
				logger.error('Error while exporting to Files', { error })
				showError(t('forms', 'There was an error, while exporting to Files'))
			}
		}

		/**
		 * If user can delete a submission
		 *
		 * @param submissionUser the user who submitted the response
		 */
		function canDeleteSubmission(submissionUser: string): boolean {
			const currentUser = getCurrentUser()
			return (
				canDeleteSubmissions.value
				|| (props.form.allowEditSubmissions
					&& currentUser?.uid === submissionUser)
			)
		}

		/**
		 * Determines if a submission can be edited.
		 *
		 * @param submissionUser - The ID of the user who created the submission.
		 * @return - Returns true if the submission can be edited, otherwise false.
		 *                      A submission can be edited if:
		 *                      - The user has the `canDeleteSubmissions` permission, or
		 *                      - The form allows editing (`form.allowEditSubmissions`) and the current user is the owner of the submission.
		 */
		function canEditSubmission(submissionUser: string): boolean {
			const currentUser = getCurrentUser()
			return (
				canDeleteSubmissions.value
				|| (props.form.allowEditSubmissions
					&& currentUser?.uid === submissionUser)
			)
		}

		/**
		 * Delete the submission
		 *
		 * @param id the id of the submission
		 */
		async function deleteSubmission(id: number): Promise<void> {
			loadingResults.value = true

			try {
				await axios.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/submissions/{submissionId}',
						{
							id: props.form.id,
							submissionId: id,
						},
					),
				)

				showSuccess(t('forms', 'Response deleted'))
				const index = submissions.value.findIndex(
					(search: SubmissionRecord) => search.id === id,
				)
				if (index >= 0) {
					submissions.value.splice(index, 1)
				}
				emitEvent('forms:last-updated:set', props.form.id)
			} catch (error) {
				logger.error(`Error while deleting response ${id}`, { error })
				showError(
					t('forms', 'An error occurred while deleting this response'),
				)
			} finally {
				loadingResults.value = false
			}
		}

		/**
		 * Show confirmation dialog for deletion
		 */
		function deleteAllSubmissions(): void {
			showConfirmDeleteDialog.value = true
		}

		/**
		 * Close the confirmation dialog for deletion
		 */
		function closeDeleteConfirmation(): void {
			showConfirmDeleteDialog.value = false
		}

		/**
		 * Deletion confirmed, delete all submissions
		 */
		async function deleteAllSubmissionsConfirmed(): Promise<void> {
			showConfirmDeleteDialog.value = false
			loadingResults.value = true

			try {
				await axios.delete(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
						id: props.form.id,
					}),
				)

				submissions.value = []
				const updatedForm = { ...props.form, submissionCount: 0 }
				emit('update:form', updatedForm)
				emitEvent('forms:last-updated:set', props.form.id)
			} catch (error) {
				logger.error('Error while deleting responses', { error })
				showError(t('forms', 'An error occurred while deleting responses'))
			} finally {
				loadingResults.value = false
			}
		}

		/**
		 * Format date answers
		 *
		 * @param submissions array of responses
		 * @param questions array of questions
		 */
		function formatDateAnswers(
			submissions: SubmissionRecord[],
			questions: FormsQuestion[],
		): SubmissionRecord[] {
			const dateQuestions = Object.fromEntries(
				questions
					.filter(
						(question) =>
							question.type === 'date'
							|| question.type === 'datetime'
							|| question.type === 'time',
					)
					.map((question) => [question.id, question.type]),
			) as Record<number, keyof typeof answerTypes>

			submissions.forEach((submission) => {
				submission.answers
					.filter((answer) => answer.questionId in dateQuestions)
					.forEach((answer) => {
						const answerType =
							answerTypes[dateQuestions[answer.questionId]]
						const date = moment(answer.text, answerType.storageFormat)

						if (date.isValid()) {
							answer.text = date.format(answerType.momentFormat)
						}
					})
			})

			return submissions
		}

		/**
		 * Get a file picker
		 */
		function getPicker(): PickerLike {
			if (picker.value !== null) {
				return picker.value
			}

			picker.value = getFilePickerBuilder(
				t('forms', 'Choose spreadsheet location'),
			)
				.setMultiSelect(false)
				.allowDirectories(true)
				.setCanPick((node: INode) => {
					if (node.type === FileType.Folder) {
						return true
					}

					const extension = node.extension?.slice(1).toLowerCase()
					if (!extension) {
						return false
					}

					return extension in SUPPORTED_FILE_FORMATS
				})
				.setButtonFactory((selectedNodes: INode[]) => {
					const [node] = selectedNodes
					if (node && node.type === FileType.File) {
						const extension = node.extension?.slice(1).toLowerCase() as
							SupportedFileFormat | undefined

						return [
							{
								label: t('forms', 'Select {file}', {
									file: selectedNodes[0].basename,
								}),
								icon: extension
									? SUPPORTED_FILE_FORMATS[extension]
									: IconTable,
								callback() {
									if (extension) {
										fileFormat = extension
									}
								},
								variant: 'primary',
							},
						]
					}

					return [
						{
							label: t('forms', 'Create XLSX'),
							icon: IconFileExcelOutline,
							callback() {
								fileFormat = 'xlsx'
							},
							variant: 'secondary',
						},
						{
							label: t('forms', 'Create CSV'),
							icon: IconFileDelimited,
							callback() {
								fileFormat = 'csv'
							},
							variant: 'secondary',
						},
						{
							label: t('forms', 'Create ODS'),
							icon: IconTable,
							callback() {
								fileFormat = 'ods'
							},
							variant: 'primary',
						},
					]
				})
				.build() as PickerLike

			return picker.value
		}

		watch(
			() => props.hash,
			async () => {
				await viewForm.fetchFullForm(props.form.id)
				await loadFormResults()
				SetWindowTitle(viewForm.formTitle.value)
			},
		)

		watch(
			() => route.name,
			() => {
				const viewId = isSummaryView.value ? 'summary' : 'responses'
				saveActiveResponseViewToLocalStorage(viewId)
				void loadFormResults()
			},
		)

		watch(limit, () => {
			void loadFormResults()
		})

		watch(offset, () => {
			if (!skipReloadOnOffsetChange.value) {
				void loadFormResults()
			}
		})

		watch(
			submissionSearch,
			debounce(() => {
				skipReloadOnOffsetChange.value = true
				offset.value = 0
				nextTick(() => {
					skipReloadOnOffsetChange.value = false
				})
				void loadFormResults()
			}, INPUT_DEBOUNCE_MS),
		)

		onBeforeMount(async (): Promise<void> => {
			// Determine the initial viewId based on the route
			const viewId = isSummaryView.value ? 'summary' : 'responses'
			saveActiveResponseViewToLocalStorage(viewId)

			await viewForm.fetchFullForm(props.form.id)
			await loadFormResults()
			SetWindowTitle(viewForm.formTitle.value)
		})

		return {
			...viewForm,
			isMobile,
			t,
			responseViews,
			questions,
			submissions,
			filteredSubmissionsCount,
			isDownloadActionOpened,
			loadingResults,
			skipReloadOnOffsetChange,
			showConfirmDeleteDialog,
			submissionSearch,
			limit,
			offset,
			linkedFileNotAvailableButtons,
			confirmDeleteButtons,
			isSummaryView,
			activeResponseView,
			isFormArchived,
			canExportSubmissions,
			canDeleteSubmissions,
			canEditForm,
			noSubmissions,
			noFilteredSubmissions,
			fileUrl,
			showLinkedFileNotAvailableDialog,
			saveActiveResponseViewToLocalStorage,
			getActiveResponseViewStorageKey,
			onViewChange,
			onUnlinkFile,
			loadFormResults,
			onDownloadFile,
			onLinkFile,
			onStoreToFiles,
			onReExport,
			canDeleteSubmission,
			canEditSubmission,
			deleteSubmission,
			deleteAllSubmissions,
			closeDeleteConfirmation,
			deleteAllSubmissionsConfirmed,
			formatDateAnswers,
			getPicker,
			IconChevronLeft,
			IconDelete,
			IconDownload,
			IconFileDelimited,
			IconFileExcelOutline,
			IconFolder,
			IconLink,
			IconLinkVariantOff,
			IconPoll,
			IconRefresh,
			IconShareVariant,
			IconTable,
			IconMagnify,
		}
	},
})
</script>

<style lang="scss" scoped>
.forms-emptycontent {
	height: 100%;
}

.app-content {
	display: flex;
	align-items: center;
	flex-direction: column;

	header,
	section {
		width: 100%;
		max-width: 750px;
	}

	// Title & description header
	header {
		display: flex;
		flex-direction: column;
		margin-block-end: 24px;
		margin-inline-start: 40px;

		h2 {
			margin-block-end: 0; // because the input field has enough padding
			font-size: 28px;
			font-weight: bold;
			margin-block-start: 32px;
			padding-inline-start: 14px;
			padding-block-end: 8px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		p {
			padding-inline-start: 14px;
		}
	}

	.response-actions {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		margin-block-start: 8px;
		margin-inline-start: 8px;
		padding-inline-start: calc(14px - var(--border-radius-pill));

		&__toggle {
			margin-inline-end: 1em;
		}
	}
}

.search-wrapper {
	margin-block-start: calc(-1 * var(--default-grid-baseline));
	margin-inline-start: auto;
	margin-inline-end: var(--default-clickable-area);
}

.bottom-pagination {
	margin-bottom: 24px;
}
</style>
