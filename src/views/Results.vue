<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author Ajfar Huq
  - @author Nick Gallo
  - @author Affan Hussain
  -
  - @license GNU AGPL version 3 or any later version
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
				{{ t('forms', 'Back to form') }}
			</button>
		</TopBar>

		<header v-if="!noSubmissions">
			<h2>{{ t('forms', 'Responses for {title}', { title: form.title }) }}</h2>
			<div>
				<button id="exportButton" @click="download">
					<span class="icon-download" role="img" />
					{{ t('forms', 'Export to CSV') }}
				</button>
				<Actions class="results-menu"
					:aria-label="t('forms', 'Options')"
					:force-menu="true">
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
				<!-- Button to copy Share-Link? -->
			</EmptyContent>
		</section>

		<section v-else>
			<Submission
				v-for="submission in submissions"
				:key="submission.id"
				:submission="submission"
				:questions="questions"
				@delete="deleteSubmission(submission.id)" />
		</section>
	</AppContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { Parser } from 'json2csv'
import { showError } from '@nextcloud/dialogs'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'

import EmptyContent from '../components/EmptyContent'
import Submission from '../components/Results/Submission'
import TopBar from '../components/TopBar'
import ViewsMixin from '../mixins/ViewsMixin'

export default {
	name: 'Results',

	components: {
		Actions,
		ActionButton,
		AppContent,
		EmptyContent,
		Submission,
		TopBar,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			loadingResults: true,
			submissions: [],
			questions: [],
		}
	},

	computed: {
		noSubmissions() {
			return this.submissions && this.submissions.length === 0
		},
	},

	beforeMount() {
		this.loadFormResults()
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

		async loadFormResults() {
			this.loadingResults = true
			console.debug('Loading Results')

			try {
				const response = await axios.get(generateUrl('/apps/forms/api/v1/submissions/{hash}', {
					hash: this.form.hash,
				}))
				this.submissions = response.data.submissions
				this.questions = response.data.questions
				console.debug(this.submissions)
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while loading results'))
			} finally {
				this.loadingResults = false
			}
		},

		async deleteSubmission(id) {
			this.loadingResults = true

			try {
				await axios.delete(generateUrl('/apps/forms/api/v1/submission/{id}', { id }))
				const index = this.submissions.findIndex(search => search.id === id)
				this.submissions.splice(index, 1)
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while removing this response'))
			} finally {
				this.loadingResults = false
			}
		},

		async deleteAllSubmissions() {
			if (!confirm(t('forms', 'Are you sure you want to delete all responses of this form?'))) {
				return
			}

			this.loadingResults = true
			try {
				await axios.delete(generateUrl('/apps/forms/api/v1/submissions/{formId}', { formId: this.form.id }))
				this.submissions = []
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while removing responses'))
			} finally {
				this.loadingResults = false
			}
		},

		download() {
			this.loadingResults = true

			const parser = new Parser({
				delimiter: ',',
			})

			const formattedSubmissions = []
			this.submissions.forEach(submission => {
				const formattedSubmission = {
					userDisplayName: submission.userDisplayName,
					timestamp: moment(submission.timestamp, 'X').format('L LT'),
				}

				submission.answers.forEach(answer => {
					const questionText = this.questions.find(question => question.id === answer.questionId).text
					if (questionText in formattedSubmission) {
						formattedSubmission[questionText] = formattedSubmission[questionText].concat('; ').concat(answer.text)
					} else {
						formattedSubmission[questionText] = answer.text
					}
				})
				formattedSubmissions.push(formattedSubmission)
			})

			const element = document.createElement('a')
			element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(parser.parse(formattedSubmissions)))
			element.setAttribute('download', this.form.title + '.csv')
			element.style.display = 'none'
			document.body.appendChild(element)
			element.click()
			document.body.removeChild(element)

			this.loadingResults = false
		},
	},
}
</script>

<style lang="scss" scoped>
h2 {
	font-size: 2em;
	font-weight: bold;
	margin-top: 32px;
	padding-left: 14px;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

#exportButton {
	width: max-content;
	padding: 13px 16px;
	margin-left: 16px;
}
</style>
