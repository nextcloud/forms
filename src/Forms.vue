<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<Content app-name="forms">
		<AppNavigation>
			<AppNavigationNew button-class="icon-add" :text="t('forms', 'New form')" @click="onNewForm" />
			<AppNavigationForm v-for="form in formattedForms"
				:key="form.id"
				:form="form"
				@delete="onDeleteForm" />
		</AppNavigation>

		<!-- No forms & loading emptycontents -->
		<AppContent v-if="loading || noForms || (!hash && $route.name !== 'create')">
			<EmptyContent v-if="loading" icon="icon-loading">
				{{ t('forms', 'Loading forms …') }}
			</EmptyContent>
			<EmptyContent v-else-if="noForms">
				{{ t('forms', 'No forms in here') }}
				<template #desc>
					<button class="primary" @click="onNewForm">
						{{ t('forms', 'Create a new one') }}
					</button>
				</template>
			</EmptyContent>

			<EmptyContent v-else>
				{{ t('forms', 'Please select a form') }}
			</EmptyContent>
		</AppContent>

		<!-- No errors show router content -->
		<template v-else>
			<router-view :form="selectedForm" />
			<router-view :form="selectedForm" name="sidebar" />
		</template>
	</Content>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import Content from '@nextcloud/vue/dist/Components/Content'

import AppNavigationForm from './components/AppNavigationForm'
import EmptyContent from './components/EmptyContent'

import { formatForm } from './utils/FormsUtils'

export default {
	name: 'Forms',

	components: {
		AppNavigationForm,
		AppContent,
		AppNavigation,
		AppNavigationNew,
		Content,
		EmptyContent,
	},

	data() {
		return {
			loading: true,
			forms: [],
		}
	},

	computed: {
		noForms() {
			return this.forms && this.forms.length === 0
		},

		formattedForms() {
			return this.forms.map(formatForm)
		},

		hash() {
			return this.$route.params.hash
		},

		selectedForm() {
			// TODO: replace with form.hash
			return this.forms.find(form => form.form.hash === this.hash)
		},
	},

	beforeMount() {
		this.loadForms()
	},

	methods: {
		/**
		 * Initial forms load
		 */
		async loadForms() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl('apps/forms/get/forms'))
				this.forms = response.data
			} catch (error) {
				showError(t('forms', 'An error occurred while loading the forms list'))
				console.error(error)
			} finally {
				this.loading = false
			}
		},

		/**
		 *
		 */
		async onNewForm() {
			try {
				// Request a new empty form
				const response = await axios.post(generateUrl('/apps/forms/api/v1/form'))
				const newForm = response.data
				this.forms.push(newForm)
				this.$router.push({ name: 'edit', params: { hash: newForm.form.hash } })
			} catch (error) {
				showError(t('forms', 'Unable to create a new form'))
				console.error(error)
			}
		},

		/**
		 * Remove form from forms list after successful server deletion request
		 *
		 * @param {Number} id the form id
		 */
		async onDeleteForm(id) {
			const formIndex = this.forms.findIndex(form => form.id === id)
			this.forms.splice(formIndex, 1)
		},
	},
}
</script>
