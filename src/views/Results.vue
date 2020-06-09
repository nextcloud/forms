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
				{{ t('forms', 'Back to questions') }}
			</button>
		</TopBar>

		<header v-if="!noSubmissions">
			<h2>{{ t('forms', 'Responses for {title}', { title: formTitle }) }}</h2>
			<div class="response_actions">
				<button class="response_actions__export" @click="download">
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
				<template #action>
					<button class="primary" @click="copyShareLink">
						{{ t('forms', 'Copy share link') }}
					</button>
				</template>
			</EmptyContent>
		</section>

		<section v-else>
			<Submission
				v-for="submission in form.submissions"
				:key="submission.id"
				:submission="submission"
				:questions="form.questions"
				@delete="deleteSubmission(submission.id)" />
		</section>
	</AppContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { Parser } from 'json2csv'
import { showError, showSuccess } from '@nextcloud/dialogs'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import axios from '@nextcloud/axios'
import Clipboard from 'v-clipboard'
import moment from '@nextcloud/moment'
import Vue from 'vue'

import EmptyContent from '../components/EmptyContent'
import Submission from '../components/Results/Submission'
import TopBar from '../components/TopBar'
import ViewsMixin from '../mixins/ViewsMixin'
import SetWindowTitle from '../utils/SetWindowTitle'

Vue.use(Clipboard)

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
		}
	},

	computed: {
		noSubmissions() {
			return this.form.submissions?.length === 0
		},

		/**
		 * Return form title, or placeholder if not set
		 * @returns {string}
		 */
		formTitle() {
			if (this.form.title) {
				return this.form.title
			}
			return t('forms', 'New form')
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

		copyShareLink(event) {
			const $formLink = window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/${this.form.hash}`)
			if (this.$clipboard($formLink)) {
				showSuccess(t('forms', 'Form link copied'))
			} else {
				showError(t('forms', 'Cannot copy, please copy the link manually'))
			}
			// Set back focus as clipboard removes focus
			event.target.focus()
		},

		async loadFormResults() {
			this.loadingResults = true
			console.debug('Loading Results')

			try {
				const response = await axios.get(generateUrl('/apps/forms/api/v1/submissions/{hash}', {
					hash: this.form.hash,
				}))
				this.form.submissions = response.data.submissions
				this.form.questions = response.data.questions
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
				await axios.delete(generateUrl('/apps/forms/api/v1/submissions/{formId}', { formId: this.form.id }))
				this.form.submissions = []
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
			this.form.submissions.forEach(submission => {
				const formattedSubmission = {
					userDisplayName: submission.userDisplayName,
					timestamp: moment(submission.timestamp, 'X').format('L LT'),
				}

				this.form.questions.forEach(question => {
					const questionText = question.text
					const answers = submission.answers.filter(answer => answer.questionId === question.id)
					if (!answers.length) {
						return // no answers, go to next question
					}
					const squashedAnswers = answers.map(answer => answer.text).join('; ')
					formattedSubmission[questionText] = squashedAnswers
				})
				formattedSubmissions.push(formattedSubmission)
			})

			const element = document.createElement('a')
			element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(parser.parse(formattedSubmissions)))
			element.setAttribute('download', this.formTitle + '.csv')
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

.response_actions {
	display: flex;
	align-items: center;

	&__export {
		width: max-content;
		margin-left: 16px;
	}
}
</style>
