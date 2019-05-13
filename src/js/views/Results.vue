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
	<div id="app-content">
		<div>
			<button class="button btn primary" @click="download">
				<span>{{ "Export to CSV" }}</span>
			</button>
		</div>
		<transition-group
			name="list"
			tag="div"
			class="table"
		>
			<resultItem
				key="0"
				:header="true"
			/>
			<li
				is="resultItem"
				v-for="(vote, index) in votes"
				:key="vote.id"
				:vote="vote"
				@viewResults="viewFormResults(index, form.event, 'results')"
			/>
		</transition-group>
		<loading-overlay v-if="loading" />
		<modal-dialog />
	</div>
</template>

<script>
// import moment from 'moment'
// import lodash from 'lodash'
import resultItem from '../components/resultItem'
import json2csvParser from 'json2csv'

export default {
	name: 'Results',

	components: {
		resultItem
	},

	data() {
		return {
			loading: true,
			votes: []
		}
	},

	created() {
		this.indexPage = OC.generateUrl('apps/forms/')
		this.loadForms()
	},

	methods: {
		loadForms() {
			this.loading = true
			this.$http.get(OC.generateUrl('apps/forms/get/votes/' + this.$route.params.hash))
				.then((response) => {
					this.votes = response.data
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
					hash: event.id
				}
			})
		},
		download() {
			this.json2csvParser = ['userId', 'voteOptionId', 'voteOptionText', 'voteAnswer']
			var element = document.createElement('a')
			element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(json2csvParser.parse(this.votes)))
			element.setAttribute('download', 'NextCloud Forms CSV' + '.csv')

			element.style.display = 'none'
			document.body.appendChild(element)

			element.click()
			document.body.removeChild(element)
		}
	}
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
