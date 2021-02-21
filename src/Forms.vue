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
			<template #list>
				<AppNavigationForm v-for="form in forms"
					:key="form.id"
					:form="form"
					@mobile-close-navigation="mobileCloseNavigation"
					@delete="onDeleteForm" />
			</template>
		</AppNavigation>

		<!-- No forms & loading emptycontents -->
		<AppContent v-if="loading || noForms || (!routeHash && $route.name !== 'create')">
			<EmptyContent v-if="loading" icon="icon-loading">
				{{ t('forms', 'Loading forms …') }}
			</EmptyContent>
			<EmptyContent v-else-if="noForms">
				{{ t('forms', 'No forms created yet') }}
				<template #action>
					<button class="primary" @click="onNewForm">
						{{ t('forms', 'Create a form') }}
					</button>
				</template>
			</EmptyContent>

			<EmptyContent v-else>
				{{ t('forms', 'Select a form or create a new one') }}
				<template #action>
					<button class="primary" @click="onNewForm">
						{{ t('forms', 'Create new form') }}
					</button>
				</template>
			</EmptyContent>
		</AppContent>

		<!-- No errors show router content -->
		<template v-else>
			<router-view
				:form.sync="selectedForm"
				:sidebar-opened.sync="sidebarOpened" />
			<router-view v-if="!selectedForm.partial"
				:form="selectedForm"
				:opened.sync="sidebarOpened"
				name="sidebar" />
		</template>
	</Content>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import Content from '@nextcloud/vue/dist/Components/Content'
import isMobile from '@nextcloud/vue/src/mixins/isMobile'

import AppNavigationForm from './components/AppNavigationForm'
import EmptyContent from './components/EmptyContent'
import OcsResponse2Data from './utils/OcsResponse2Data'

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

	mixins: [isMobile],

	data() {
		return {
			loading: true,
			sidebarOpened: true,
			forms: [],
		}
	},

	computed: {
		noForms() {
			return this.forms && this.forms.length === 0
		},

		routeHash() {
			return this.$route.params.hash
		},

		selectedForm: {
			get() {
				return this.forms.find(form => form.hash === this.routeHash)
			},
			set(form) {
				const index = this.forms.findIndex(search => search.hash === this.routeHash)
				if (index > -1) {
					this.$set(this.forms, index, form)
				}
			},
		},
	},

	beforeMount() {
		this.loadForms()
	},

	methods: {
		/**
		 * Closes the App-Navigation on mobile-devices
		 */
		mobileCloseNavigation() {
			if (this.isMobile) {
				emit('toggle-navigation', { open: false })
			}
		},

		/**
		 * Initial forms load
		 */
		async loadForms() {
			this.loading = true
			try {
				const response = await axios.get(generateOcsUrl('apps/forms/api/v1', 2) + 'forms')
				this.forms = OcsResponse2Data(response)
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v1', 2) + 'form')
				const newForm = OcsResponse2Data(response)
				this.forms.unshift(newForm)
				this.$router.push({ name: 'edit', params: { hash: newForm.hash } })
				this.mobileCloseNavigation()
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
			const deletedHash = this.forms[formIndex].hash

			this.forms.splice(formIndex, 1)

			// Redirect if current form has been deleted
			if (deletedHash === this.routeHash) {
				this.$router.push({ name: 'root' })
			}
		},
	},
}
</script>
