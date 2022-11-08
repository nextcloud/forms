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
	<NcAppContent v-if="loadingResults">
		<NcEmptyContent :title="t('forms', 'Loading responses …')">
			<template #icon>
				<NcLoadingIcon :size="64" />
			</template>
		</NcEmptyContent>
	</NcAppContent>

	<NcAppContent v-else>
		<TopBar :permissions="form?.permissions" @share-form="onShareForm" />
		<header v-if="!noSubmissions">
			<h2>{{ formTitle }}</h2>
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

				<!-- Action menu for CSV export and deletion -->
				<NcActions class="results-menu"
					:aria-label="t('forms', 'Options')"
					:force-menu="true">
					<NcActionButton :close-after-click="true" @click="onStoreToFiles">
						<template #icon>
							<IconFolder :size="20" />
						</template>
						{{ t('forms', 'Save CSV to Files') }}
					</NcActionButton>
					<NcActionLink :href="downloadUrl">
						<template #icon>
							<IconDownload :size="20" />
						</template>
						{{ t('forms', 'Download CSV') }}
					</NcActionLink>
					<NcActionLink :href="fileUrl" v-if="isLinked">
						<template #icon>
							<IconFolderOpenVariant :size="20" />

						</template>
						{{ t('forms', 'Open spreadSheet') }}
					</NcActionLink>
					<NcActionButton v-else @click="onLinkFile">
						<template #icon>
							<IconLinkVariant :size="20" />

						</template>
						{{ t('forms', 'Link to a spreadsheet') }}
					</NcActionButton>
					<NcActionButton :disabled="!isLinked" @click="unlinkFile">
						<template #icon>
							<IconLinkVariantOff :size="20" />
						</template>
						{{ t('forms', 'Unlink the spreadsheet') }}
					</NcActionButton>
					<NcActionButton @click="deleteAllSubmissions">
						<template #icon>
							<IconDelete :size="20" />
						</template>
						{{ t('forms', 'Delete all responses') }}
					</NcActionButton>
				</NcActions>
			</div>
		</header>

		<!-- No submissions -->
		<section v-if="noSubmissions">
			<NcEmptyContent :title="t('forms', 'No responses yet')"
				:description="t('forms', 'Results of submitted forms will show up here')">
				<template #icon>
					<IconPoll :size="64" />
				</template>
				<template #action>
					<NcButton type="primary" @click="onShareForm">
						<template #icon>
							<IconShareVariant :size="20" decorative />
						</template>
						{{ t('forms', 'Share form') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</section>

		<!-- Summary view for visualization -->
		<section v-if="!noSubmissions && showSummary">
			<ResultsSummary v-for="question in form.questions"
				:key="question.id"
				:question="question"
				:submissions="form.submissions" />
		</section>

		<!-- Responses view for individual responses -->
		<section v-if="!noSubmissions && !showSummary">
			<Submission v-for="submission in form.submissions"
				:key="submission.id"
				:submission="submission"
				:questions="form.questions"
				@delete="deleteSubmission(submission.id)" />
		</section>
	</NcAppContent>
</template>

<script>
import { generateOcsUrl, generateUrl } from '@nextcloud/router'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'

import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconDownload from 'vue-material-design-icons/Download.vue'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import IconLinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import IconLinkVariantOff from 'vue-material-design-icons/LinkVariantOff.vue'

import IconFolderOpenVariant from 'vue-material-design-icons/FolderOpen.vue'


import ResultsSummary from '../components/Results/ResultsSummary.vue'
import Submission from '../components/Results/Submission.vue'
import TopBar from '../components/TopBar.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'
import answerTypes from '../models/AnswerTypes.js'
import logger from '../utils/Logger.js'
import SetWindowTitle from '../utils/SetWindowTitle.js'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'

const picker = getFilePickerBuilder(t('forms', 'Save CSV to Files'))
	.setMultiSelect(false)
	.setModal(true)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'Results',


	components: {
		IconDelete,
		IconDownload,
		IconFolder,
		IconPoll,
		IconShareVariant,
		IconLinkVariant,
		IconFolderOpenVariant,
		IconLinkVariantOff,
		NcActions,
		NcActionButton,
		NcActionLink,
		NcAppContent,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		ResultsSummary,
		Submission,
		TopBar,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			loadingResults: true,
			showSummary: true,
			isLinked: false,
			fileID: ''
		}
	},

	computed: {
		noSubmissions() {
			return this.form.submissions?.length === 0
		},

		/**
		 * Return form title, or placeholder if not set
		 *
		 * @return {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
		},

		/**
		 * Generate the export to csv url
		 *
		 * @return {string}
		 */
		downloadUrl() {
			return generateOcsUrl('apps/forms/api/v2/submissions/export/{hash}', { hash: this.form.hash })
		},
		/**
		 * Generate Link to Linked File
		 *
		 * @return {string}
		 */
		fileUrl() {
			if(!!this.fileID){
				return generateUrl(`/f/${this.fileID.data.ocs.data}`)
			}
			return window.location.href
		},
	},

	watch: {
		// Reload results, when form changes
		hash() {
			this.loadFormResults()
		},
	},

	async beforeMount() {
		this.loadFormResults()
		SetWindowTitle(this.formTitle)
		this.isLinked = await this.isFileLinked()
		this.fileID = await axios.get(generateOcsUrl(`apps/forms/api/v3/submissions/fileId/${this.form.hash}`));
	},

	methods: {
		async unlinkFile(){
			await axios.post(generateOcsUrl('apps/forms/api/v3/submissions/unlink'),{
				hash: this.form.hash
			})
			this.isLinked = await this.isFileLinked()
		},
		async isFileLinked(){
			const fileId = await axios.get(generateOcsUrl(`apps/forms/api/v3/submissions/fileId/${this.form.hash}`))
			return !!fileId.data.ocs.data;
		},
		async loadFormResults() {
			this.loadingResults = true
			logger.debug(`Loading results for form ${this.form.hash}`)

			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2/submissions/{hash}', { hash: this.form.hash }))

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

		async onLinkFile() {
			if(!this.isLinked) {
				picker.pick()
					.then(async (path) => {
						try {
							 await axios.post(generateOcsUrl('apps/forms/api/v2/submissions/link'), {
								 hash: this.form.hash,
								 path
							})
							this.isLinked = true;
							this.fileID =  await axios.get(generateOcsUrl(`apps/forms/api/v3/submissions/fileId/${this.form.hash}`));
							showSuccess(t('forms', 'File successfully linked'))
						} catch (error) {
							logger.error('Error while exporting to Files and linking', { error })
							showError(t('forms', 'There was an error, while Linking the File'))
						}
					})
			}else{
				// Theoretically this will never fire
				showSuccess(t('forms', 'File is already linked'))
			}
		},
		// Show Filepicker, then call API to store
		async onStoreToFiles() {
			// picker.pick() does not reject Promise -> await would never resolve.
			picker.pick()
				.then(async (path) => {
					try {
						const response = await axios.post(generateOcsUrl('apps/forms/api/v2/submissions/export'), {
							hash: this.form.hash,
							path,
						})
						showSuccess(t('forms', 'Export successful to {file}', { file: OcsResponse2Data(response) }))
					} catch (error) {
						logger.error('Error while exporting to Files', { error })
						showError(t('forms', 'There was an error, while exporting to Files'))
					}
				})
		},

		async deleteSubmission(id) {
			this.loadingResults = true

			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v2/submission/{id}', { id }))
				const index = this.form.submissions.findIndex(search => search.id === id)
				this.form.submissions.splice(index, 1)
			} catch (error) {
				logger.error(`Error while removing response ${id}`, { error })
				showError(t('forms', 'There was an error while removing this response'))
			} finally {
				this.loadingResults = false
			}
		},

		async deleteAllSubmissions() {
			if (!confirm(t('forms', 'Are you sure you want to delete all responses of {title}?', { title: this.formTitle }))) {
				return
			}

			this.loadingResults = true
			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v2/submissions/{formId}', { formId: this.form.id }))
				this.form.submissions = []
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
					.map(question => [question.id, question.type])
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
	},
}
</script>

<style lang="scss" scoped>
.app-content header {
	h2 {
		font-size: 2em;
		font-weight: bold;
		margin-top: 32px;
		padding-left: 14px;
		padding-bottom: 8px;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	p {
		padding-left: 14px;
	}

	.response-actions {
		display: flex;
		align-items: center;
		padding-left: 14px;

		&__radio {
			margin-right: 8px;

			&__item {
				border-radius: var(--border-radius-pill);
				padding: 8px 16px;
				font-weight: bold;
				background-color: var(--color-background-dark);

				&:first-of-type {
					border-top-right-radius: 0;
					border-bottom-right-radius: 0;
					padding-right: 8px;
				}

				&:last-of-type {
					border-top-left-radius: 0;
					border-bottom-left-radius: 0;
					padding-left: 8px;
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
