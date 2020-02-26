<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  - @author Natalie Gilbert
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
		<Controls>
			<router-link :to="{ name: 'create'}" class="button">
				<span class="symbol icon-add" />
				<span class="hidden-visually">
					{{ t('forms', 'New') }}
				</span>
			</router-link>
		</Controls>
		<div v-if="noForms" class="">
			<div class="icon-forms" />
			<h2> {{ t('No existing forms.') }} </h2>
			<router-link :to="{ name: 'create'}" class="button new">
				<span>{{ t('forms', 'Click here to add a form') }}</span>
			</router-link>
		</div>
		<transition-group
			v-if="!noForms"
			name="list"
			tag="div"
			class="table">
			<FormListItem
				key="0"
				:header="true" />
			<li
				is="form-list-item"
				v-for="(form, index) in forms"
				:key="form.id"
				:form="form"
				@deleteForm="removeForm(index, form.event)"
				@viewResults="viewFormResults(index, form.event, 'results')" />
		</transition-group>
		<LoadingOverlay v-if="loading" />
		<modal-dialog />
	</div>
</template>

<script>

import FormListItem from '../components/formListItem'
import Controls from '../components/_base-Controls'
import axios from '@nextcloud/axios'
import LoadingOverlay from '../components/_base-LoadingOverlay'

export default {
	name: 'List',

	components: {
		Controls,
		FormListItem,
		LoadingOverlay,
	},

	data() {
		return {
			noForms: false,
			loading: true,
			forms: [],
		}
	},

	created() {
		this.indexPage = OC.generateUrl('apps/forms/')
		this.loadForms()
	},

	methods: {
		loadForms() {
			this.loading = true
			axios.get(OC.generateUrl('apps/forms/get/forms'))
				.then((response) => {
					this.forms = response.data
					this.loading = false
				}, (error) => {
					/* eslint-disable-next-line no-console */
					console.log(error.response)
					this.loading = false
				})
		},
		helpPage() {
			window.open('https://github.com/affan98/forms/blob/master/Forms_Support.md')
		},
		viewFormResults(index, event, name) {
			this.$router.push({
				name: name,
				params: {
					hash: event.id,
				},
			})
		},
		removeForm(index, event) {
			const params = {
				title: t('forms', 'Delete form'),
				text: t('forms', 'Do you want to delete "%n"?', 1, event.title),
				buttonHideText: t('forms', 'No, keep form.'),
				buttonConfirmText: t('forms', 'Yes, delete form.'),
				onConfirm: () => {
					// this.deleteForm(index, event)
					axios.delete(OC.generateUrl('apps/forms/forms/{id}', { id: event.id }))
						.then((response) => {
							this.forms.splice(index, 1)
							OC.Notification.showTemporary(t('forms', 'Form "%n" deleted', 1, event.title))
						}, (error) => {
							OC.Notification.showTemporary(t('forms', 'Error while deleting Form "%n"', 1, event.title))
							/* eslint-disable-next-line no-console */
							console.log(error.response)
						}
						)
				},
			}
			this.$modal.show(params)
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
