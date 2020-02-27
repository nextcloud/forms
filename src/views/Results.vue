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
	<div>
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
					v-for="(vote, index) in votes"
					:key="vote.id"
					:vote="vote"
					@viewResults="viewFormResults(index, form.event, 'results')" />
			</transition-group>
			<LoadingOverlay v-if="loading" />
			<modal-dialog />
		</div>
	</div>
</template>

<script>
import ResultItem from '../components/resultItem'
import json2csvParser from 'json2csv'
import axios from '@nextcloud/axios'
import LoadingOverlay from '../components/_base-LoadingOverlay'

export default {
	name: 'Results',

	components: {
		ResultItem,
		LoadingOverlay,
	},

	data() {
		return {
			loading: true,
			votes: [],

		}
	},

	computed: {
		stats() {
			const sums = []

			if (this.votes != null) {
				const uniqueAns = []
				const uniqueQs = []
				const ansToQ = new Map()
				for (let i = 0; i < this.votes.length; i++) {
					if (this.votes[i].voteOptionType === 'radiogroup' || this.votes[i].voteOptionType === 'dropdown') {
						if (uniqueAns.includes(this.votes[i].voteAnswer) === false) {
							uniqueAns.push(this.votes[i].voteAnswer)
							ansToQ.set(this.votes[i].voteAnswer, this.votes[i].voteOptionId)
						}
						if (uniqueQs.includes(this.votes[i].voteOptionId) === false) {
							uniqueQs.push(this.votes[i].voteOptionId)
						}
					}
				}
				for (let i = 0; i < uniqueAns.length; i++) {
					sums[i] = 0
				}
				for (let i = 0; i < this.votes.length; i++) {
					sums[uniqueAns.indexOf(this.votes[i].voteAnswer)]++
				}
				for (let i = 0; i < sums.length; i++) {
					sums[i] = 'Question ' + ansToQ.get(uniqueAns[i]) + ':  ' + (sums[i] / ((this.votes.length / uniqueQs.length)) * 100).toFixed(2) + '%' + ' of respondents voted for answer choice: ' + uniqueAns[i]
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
			axios.get(OC.generateUrl('apps/forms/get/votes/' + this.$route.params.hash))
				.then((response) => {
					if (response.data == null) {
						this.votes = null
						OC.Notification.showTemporary('Access Denied')
					} else {
						this.votes = response.data
					}
					this.loading = false
				}, (error) => {
					/* eslint-disable-next-line no-console */
					console.log(error.response)
					this.loading = false
				})
		},
		viewFormResults(index, event, name) {
			this.$router.push({
				name: name,
				params: {
					hash: event.id,
				},
			})
		},
		download() {

			this.loading = true
			axios.get(OC.generateUrl('apps/forms/get/event/' + this.$route.params.hash))
				.then((response) => {
					this.json2csvParser = ['userId', 'voteOptionId', 'voteOptionText', 'voteAnswer']
					const element = document.createElement('a')
					element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(json2csvParser.parse(this.votes)))
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
