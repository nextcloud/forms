<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent :page-heading="t('forms', 'Results')">
		<NcDialog
			:open.sync="showLinkedFileNotAvailableDialog"
			:name="t('forms', 'Linked file not available')"
			:message="
				t(
					'forms',
					'Linked file is not available, would you like to link a new file?',
				)
			"
			:buttons="linkedFileNotAvailableButtons"
			size="normal"
			:can-close="false" />

		<TopBar
			:archived="isFormArchived"
			:permissions="form?.permissions"
			:sidebar-opened="sidebarOpened"
			@share-form="onShareForm" />

		<!-- Loading submissions -->
		<NcEmptyContent
			v-if="loadingResults"
			class="forms-emptycontent"
			:name="t('forms', 'Loading responses …')">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<!-- Showing submissions -->
		<template v-else>
			<header>
				<h2 dir="auto">
					{{ formTitle }}
				</h2>
				<p>
					{{
						t('forms', '{amount} responses', {
							amount: submissions.length ?? 0,
						})
					}}
				</p>

				<!-- View switcher between Summary and Responses -->
				<div class="response-actions">
					<PillMenu
						:disabled="noSubmissions"
						:options="responseViews"
						:active.sync="activeResponseView"
						class="response-actions__toggle" />

					<!-- Action menu for cloud export and deletion -->
					<NcActions
						:aria-label="t('forms', 'Options')"
						force-name
						:inline="isMobile ? 0 : 1"
						@blur="isDownloadActionOpened = false"
						@close="isDownloadActionOpened = false">
						<template v-if="!isDownloadActionOpened">
							<NcActionButton
								v-if="canEditForm && !form.fileId"
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
								<NcActionButton
									close-after-click
									@click="onReExport">
									<template #icon>
										<IconRefresh :size="20" />
									</template>
									{{ t('forms', 'Re-export spreadsheet') }}
								</NcActionButton>
								<NcActionButton
									close-after-click
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
								:is-menu="true"
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
									<IconFileExcel :size="20" />
								</template>
								XSLX
							</NcActionButton>
						</template>
					</NcActions>
				</div>
			</header>

			<!-- No submissions -->
			<NcEmptyContent
				v-if="noSubmissions"
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
						<NcButton type="primary" @click="onShareForm">
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
					:submission="submission"
					:questions="questions"
					:can-delete-submission="canDeleteSubmissions"
					@delete="deleteSubmission(submission.id)" />
			</section>
		</template>

		<!-- Confirmation dialog for deleting all submissions -->
		<NcDialog
			:open.sync="showConfirmDeleteDialog"
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
import { getRequestToken } from '@nextcloud/auth'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { useIsSmallMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'

import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'

import IconCancelSvg from '@mdi/svg/svg/cancel.svg?raw'
import IconChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconDeleteSvg from '@mdi/svg/svg/delete.svg?raw'
import IconDownload from 'vue-material-design-icons/Download.vue'
import IconFileDelimited from 'vue-material-design-icons/FileDelimited.vue'
import IconFileDelimitedSvg from '@mdi/svg/svg/file-delimited.svg?raw'
import IconFileExcel from 'vue-material-design-icons/FileExcel.vue'
import IconFileExcelSvg from '@mdi/svg/svg/file-excel.svg?raw'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconLink from 'vue-material-design-icons/Link.vue'
import IconLinkSvg from '@mdi/svg/svg/link.svg?raw'
import IconLinkVariantOff from 'vue-material-design-icons/LinkVariantOff.vue'
import IconLinkVariantOffSvg from '@mdi/svg/svg/link-variant-off.svg?raw'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import IconRefresh from 'vue-material-design-icons/Refresh.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import IconTable from 'vue-material-design-icons/Table.vue'
import IconTableSvg from '@mdi/svg/svg/table.svg?raw'

import { FormState } from '../models/FormStates.ts'
import ResultsSummary from '../components/Results/ResultsSummary.vue'
import Submission from '../components/Results/Submission.vue'
import TopBar from '../components/TopBar.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'
import answerTypes from '../models/AnswerTypes.js'
import logger from '../utils/Logger.js'
import SetWindowTitle from '../utils/SetWindowTitle.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
import PermissionTypes from '../mixins/PermissionTypes.js'
import PillMenu from '../components/PillMenu.vue'

const SUPPORTED_FILE_FORMATS = {
	ods: IconTableSvg,
	csv: IconFileDelimitedSvg,
	xlsx: IconFileExcelSvg,
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
	name: 'Results',

	components: {
		IconChevronLeft,
		IconDelete,
		IconDownload,
		IconFileDelimited,
		IconFileExcel,
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
		NcEmptyContent,
		NcLoadingIcon,
		PillMenu,
		ResultsSummary,
		Submission,
		TopBar,
	},

	mixins: [PermissionTypes, ViewsMixin],

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

			isDownloadActionOpened: false,
			loadingResults: true,

			picker: null,
			showConfirmDeleteDialog: false,

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
			return this.canEditForm && this.form.fileId && !this.form.filePath
		},
	},

	watch: {
		// Reload results, when form changes
		async hash() {
			await this.fetchFullForm(this.form.id)
			this.loadFormResults()
			SetWindowTitle(this.formTitle)
		},
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
				const response = await axios.get(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/submissions', {
						id: this.form.id,
					}),
				)

				let loadedSubmissions = OcsResponse2Data(response).submissions
				const loadedQuestions = OcsResponse2Data(response).questions

				loadedSubmissions = this.formatDateAnswers(
					loadedSubmissions,
					loadedQuestions,
				)

				// Append questions & submissions
				this.submissions = loadedSubmissions
				this.questions = loadedQuestions
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
				}) +
				'?requesttoken=' +
				encodeURIComponent(getRequestToken()) +
				'&fileFormat=' +
				fileFormat
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
							(question.type === 'date') |
							(question.type === 'datetime') |
							(question.type === 'time'),
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
				.setButtonFactory((selectedNodes, currentPath, currentView) => {
					if (selectedNodes.length === 1) {
						const extension = selectedNodes[0].extension
							?.slice(1)
							?.toLowerCase()
						if (!extension) {
							return [
								{
									label: t('forms', 'Create XLSX'),
									icon: IconFileExcelSvg,
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
		margin-top: 8px;
		padding-left: calc(14px - var(--border-radius-pill));

		&__toggle {
			margin-right: 1em;
		}
	}
}
</style>
