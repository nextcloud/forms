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
			<div v-for="sum in stats" :key="sum">
				{{ sum }}
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
			<button id="exportButton" class="primary" @click="download">
				<span class="icon-download-white" role="img" />
				{{ t('forms', 'Export to CSV') }}
			</button>
			<transition-group
				name="list"
				tag="div"
				class="table">
				<ResultItem
					key="0"
					:header="true" />
				<ResultItem
					v-for="answer in answers"
					:key="answer.id"
					:answer="answer" />
			</transition-group>
		</section>
	</AppContent>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import axios from '@nextcloud/axios'

import EmptyContent from '../components/EmptyContent'
import TopBar from '../components/TopBar'
import ViewsMixin from '../mixins/ViewsMixin'

import ResultItem from '../components/resultItem'
import json2csvParser from 'json2csv'

export default {
	name: 'Results',

	components: {
		AppContent,
		EmptyContent,
		ResultItem,
		TopBar,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			loadingResults: true,
			answers: [],
		}
	},

	computed: {
		stats() {
			const sums = []

			if (this.answers != null) {
				const uniqueAns = []
				const uniqueQs = []
				const ansToQ = new Map()
				for (let i = 0; i < this.answers.length; i++) {
					if (this.answers[i].questionType === 'radiogroup' || this.answers[i].questionType === 'dropdown') {
						if (uniqueAns.includes(this.answers[i].text) === false) {
							uniqueAns.push(this.answers[i].text)
							ansToQ.set(this.answers[i].text, this.answers[i].questionId)
						}
						if (uniqueQs.includes(this.answers[i].questionId) === false) {
							uniqueQs.push(this.answers[i].questionId)
						}
					}
				}
				for (let i = 0; i < uniqueAns.length; i++) {
					sums[i] = 0
				}
				for (let i = 0; i < this.answers.length; i++) {
					sums[uniqueAns.indexOf(this.answers[i].text)]++
				}
				for (let i = 0; i < sums.length; i++) {
					sums[i] = 'Question ' + ansToQ.get(uniqueAns[i]) + ':  ' + (sums[i] / ((this.answers.length / uniqueQs.length)) * 100).toFixed(2) + '%' + ' of respondents voted for answer choice: ' + uniqueAns[i]
				}
			}

			return sums.sort()
		},

		noSubmissions() {
			return this.answers && this.answers.length === 0
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
				this.answers = response.data
				console.debug(this.answers)
			} catch (error) {
				console.error(error)
				showError(t('forms', 'There was an error while loading results'))
			} finally {
				this.loadingResults = false
			}
		},

		download() {
			this.loading = true
			axios.get(OC.generateUrl('apps/forms/get/form/' + this.$route.params.hash))
				.then((response) => {
					this.json2csvParser = ['userId', 'questionId', 'questionText', 'Answer'] // TODO Is this one necessary??
					const formattedAns = []
					this.answers.forEach(ans => {
						formattedAns.push({
							userId: ans['userId'],
							questionId: ans['questionId'],
							questionText: ans['questionText'],
							answer: ans['text'],
						})
					})
					const element = document.createElement('a')
					element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(json2csvParser.parse(formattedAns)))
					element.setAttribute('download', response.data.title + '.csv')

					element.style.display = 'none'
					document.body.appendChild(element)
					element.click()
					document.body.removeChild(element)
					this.loading = false
				}, (error) => {
					/* eslint-disable-next-line no-console */
					console.log(error.response)
					this.loading = false
				})
		},
	},
}
</script>

<style lang="scss" scoped>
.table {
	width: 100%;
	margin-top: 45px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	flex-wrap: nowrap;
}

#exportButton {
	width: max-content;
}
</style>
