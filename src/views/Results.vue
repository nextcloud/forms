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
	<AppContent>
		<div>
			<button class="button btn primary" @click="download">
				<span>{{ "Export to CSV" }}</span>
			</button>
		</div>
		<h1>{{ "Statistics" }}</h1>
		<div v-for="sum in stats" :key="sum">
			{{ sum }}
		</div>
		<div id="app-content">
			<transition-group
				name="list"
				tag="div"
				class="table">
				<ResultItem
					key="0"
					:header="true" />
				<li
					is="resultItem"
					v-for="(answer, index) in answers"
					:key="answer.id"
					:answer="answer"
					@viewResults="viewFormResults(index, form.form, 'results')" />
			</transition-group>
			<LoadingOverlay v-if="loading" />
			<modal-dialog />
		</div>
	</AppContent>
</template>

<script>
import ResultItem from '../components/resultItem'
import json2csvParser from 'json2csv'
import axios from '@nextcloud/axios'
import LoadingOverlay from '../components/_base-LoadingOverlay'
import ViewsMixin from '../mixins/ViewsMixin'
import { generateUrl } from '@nextcloud/router'

import AppContent from '@nextcloud/vue/dist/Components/AppContent'
export default {
	name: 'Results',

	components: {
		AppContent,
		ResultItem,
		LoadingOverlay,
	},

	mixins: [ViewsMixin],

	data() {
		return {
			loading: true,
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
	},

	created() {
		this.indexPage = OC.generateUrl('apps/forms/')
		this.loadForms()
	},

	methods: {
		loadForms() {
			this.loading = true
			axios.get(generateUrl('apps/forms/api/v1/submissions/{hash}', { hash: this.$route.params.hash }))
				.then((response) => {
					if (response.data == null) {
						this.answers = null
						OC.Notification.showTemporary('Access Denied')
					} else {
						this.answers = response.data
					}
					this.loading = false
				}, (error) => {
					/* eslint-disable-next-line no-console */
					console.log(error.response)
					this.loading = false
				})
		},
		viewFormResults(index, form, name) {
			this.$router.push({
				name: name,
				params: {
					hash: form.id,
				},
			})
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

<style lang="scss">

.table {
	width: 100%;
	margin-top: 45px;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	flex-wrap: nowrap;
}

#emptycontent {
	.icon-forms {
		background-color: black;
		-webkit-mask: url('./img/app.svg') no-repeat 50% 50%;
		mask: url('./img/app.svg') no-repeat 50% 50%;
	}
}

</style>
