<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent :page-heading="t('forms', 'Results')">
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
			no-close />

		<TopBar
			:archived="isFormArchived"
			:locked="isFormLocked"
			:permissions="form?.permissions"
			:sidebar-opened="sidebarOpened"
			:submission-count="form?.submissionCount"
			@share-form="onShareForm" />

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
					class="response-actions__toggle"
					@update:active="loadFormResults" />

				<!-- Action menu for cloud export and deletion -->
				<NcActions
					v-if="canExportSubmissions"
					:aria-label="t('forms', 'Options')"
					force-name
					:inline="isMobile ? 0 : 1"
					@blur="isDownloadActionOpened = false"
					@close="isDownloadActionOpened = false">
					<template v-if="!isDownloadActionOpened">
						<NcActionButton
							v-if="canEditForm && !form.fileId && !isFormLocked"
							@click="onLinkFile">
							<template #icon>
								<IconLink :size="20" />
							</template>
							{{ t('forms', 'Create spreadsheet') }}
						</NcActionButton>
						<template v-if="canEditForm && form.fileId">
							<NcActionButton
								:href="fileUrl"
								type="tertiary-no-background">
								<template #icon>
									<IconTable :size="20" />
								</template>
								{{ t('forms', 'Open spreadsheet') }}
							</NcActionButton>
							<NcActionButton close-after-click @click="onReExport">
								<template #icon>
									<IconRefresh :size="20" />
								</template>
								{{ t('forms', 'Re-export spreadsheet') }}
							</NcActionButton>
							<NcActionButton
								close-after-click
								:disabled="isFormLocked"
								@click="onUnlinkFile">
								<template #icon>
									<IconLinkVariantOff :size="20" />
								</template>
								{{ t('forms', 'Unlink spreadsheet') }}
							</NcActionButton>
							<NcActionSeparator v-if="!noSubmissions" />
						</template>
						<NcActionButton
							v-if="!noSubmissions"
							close-after-click
							@click="onStoreToFiles">
							<template #icon>
								<IconFolder :size="20" />
							</template>
							{{ t('forms', 'Save copy to Files') }}
						</NcActionButton>
						<NcActionButton
							v-if="!noSubmissions"
							:close-after-click="false"
							is-menu
							@click="isDownloadActionOpened = true">
							<template #icon>
								<IconDownload :size="20" />
							</template>
							{{ t('forms', 'Download') }}
						</NcActionButton>
						<NcActionButton
							v-if="canDeleteSubmissions && !noSubmissions"
							close-after-click
							@click="deleteAllSubmissions">
							<template #icon>
								<IconDelete :size="20" />
							</template>
							{{ t('forms', 'Delete all responses') }}
						</NcActionButton>
					</template>

					<template v-else>
						<!-- Back to top-level button -->
						<NcActionButton @click="isDownloadActionOpened = false">
							<template #icon>
								<IconChevronLeft :size="20" />
							</template>
							{{ t('forms', 'Download') }}
						</NcActionButton>
						<NcActionSeparator />
						<NcActionButton
							close-after-click
							@click="onDownloadFile('csv')">
							<template #icon>
								<IconFileDelimited :size="20" />
							</template>
							CSV
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="onDownloadFile('ods')">
							<template #icon>
								<IconTable :size="20" />
							</template>
							ODS
						</NcActionButton>
						<NcActionButton
							close-after-click
							@click="onDownloadFile('xlsx')">
							<template #icon>
								<IconFileExcelOutline :size="20" />
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
						trailing-button-icon="close"
						:show-trailing-button="submissionSearch.length > 0"
						@trailing-button-click="submissionSearch = ''">
						<template #icon>
							<IconMagnify :size="20" />
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
			:name="t('forms', 'No results found')"
			class="forms-emptycontent"
			:description="
				t('forms', 'No results found for {submissionSearch}', {
					submissionSearch,
				})
			">
			<template #icon>
				<IconPoll :size="64" />
			</template>
		</NcEmptyContent>

		<!-- No submissions -->
		<NcEmptyContent
			v-else-if="noSubmissions"
			:name="t('forms', 'No responses yet')"
			class="forms-emptycontent"
			:description="
				t('forms', 'Results of submitted forms will show up here')
			">
			<template #icon>
				<IconPoll :size="64" />
			</template>
			<template #action>
				<div class="response-actions">
					<NcButton variant="primary" @click="onShareForm">
						<template #icon>
							<IconShareVariant :size="20" decorative />
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
				:form-hash="form.hash"
				:submission="submission"
				:questions="questions"
				:highlight="submissionSearch"
				:can-delete-submission="canDeleteSubmission(submission.userId)"
				:can-edit-submission="canEditSubmission(submission.userId)"
				@delete="deleteSubmission(submission.id)" />

			<PaginationToolbar
				v-model:limit="limit"
				v-model:offset="offset"
				class="bottom-pagination"
				:total-items-count="filteredSubmissionsCount" />
		</section>

		<!-- Confirmation dialog for deleting all submissions -->
		<NcDialog
			v-model:open="showConfirmDeleteDialog"
			:name="t('forms', 'Delete submissions')"
			:message="
				t(
					'forms',
					'Are you sure you want to delete all responses of {title}?',
					{ title: formTitle },
				)
			"
			:buttons="confirmDeleteButtons" />
	</NcAppContent>
</template>

<script>
import IconCancelSvg from '@mdi/svg/svg/cancel.svg?raw'
import IconDeleteSvg from '@mdi/svg/svg/delete.svg?raw'
import IconFileDelimitedSvg from '@mdi/svg/svg/file-delimited-outline.svg?raw'
import IconFileExcelOutlineSvg from '@mdi/svg/svg/file-excel-outline.svg?raw'
import IconLinkVariantOffSvg from '@mdi/svg/svg/link-off.svg?raw'
import IconLinkSvg from '@mdi/svg/svg/link.svg?raw'
import IconTableSvg from '@mdi/svg/svg/table.svg?raw'
import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { useIsSmallMobile } from '@nextcloud/vue'
import debounce from 'debounce'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconFileDelimited from 'vue-material-design-icons/FileDelimitedOutline.vue'
import IconFileExcelOutline from 'vue-material-design-icons/FileExcelOutline.vue'
import IconFolder from 'vue-material-design-icons/FolderOutline.vue'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconLinkVariantOff from 'vue-material-design-icons/LinkOff.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariantOutline.vue'
import IconTable from 'vue-material-design-icons/Table.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'
import IconDownload from 'vue-material-design-icons/TrayArrowDown.vue'
import PaginationToolbar from '../components/PaginationToolbar.vue'
import PillMenu from '../components/PillMenu.vue'
import ResultsSummary from '../components/Results/ResultsSummary.vue'
import Submission from '../components/Results/Submission.vue'
import TopBar from '../components/TopBar.vue'
import PermissionTypes from '../mixins/PermissionTypes.js'
import ViewsMixin from '../mixins/ViewsMixin.js'
import answerTypes from '../models/AnswerTypes.js'
import { FormState, INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
import SetWindowTitle from '../utils/SetWindowTitle.js'

const SUPPORTED_FILE_FORMATS = {
	ods: IconTableSvg,
	csv: IconFileDelimitedSvg,
	xlsx: IconFileExcelOutlineSvg,
}
let fileFormat = 'csv'

const responseViews = [
	{
		title: t('forms', 'Summary'),
		id: 'summary',
	},
	{
		title: t('forms', 'Responses'),
		id: 'responses',
	},
]

export default {
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Results',

	components: {
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
		NcActionButton,
		NcActionSeparator,
		NcActions,
		NcAppContent,
		NcButton,
		NcDialog,
		NcTextField,
		PaginationToolbar,
		IconMagnify,
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

			// non reactive props
			responseViews,
		}
	},

	data() {
		return {
			activeResponseView: responseViews[0],

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
					icon: IconLinkVariantOffSvg,
					type: 'error',
					callback: () => {
						this.onUnlinkFile()
					},
				},
				{
					label: t('forms', 'Create spreadsheet'),
					icon: IconLinkSvg,
					type: 'primary',
					callback: () => {
						this.onLinkFile()
					},
				},
			],

			confirmDeleteButtons: [
				{
					label: t('forms', 'Cancel'),
					icon: IconCancelSvg,
					type: 'tertiary',
					callback: () => {
						this.showConfirmDeleteDialog = false
					},
				},
				{
					label: t('forms', 'Delete submissions'),
					icon: IconDeleteSvg,
					type: 'error',
					callback: () => {
						this.deleteAllSubmissionsConfirmed()
					},
				},
			],
		}
	},

	computed: {
		isFormArchived() {
			return this.form.state === FormState.FormArchived
		},

		canExportSubmissions() {
			return this.form.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS,
			)
		},

		canDeleteSubmissions() {
			return (
				this.form.permissions.includes(
					this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
				) && !this.isFormArchived
			)
		},

		canEditForm() {
			return this.form.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
			)
		},

		noSubmissions() {
			return this.form?.submissionCount === 0
		},

		noFilteredSubmissions() {
			return this.submissions.length === 0
		},

		/**
		 * Generate link to linked file
		 *
		 * @return {string}
		 */
		fileUrl() {
			if (this.form.fileId) {
				return generateUrl('/f/{fileId}', { fileId: this.form.fileId })
			}
			return window.location.href
		},

		showLinkedFileNotAvailableDialog() {
			if (this.form.partial) {
				return false
			}
			return (
				this.canEditForm
				&& this.form.fileId
				&& !this.form.filePath
				&& !this.isFormLocked
			)
		},
	},

	watch: {
		// Reload results when form changes
		async hash() {
			await this.fetchFullForm(this.form.id)
			this.loadFormResults()
			SetWindowTitle(this.formTitle)
		},

		limit() {
			this.loadFormResults()
		},

		offset() {
			// Only load results if we're not changing offset from submissionSearch watch
			if (!this.skipReloadOnOffsetChange) {
				this.loadFormResults()
			}
		},

		submissionSearch: debounce(function () {
			this.skipReloadOnOffsetChange = true
			this.offset = 0
			this.$nextTick(() => {
				this.skipReloadOnOffsetChange = false
			})
			this.loadFormResults()
		}, INPUT_DEBOUNCE_MS),
	},

	async beforeMount() {
		await this.fetchFullForm(this.form.id)
		this.loadFormResults()
		SetWindowTitle(this.formTitle)
	},

	methods: {
		async onUnlinkFile() {
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

		async loadFormResults() {
			this.loadingResults = true
			logger.debug(`Loading results for form ${this.form.hash}`)

			try {
				let response = null
				if (this.activeResponseView.id === 'summary') {
					response = await axios.get(
						generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
							id: this.form.id,
						}),
					)
				} else {
					response = await axios.get(
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
				}
				const data = OcsResponse2Data(response)

				// Append questions & submissions
				this.submissions = this.formatDateAnswers(
					data.submissions,
					data.questions,
				)
				this.questions = data.questions
				this.filteredSubmissionsCount = data.filteredSubmissionsCount
			} catch (error) {
				logger.error('Error while loading results', { error })
				showError(t('forms', 'There was an error while loading the results'))
			} finally {
				this.loadingResults = false
			}
		},

		async onDownloadFile(fileFormat) {
			const exportUrl =
				generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
					id: this.form.id,
				})
				+ '?requesttoken='
				+ encodeURIComponent(getRequestToken())
				+ '&fileFormat='
				+ fileFormat
			window.open(exportUrl, '_self')
		},

		async onLinkFile() {
			try {
				await this.getPicker()
					.pick()
					.then(async (path) => {
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
									file: this.form.filePath.split('/').pop(),
								}),
							)
							emit('forms:last-updated:set', this.form.id)
						} catch (error) {
							logger.error(
								'Error while exporting to Files and linking',
								{ error },
							)
							showError(
								t(
									'forms',
									'There was an error while linking the file',
								),
							)
						}
					})
			} catch (error) {
				// User aborted
				logger.debug('No file selected', { error })
			}
		},

		// Show Filepicker, then call API to store
		async onStoreToFiles() {
			try {
				await this.getPicker()
					.pick()
					.then(async (path) => {
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
									file: OcsResponse2Data(response),
								}),
							)
						} catch (error) {
							logger.error('Error while exporting to Files', {
								error,
							})
							showError(
								t(
									'forms',
									'There was an error while exporting to Files',
								),
							)
						}
					})
			} catch (error) {
				// User aborted
				logger.debug('No file selected', { error })
			}
		},

		async onReExport() {
			if (!this.form.fileId) {
				// Theoretically this will never fire
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
						file: OcsResponse2Data(response),
					}),
				)
			} catch (error) {
				logger.error('Error while exporting to Files', { error })
				showError(t('forms', 'There was an error, while exporting to Files'))
			}
		},

		/**
		 * Determines if a submission can be deleted.
		 *
		 * @param {string} submissionUser - The ID of the user who created the submission.
		 * @return {boolean} - Returns true if the submission can be deleted, otherwise false.
		 *                      A submission can be deleted if:
		 *                      - The user has the `canDeleteSubmissions` permission, or
		 *                      - The form allows editing (`form.allowEditSubmissions`) and the current user is the owner of the submission.
		 */
		canDeleteSubmission(submissionUser) {
			return (
				this.canDeleteSubmissions
				|| (this.form.allowEditSubmissions
					&& getCurrentUser().uid === submissionUser)
			)
		},

		/**
		 * Determines if a submission can be edited.
		 *
		 * @param {string} submissionUser - The ID of the user who created the submission.
		 * @return {boolean} - Returns true if the submission can be edited, otherwise false.
		 */
		canEditSubmission(submissionUser) {
			return (
				this.form.allowEditSubmissions
				&& getCurrentUser().uid === submissionUser
			)
		},

		async deleteSubmission(id) {
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
				showSuccess(t('forms', 'Submission deleted'))
				const index = this.submissions.findIndex(
					(search) => search.id === id,
				)
				this.submissions.splice(index, 1)
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error(`Error while removing response ${id}`, { error })
				showError(
					t('forms', 'There was an error while removing this response'),
				)
			} finally {
				this.loadingResults = false
			}
		},

		deleteAllSubmissions() {
			this.showConfirmDeleteDialog = true
		},

		async deleteAllSubmissionsConfirmed() {
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
				logger.error('Error while removing responses', { error })
				showError(t('forms', 'There was an error while removing responses'))
			} finally {
				this.loadingResults = false
			}
		},

		formatDateAnswers(submissions, questions) {
			// Filter questions that are date/datetime/time
			const dateQuestions = Object.fromEntries(
				questions
					.filter(
						(question) =>
							(question.type === 'date')
							| (question.type === 'datetime')
							| (question.type === 'time'),
					)
					.map((question) => [question.id, question.type]),
			)

			// Go through submissions and reformat answers to date/time questions
			submissions.forEach((submission) => {
				submission.answers
					.filter((answer) => answer.questionId in dateQuestions)
					.forEach((answer) => {
						const date = moment(
							answer.text,
							answerTypes[dateQuestions[answer.questionId]]
								.storageFormat,
						)
						if (date.isValid()) {
							answer.text = date.format(
								answerTypes[dateQuestions[answer.questionId]]
									.momentFormat,
							)
						}
					})
			})

			return submissions
		},

		getPicker() {
			if (this.picker !== null) {
				return this.picker
			}

			this.picker = getFilePickerBuilder(
				t('forms', 'Choose spreadsheet location'),
			)
				.setMultiSelect(false)
				.allowDirectories(true)
				.setButtonFactory((selectedNodes) => {
					if (selectedNodes.length === 1) {
						const extension = selectedNodes[0].extension
							?.slice(1)
							?.toLowerCase()
						if (!extension) {
							return [
								{
									label: t('forms', 'Create XLSX'),
									icon: IconFileExcelOutlineSvg,
									callback() {
										fileFormat = 'xlsx'
									},
									type: 'secondary',
								},
								{
									label: t('forms', 'Create CSV'),
									icon: IconFileDelimitedSvg,
									callback() {
										fileFormat = 'csv'
									},
									type: 'secondary',
								},
								{
									label: t('forms', 'Create ODS'),
									icon: IconTableSvg,
									callback() {
										fileFormat = 'ods'
									},
									type: 'primary',
								},
							]
						} else if (SUPPORTED_FILE_FORMATS[extension]) {
							return [
								{
									label: t('forms', 'Select {file}', {
										file: selectedNodes[0].basename,
									}),
									icon: SUPPORTED_FILE_FORMATS[extension],
									callback() {
										fileFormat = extension
									},
									type: 'primary',
								},
							]
						}
					}

					return []
				})
				.build()

			return this.picker
		},
	},
}
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
