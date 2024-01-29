<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author Ajfar Huq
  - @author Nick Gallo
  - @author Affan Hussain
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
	<NcAppContent>
		<NcDialog :open.sync="showLinkedFileNotAvailableDialog"
			:name="t('forms', 'Linked file not available')"
			:message="t('forms', 'Linked file is not available, would you like to link a new file?')"
			:buttons="linkedFileNotAvailableButtons"
			size="normal"
			:can-close="false" />

		<TopBar :permissions="form?.permissions"
			:sidebar-opened="sidebarOpened"
			@update:sidebarOpened="onSidebarChange"
			@share-form="onShareForm" />

		<!-- Loading submissions -->
		<NcEmptyContent v-if="loadingResults"
			class="forms-emptycontent"
			:name="t('forms', 'Loading responses …')">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>

		<!-- No submissions -->
		<NcEmptyContent v-else-if="noSubmissions"
			:name="t('forms', 'No responses yet')"
			class="forms-emptycontent"
			:description="t('forms', 'Results of submitted forms will show up here')">
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

					<NcButton v-if="!form.fileId" type="tertiary-no-background" @click="onLinkFile">
						<template #icon>
							<IconLink :size="20" />
						</template>
						{{ t('forms', 'Create spreadsheet') }}
					</NcButton>

					<NcButton v-if="form.fileId" :href="fileUrl" type="tertiary-no-background">
						<template #icon>
							<IconTable :size="20" />
						</template>
						{{ t('forms', 'Open spreadsheet') }}
					</NcButton>
				</div>
			</template>
		</NcEmptyContent>

		<!-- Showing submissions -->
		<template v-else>
			<header>
				<h2 dir="auto">
					{{ formTitle }}
				</h2>
				<p>{{ t('forms', '{amount} responses', { amount: form.submissions.length }) }}</p>

				<!-- View switcher between Summary and Responses -->
				<div class="response-actions">
					<div class="response-actions__radio">
						<input id="show-summary--true"
							v-model="showSummary"
							type="radio"
							:value="true"
							class="hidden">
						<label for="show-summary--true"
							class="response-actions__radio__item"
							:class="{ 'response-actions__radio__item--active': showSummary }">
							{{ t('forms', 'Summary') }}
						</label>
						<input id="show-summary--false"
							v-model="showSummary"
							type="radio"
							:value="false"
							class="hidden">
						<label for="show-summary--false"
							class="response-actions__radio__item"
							:class="{ 'response-actions__radio__item--active': !showSummary }">
							{{ t('forms', 'Responses') }}
						</label>
					</div>

					<NcButton v-if="form.fileId" :href="fileUrl" type="tertiary-no-background">
						<template #icon>
							<IconTable :size="20" />
						</template>
						{{ t('forms', 'Open spreadsheet') }}
					</NcButton>

					<NcActions v-else type="tertiary-no-background" :force-name="true">
						<NcActionButton @click="onLinkFile">
							<template #icon>
								<IconLink :size="20" />
							</template>
							{{ t('forms', 'Create spreadsheet') }}
						</NcActionButton>
					</NcActions>

					<!-- Action menu for cloud export and deletion -->
					<NcActions :aria-label="t('forms', 'Options')"
						:force-menu="true"
						@close="isDownloadActionOpened = false">
						<template v-if="!isDownloadActionOpened">
							<template v-if="form.fileId">
								<NcActionButton :close-after-click="true" @click="onReExport">
									<template #icon>
										<IconRefresh :size="20" />
									</template>
									{{ t('forms', 'Re-export spreadsheet') }}
								</NcActionButton>
								<NcActionButton :close-after-click="true" @click="onUnlinkFile">
									<template #icon>
										<IconLinkVariantOff :size="20" />
									</template>
									{{ t('forms', 'Unlink spreadsheet') }}
								</NcActionButton>
								<NcActionSeparator />
							</template>
							<NcActionButton :close-after-click="true" @click="onStoreToFiles">
								<template #icon>
									<IconFolder :size="20" />
								</template>
								{{ t('forms', 'Save copy to Files') }}
							</NcActionButton>
							<NcActionButton :close-after-click="false"
								:is-menu="true"
								@click="isDownloadActionOpened = true">
								<template #icon>
									<IconDownload :size="20" />
								</template>
								{{ t('forms', 'Download') }}
							</NcActionButton>

							<NcActionButton v-if="canDeleteSubmissions"
								:close-after-click="true"
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
							<NcActionButton :close-after-click="true" @click="onDownloadFile('csv')">
								<template #icon>
									<IconFileDelimited :size="20" />
								</template>
								CSV
							</NcActionButton>
							<NcActionButton :close-after-click="true" @click="onDownloadFile('ods')">
								<template #icon>
									<IconTable :size="20" />
								</template>
								ODS
							</NcActionButton>
							<NcActionButton :close-after-click="true" @click="onDownloadFile('xlsx')">
								<template #icon>
									<IconFileExcel :size="20" />
								</template>
								XSLX
							</NcActionButton>
						</template>
					</NcActions>
				</div>
			</header>

			<!-- Summary view for visualization -->
			<section v-if="showSummary">
				<ResultsSummary v-for="question in form.questions"
					:key="question.id"
					:question="question"
					:submissions="form.submissions" />
			</section>

			<!-- Responses view for individual responses -->
			<section v-else>
				<Submission v-for="submission in form.submissions"
					:key="submission.id"
					:submission="submission"
					:questions="form.questions"
					:can-delete-submission="canDeleteSubmissions"
					@delete="deleteSubmission(submission.id)" />
			</section>
		</template>

		<!-- Confirmation dialog for deleting all submissions -->
		<NcDialog :open.sync="showConfirmDeleteDialog"
			:name="t('forms', 'Delete submissions')"
			:message="t('forms', 'Are you sure you want to delete all responses of {title}?', { title: formTitle })"
			:buttons="confirmDeleteButtons" />
	</NcAppContent>
</template>

<script>
import { getRequestToken } from '@nextcloud/auth'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'

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

import ResultsSummary from '../components/Results/ResultsSummary.vue'
import Submission from '../components/Results/Submission.vue'
import TopBar from '../components/TopBar.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'
import answerTypes from '../models/AnswerTypes.js'
import logger from '../utils/Logger.js'
import SetWindowTitle from '../utils/SetWindowTitle.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
import PermissionTypes from '../mixins/PermissionTypes.js'

const SUPPORTED_FILE_FORMATS = { ods: IconTableSvg, csv: IconFileDelimitedSvg, xlsx: IconFileExcelSvg }
let fileFormat = 'csv'

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
		ResultsSummary,
		Submission,
		TopBar,
	},

	mixins: [PermissionTypes, ViewsMixin],

	data() {
		return {
			isDownloadActionOpened: false,
			loadingResults: true,
			picker: null,
			showSummary: true,
			showConfirmDeleteDialog: false,
			showLinkedFileNotAvailableDialog: false,
			linkedFileNotAvailableButtons: [
				{
					label: t('forms', 'Unlink spreadsheet'),
					icon: IconLinkVariantOffSvg,
					type: 'error',
					callback: () => { this.onUnlinkFile() },
				},
				{
					label: t('forms', 'Create spreadsheet'),
					icon: IconLinkSvg,
					type: 'primary',
					callback: () => { this.onLinkFile() },
				},
			],
			confirmDeleteButtons: [
				{
					label: t('forms', 'Cancel'),
					icon: IconCancelSvg,
					type: 'tertiary',
					callback: () => { this.showConfirmDeleteDialog = false },
				},
				{
					label: t('forms', 'Delete submissions'),
					icon: IconDeleteSvg,
					type: 'error',
					callback: () => { this.deleteAllSubmissionsConfirmed() },
				},
			],
		}
	},

	computed: {
		canDeleteSubmissions() {
			return this.form.permissions.includes(this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE)
		},

		noSubmissions() {
			return this.form.submissions?.length === 0
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
	},

	watch: {
		// Reload results, when form changes
		async hash() {
			this.loadFormResults()
			await this.fetchLinkedFileInfo()
		},
	},

	async beforeMount() {
		this.loadFormResults()
		await this.fetchLinkedFileInfo()
		SetWindowTitle(this.formTitle)
	},

	methods: {
		async onUnlinkFile() {
			await axios.post(generateOcsUrl('apps/forms/api/v2.4/form/unlink'), {
				hash: this.form.hash,
			})

			this.form.fileFormat = null
			this.form.fileId = null
			this.form.filePath = null
		},
		async loadFormResults() {
			this.loadingResults = true
			logger.debug(`Loading results for form ${this.form.hash}`)

			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2.2/submissions/{hash}', { hash: this.form.hash }))

				let loadedSubmissions = OcsResponse2Data(response).submissions
				const loadedQuestions = OcsResponse2Data(response).questions

				loadedSubmissions = this.formatDateAnswers(loadedSubmissions, loadedQuestions)

				// Append questions & submissions
				this.$set(this.form, 'submissions', loadedSubmissions)
				this.$set(this.form, 'questions', loadedQuestions)
			} catch (error) {
				logger.error('Error while loading results', { error })
				showError(t('forms', 'There was an error while loading the results'))
			} finally {
				this.loadingResults = false
			}
		},

		async onDownloadFile(fileFormat) {
			const exportUrl = generateOcsUrl('apps/forms/api/v2.4/submissions/export/{hash}', { hash: this.form.hash })
				+ '?requesttoken=' + encodeURIComponent(getRequestToken())
				+ '&fileFormat=' + fileFormat
			window.open(exportUrl, '_self')
		},

		async onLinkFile() {
			try {
				await this.getPicker().pick()
					.then(async (path) => {
						try {
							const response = await axios.post(generateOcsUrl('apps/forms/api/v2.4/form/link/{fileFormat}', { fileFormat }), {
								hash: this.form.hash,
								path,
							})
							const responseData = OcsResponse2Data(response)

							this.form.fileFormat = responseData.fileFormat
							this.form.fileId = responseData.fileId
							this.form.filePath = responseData.filePath

							showSuccess(t('forms', 'File {file} successfully linked', { file: responseData.fileName }))
						} catch (error) {
							logger.error('Error while exporting to Files and linking', { error })
							showError(t('forms', 'There was an error while linking the file'))
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
				await this.getPicker().pick()
					.then(async (path) => {
						try {
							const response = await axios.post(generateOcsUrl('apps/forms/api/v2.4/submissions/export'), { hash: this.form.hash, path, fileFormat })
							showSuccess(t('forms', 'Export successful to {file}', { file: OcsResponse2Data(response) }))
						} catch (error) {
							logger.error('Error while exporting to Files', { error })
							showError(t('forms', 'There was an error while exporting to Files'))
						}
					})
			} catch (error) {
				// User aborted
				logger.debug('No file selected', { error })
			}
		},

		async fetchLinkedFileInfo() {
			const response = await axios.get(generateOcsUrl('apps/forms/api/v2.4/form/{id}', { id: this.form.id }))
			const form = OcsResponse2Data(response)
			this.$set(this.form, 'fileFormat', form.fileFormat)
			this.$set(this.form, 'fileId', form.fileId)
			this.$set(this.form, 'filePath', form.filePath)
			this.showLinkedFileNotAvailableDialog = form.fileId && !form.filePath
		},

		async onReExport() {
			if (!this.form.fileId) {
				// Theoretically this will never fire
				showError(t('forms', 'File is not linked'))
				return
			}
			try {
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2.1/submissions/export'),
					{ hash: this.form.hash, path: this.form.filePath, fileFormat: this.form.fileFormat },
				)
				showSuccess(t('forms', 'Export successful to {file}', { file: OcsResponse2Data(response) }))
			} catch (error) {
				logger.error('Error while exporting to Files', { error })
				showError(t('forms', 'There was an error, while exporting to Files'))
			}
		},

		async deleteSubmission(id) {
			this.loadingResults = true

			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v2.2/submission/{id}', { id }))
				showSuccess(t('forms', 'Submission deleted'))
				const index = this.form.submissions.findIndex(search => search.id === id)
				this.form.submissions.splice(index, 1)
				emit('forms:last-updated:set', this.form.id)
			} catch (error) {
				logger.error(`Error while removing response ${id}`, { error })
				showError(t('forms', 'There was an error while removing this response'))
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
				await axios.delete(generateOcsUrl('apps/forms/api/v2.2/submissions/{formId}', { formId: this.form.id }))
				this.form.submissions = []
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
					.filter(question => question.type === 'date' | question.type === 'datetime' | question.type === 'time')
					.map(question => [question.id, question.type]),
			)

			// Go through submissions and reformat answers to date/time questions
			submissions.forEach(submission => {
				submission.answers.filter(answer => answer.questionId in dateQuestions)
					.forEach(answer => {
						const date = moment(answer.text, answerTypes[dateQuestions[answer.questionId]].storageFormat)
						if (date.isValid()) {
							answer.text = date.format(answerTypes[dateQuestions[answer.questionId]].momentFormat)
						}
					})
			})

			return submissions
		},

		getPicker() {
			if (this.picker !== null) {
				return this.picker
			}

			this.picker = getFilePickerBuilder(t('forms', 'Choose spreadsheet location'))
				.setMultiSelect(false)
				.allowDirectories()
				.setButtonFactory((selectedNodes, currentPath, currentView) => {
					if (selectedNodes.length === 1) {
						const extension = selectedNodes[0].extension.slice(1).toLowerCase()
						if (SUPPORTED_FILE_FORMATS[extension]) {
							return [{
								label: t('forms', 'Select {file}', { file: selectedNodes[0].basename }),
								icon: SUPPORTED_FILE_FORMATS[extension],
								callback() {
									fileFormat = extension
								},
								type: 'primary',
							}]
						}

						return []
					}

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
		margin-inline-start: 56px;

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
		align-items: center;
		padding-inline-start: 14px;

		&__radio {
			margin-inline-end: 8px;

			&__item {
				border-radius: var(--border-radius-pill);
				padding-block: 8px;
				padding-inline: 16px;
				font-weight: bold;
				background-color: var(--color-background-dark);

				&:first-of-type {
					border-start-end-radius: 0;
					border-end-end-radius: 0;
					padding-inline-end: 8px;
				}

				&:last-of-type {
					border-start-start-radius: 0;
					border-end-start-radius: 0;
					padding-inline-start: 8px;
				}

				&--active {
					background-color: var(--color-primary);
					color: var(--color-primary-text)
				}
			}
		}
	}
}
</style>
