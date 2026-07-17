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
					v-model:active="activeResponseView"
					:disabled="noSubmissions"
					:options="responseViews"
					:groupLabel="t('forms', 'View mode')"
					class="response-actions__toggle"
					@update:active="onChangeResponseView" />

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
						&& activeResponseView.id !== 'summary'
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
		<section v-else-if="activeResponseView.id === 'summary'">
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
import { emit } from '@nextcloud/event-bus'
import { FileType } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { useIsSmallMobile } from '@nextcloud/vue'
import debounce from 'debounce'
import { defineComponent } from 'vue'
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
import PermissionTypes from '../mixins/PermissionTypes.ts'
import ViewsMixin from '../mixins/ViewsMixin.ts'
import answerTypes from '../models/AnswerTypes.ts'
import { FormState, INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import SetWindowTitle from '../utils/SetWindowTitle.ts'

type SupportedFileFormat = 'ods' | 'csv' | 'xlsx'

interface ResponseView {
	title: string
	id: 'summary' | 'responses'
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

interface ResultsViewData {
	activeResponseView: ResponseView
	questions: FormsQuestion[]
	submissions: SubmissionRecord[]
	filteredSubmissionsCount: number
	isDownloadActionOpened: boolean
	loadingResults: boolean
	skipReloadOnOffsetChange: boolean
	picker: PickerLike | null
	showConfirmDeleteDialog: boolean
	submissionSearch: string
	limit: number
	offset: number
	linkedFileNotAvailableButtons: DialogButton[]
	confirmDeleteButtons: DialogButton[]
}

const SUPPORTED_FILE_FORMATS: Record<SupportedFileFormat, string> = {
	ods: IconTable,
	csv: IconFileDelimited,
	xlsx: IconFileExcelOutline,
}

let fileFormat: SupportedFileFormat = 'csv'

const responseViews: ResponseView[] = [
	{
		title: t('forms', 'Summary'),
		id: 'summary',
	},
	{
		title: t('forms', 'Responses'),
		id: 'responses',
	},
]

const responseViewIds = new Set(responseViews.map((view) => view.id))

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

	mixins: [PermissionTypes, ViewsMixin],
	emits: ['update:form'],

	setup() {
		return {
			isMobile: useIsSmallMobile(),
			t,
			responseViews,
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

	data(): ResultsViewData {
		return {
			activeResponseView: null,

			questions: [],
			submissions: [],
			filteredSubmissionsCount: 0,
			isDownloadActionOpened: false,
			loadingResults: true,
			skipReloadOnOffsetChange: false,
			picker: null,
			showConfirmDeleteDialog: false,
			submissionSearch: '',
			limit: 20,
			offset: 0,
			linkedFileNotAvailableButtons: [
				{
					label: t('forms', 'Unlink spreadsheet'),
					icon: IconLinkVariantOff,
					variant: 'error',
					callback: () => {
						this.onUnlinkFile()
					},
				},
				{
					label: t('forms', 'Create spreadsheet'),
					icon: IconLink,
					variant: 'primary',
					callback: () => {
						this.onLinkFile()
					},
				},
			],

			confirmDeleteButtons: [
				{
					label: t('forms', 'Cancel'),
					icon: IconCancel,
					variant: 'tertiary',
					callback: () => {
						this.closeDeleteConfirmation()
					},
				},
				{
					label: t('forms', 'Delete responses'),
					icon: IconDelete,
					variant: 'error',
					callback: () => {
						this.deleteAllSubmissionsConfirmed()
					},
				},
			],
		}
	},

	computed: {
		isFormArchived(): boolean {
			return this.form.state === FormState.FormArchived
		},

		canExportSubmissions(): boolean {
			return this.form.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS,
			)
		},

		canDeleteSubmissions(): boolean {
			return (
				this.form.permissions.includes(
					this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
				) && !this.isFormArchived
			)
		},

		canEditForm(): boolean {
			return this.form.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
			)
		},

		noSubmissions(): boolean {
			return this.form.submissionCount === 0
		},

		noFilteredSubmissions(): boolean {
			return this.submissions.length === 0
		},

		fileUrl(): string {
			if (this.form.fileId) {
				return generateUrl('/f/{fileId}', { fileId: this.form.fileId })
			}
			return window.location.href
		},

		showLinkedFileNotAvailableDialog(): boolean {
			if (this.form.partial) {
				return false
			}

			return (
				this.canEditForm
				&& Boolean(this.form.fileId)
				&& !this.form.filePath
				&& !this.isFormLocked
			)
		},
	},

	watch: {
		async hash(): Promise<void> {
			await this.fetchFullForm(this.form.id)
			await this.syncActiveResponseViewFromRoute()
			SetWindowTitle(this.formTitle)
		},

		'$route.query': {
			handler(): void {
				this.syncActiveResponseViewFromRoute()
			},
		},

		limit(): void {
			this.loadFormResults()
		},

		offset(): void {
			if (!this.skipReloadOnOffsetChange) {
				this.loadFormResults()
			}
		},

		submissionSearch: debounce(function (
			this: ResultsViewData & {
				$nextTick: (callback: () => void) => void
				loadFormResults: () => void
			},
		) {
			this.skipReloadOnOffsetChange = true
			this.offset = 0
			this.$nextTick(() => {
				this.skipReloadOnOffsetChange = false
			})
			this.loadFormResults()
		}, INPUT_DEBOUNCE_MS),

		activeResponseView(newView: ResponseView): void {
			if (newView?.id) {
				this.saveActiveResponseViewToLocalStorage(newView.id)
			}
		},
	},

	async beforeMount(): Promise<void> {
		await this.fetchFullForm(this.form.id)
		await this.syncActiveResponseViewFromRoute()
		SetWindowTitle(this.formTitle)
	},

	methods: {
		getResponseViewById(viewId: string): ResponseView {
			return (
				responseViews.find((view) => view.id === viewId) ?? responseViews[0]
			)
		},

		getRouteResponseViewId(): ResponseView['id'] | null {
			return this.$route.query.view ?? null
		},

		loadStoredActiveResponseViewId(): ResponseView['id'] {
			try {
				const storageKey = this.getActiveResponseViewStorageKey()
				if (!storageKey) {
					return responseViews[0].id
				}

				const storedViewId = localStorage.getItem(storageKey)
				if (
					storedViewId
					&& responseViewIds.has(storedViewId as ResponseView['id'])
				) {
					return storedViewId as ResponseView['id']
				}

				return responseViews[0].id
			} catch (err) {
				logger.debug('Error loading activeResponseView from localStorage', {
					error: err,
				})
				return responseViews[0].id
			}
		},

		resolveActiveResponseViewId(): ResponseView['id'] {
			return (
				this.getRouteResponseViewId()
				?? this.loadStoredActiveResponseViewId()
			)
		},

		async syncActiveResponseViewFromRoute(): Promise<void> {
			const routeViewId = this.getRouteResponseViewId()
			const nextView = this.getResponseViewById(
				routeViewId ?? this.loadStoredActiveResponseViewId(),
			)
			const currentViewId = this.activeResponseView?.id

			if (currentViewId !== nextView.id) {
				this.activeResponseView = nextView
			}

			if (!routeViewId) {
				try {
					await this.$router.replace({
						name: 'results',
						params: {
							hash: this.form.hash,
						},
						query: {
							...this.$route.query,
							view: nextView.id,
						},
					})
					return
				} catch (error) {
					logger.debug('Navigation cancelled', { error })
				}
			}

			this.loadFormResults()
		},

		saveActiveResponseViewToLocalStorage(viewId: ResponseView['id']): void {
			try {
				const storageKey = this.getActiveResponseViewStorageKey()
				if (!storageKey) {
					return
				}

				localStorage.setItem(storageKey, viewId)
			} catch (err) {
				logger.debug('Error saving activeResponseView to localStorage', {
					error: err,
				})
			}
		},

		getActiveResponseViewStorageKey(): string | null {
			const formHash = this.form.hash
			if (!formHash) {
				return null
			}

			return `nextcloud_forms_${formHash}_activeResponseView`
		},

		async onChangeResponseView(view: ResponseView): Promise<void> {
			if (this.getRouteResponseViewId() === view.id) {
				this.loadFormResults()
				return
			}

			try {
				await this.$router.push({
					name: 'results',
					params: {
						hash: this.form.hash,
					},
					query: {
						view: view.id,
					},
				})
			} catch (error) {
				logger.debug('Navigation cancelled', { error })
			}
		},

		async onUnlinkFile(): Promise<void> {
			await axios.patch(
				generateOcsUrl('apps/forms/api/v3/forms/{formId}', {
					formId: this.form.id,
				}),
				{
					keyValuePairs: {
						fileId: null,
						fileFormat: null,
					},
				},
			)

			const updatedForm = {
				...this.form,
				fileFormat: null,
				fileId: null,
				filePath: null,
			}
			this.$emit('update:form', updatedForm)
			emit('forms:last-updated:set', this.form.id)
		},

		async loadFormResults(): Promise<void> {
			this.loadingResults = true
			logger.debug(`Loading responses for form ${this.form.hash}`)

			try {
				const response =
					this.activeResponseView.id === 'summary'
						? await axios.get(
								generateOcsUrl(
									'apps/forms/api/v3/forms/{id}/submissions',
									{
										id: this.form.id,
									},
								),
							)
						: await axios.get(
								generateOcsUrl(
									'apps/forms/api/v3/forms/{id}/submissions?limit={limit}&offset={offset}&query={query}',
									{
										id: this.form.id,
										limit: this.limit,
										offset: this.offset,
										query: this.submissionSearch,
									},
								),
							)

				const data = OcsResponse2Data<ResultsResponse>(response)

				this.submissions = this.formatDateAnswers(
					data.submissions,
					data.questions,
				)
				this.questions = data.questions
				this.filteredSubmissionsCount = data.filteredSubmissionsCount
			} catch (error) {
				logger.error('Error while loading responses', { error })
				showError(t('forms', 'An error occurred while loading responses'))
			} finally {
				this.loadingResults = false
			}
		},

		async onDownloadFile(nextFileFormat: SupportedFileFormat): Promise<void> {
			const exportUrl =
				generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
					id: this.form.id,
				})
				+ '?requesttoken='
				+ encodeURIComponent(getRequestToken() ?? '')
				+ '&fileFormat='
				+ nextFileFormat

			window.open(exportUrl, '_self')
		},

		async onLinkFile(): Promise<void> {
			try {
				const path = await this.getPicker().pick()
				try {
					await axios.patch(
						generateOcsUrl('apps/forms/api/v3/forms/{id}', {
							id: this.form.id,
						}),
						{
							keyValuePairs: {
								path,
								fileFormat,
							},
						},
					)
					await this.fetchFullForm(this.form.id)
					await this.loadFormResults()

					showSuccess(
						t('forms', 'File {file} successfully linked', {
							file: this.form.filePath?.split('/').pop() ?? '',
						}),
					)
					emit('forms:last-updated:set', this.form.id)
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
		},

		async onStoreToFiles(): Promise<void> {
			try {
				const path = await this.getPicker().pick()
				try {
					const response = await axios.post(
						generateOcsUrl(
							'apps/forms/api/v3/forms/{id}/submissions/export',
							{
								id: this.form.id,
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
		},

		async onReExport(): Promise<void> {
			if (!this.form.fileId) {
				showError(t('forms', 'File is not linked'))
				return
			}

			try {
				const response = await axios.post(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/submissions/export',
						{
							id: this.form.id,
						},
					),
					{
						path: this.form.filePath,
						fileFormat: this.form.fileFormat,
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
		},

		canDeleteSubmission(submissionUser: string): boolean {
			const currentUser = getCurrentUser()
			return (
				this.canDeleteSubmissions
				|| (this.form.allowEditSubmissions
					&& currentUser?.uid === submissionUser)
			)
		},

		/**
		 * Determines if a submission can be edited.
		 *
		 * @param submissionUser - The ID of the user who created the submission.
		 * @return - Returns true if the submission can be edited, otherwise false.
		 *                      A submission can be edited if:
		 *                      - The user has the `canDeleteSubmissions` permission, or
		 *                      - The form allows editing (`form.allowEditSubmissions`) and the current user is the owner of the submission.
		 */
		canEditSubmission(submissionUser: string): boolean {
			const currentUser = getCurrentUser()
			return (
				this.canDeleteSubmissions
				|| (this.form.allowEditSubmissions
					&& currentUser?.uid === submissionUser)
			)
		},

		async deleteSubmission(id: number): Promise<void> {
			this.loadingResults = true

			try {
				await axios.delete(
					generateOcsUrl(
						'apps/forms/api/v3/forms/{id}/submissions/{submissionId}',
						{
							id: this.form.id,
							submissionId: id,
						},
					),
				)

				showSuccess(t('forms', 'Response deleted'))
				const index = this.submissions.findIndex(
					(search: SubmissionRecord) => search.id === id,
				)
				this.submissions.splice(index, 1)
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error(`Error while deleting response ${id}`, { error })
				showError(
					t('forms', 'An error occurred while deleting this response'),
				)
			} finally {
				this.loadingResults = false
			}
		},

		deleteAllSubmissions(): void {
			this.showConfirmDeleteDialog = true
		},

		closeDeleteConfirmation(): void {
			this.showConfirmDeleteDialog = false
		},

		async deleteAllSubmissionsConfirmed(): Promise<void> {
			this.showConfirmDeleteDialog = false
			this.loadingResults = true

			try {
				await axios.delete(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
						id: this.form.id,
					}),
				)

				this.submissions = []
				this.form.submissionCount = 0
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error('Error while deleting responses', { error })
				showError(t('forms', 'An error occurred while deleting responses'))
			} finally {
				this.loadingResults = false
			}
		},

		formatDateAnswers(
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
		},

		getPicker(): PickerLike {
			if (this.picker !== null) {
				return this.picker
			}

			this.picker = getFilePickerBuilder(
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

			return this.picker
		},
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
