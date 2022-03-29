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
	<AppContent v-if="loadingResults">
		<EmptyContent icon="icon-loading">
			{{ t('forms', 'Loading responses …') }}
		</EmptyContent>
	</AppContent>

	<AppContent v-else>
		<TopBar>
			<button @click="showEdit">
				<span class="icon-forms" role="img" />
				{{ t('forms', 'Back to questions') }}
			</button>
			<button v-if="!noSubmissions" @click="onShareForm">
				<span class="icon-share" role="img" />
				{{ t('forms', 'Share form') }}
			</button>
		</TopBar>

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
				<Actions class="results-menu"
					:aria-label="t('forms', 'Options')"
					:force-menu="true">
					<ActionButton :close-after-click="true" icon="icon-folder" @click="onStoreToFiles">
						{{ t('forms', 'Save CSV to Files') }}
					</ActionButton>
					<ActionLink icon="icon-download" :href="downloadUrl">
						{{ t('forms', 'Download CSV') }}
					</ActionLink>
					<ActionButton icon="icon-delete" @click="deleteAllSubmissions">
						{{ t('forms', 'Delete all responses') }}
					</ActionButton>
				</Actions>
			</div>
		</header>

		<!-- No submissions -->
		<section v-if="noSubmissions">
			<EmptyContent icon="icon-comment">
				{{ t('forms', 'No responses yet') }}
				<template #desc>
					{{ t('forms', 'Results of submitted forms will show up here') }}
				</template>
				<template #action>
					<button class="primary" @click="onShareForm">
						<span class="icon-share-primary" role="img" />
						{{ t('forms', 'Share form') }}
					</button>
				</template>
			</EmptyContent>
		</section>

		<!-- Summary view for visualization -->
		<section v-if="!noSubmissions && showSummary">
			<Summary v-for="question in form.questions"
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
	</AppContent>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import axios from '@nextcloud/axios'

import EmptyContent from '../components/EmptyContent'
import Summary from '../components/Results/Summary'
import Submission from '../components/Results/Submission'
import TopBar from '../components/TopBar'
import ShareLinkMixin from '../mixins/ShareLinkMixin'
import ViewsMixin from '../mixins/ViewsMixin'
import SetWindowTitle from '../utils/SetWindowTitle'
import OcsResponse2Data from '../utils/OcsResponse2Data'

const picker = getFilePickerBuilder(t('forms', 'Save CSV to Files'))
	.setMultiSelect(false)
	.setModal(true)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'Results',

	components: {
		Actions,
		ActionButton,
		ActionLink,
		AppContent,
		EmptyContent,
		Summary,
		Submission,
		TopBar,
	},

	mixins: [ViewsMixin, ShareLinkMixin],

	data() {
		return {
			loadingResults: true,
			showSummary: true,
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
	},

	watch: {
		// Reload results, when form changes
		hash() {
			this.loadFormResults()
		},
	},

	beforeMount() {
		this.loadFormResults()
		SetWindowTitle(this.formTitle)
	},

	methods: {
		showEdit() {
			this.$router.push({
				name: 'edit',
				params: {
					hash: this.form.hash,
				},
			})
		},

		onShareForm() {
			this.$emit('open-sharing', this.form.hash)
		},

		async loadFormResults() {
			this.loadingResults = true
			console.debug('Loading results for form', this.form.hash)

			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v2/submissions/{hash}', { hash: this.form.hash }))

				// Append questions & submissions
				this.$set(this.form, 'submissions', OcsResponse2Data(response).submissions)
				this.$set(this.form, 'questions', OcsResponse2Data(response).questions)
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while loading the results'))
			} finally {
				this.loadingResults = false
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
						console.error(error)
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
				console.error(error)
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
				console.error(error)
				showError(t('forms', 'There was an error while removing responses'))
			} finally {
				this.loadingResults = false
			}
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
