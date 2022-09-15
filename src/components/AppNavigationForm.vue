<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<NcListItem ref="navigationItem"
		:title="formTitle"
		:to="{
			name: routerTarget,
			params: { hash: form.hash }
		}"
		:counter-number="form.submissionCount"
		:active="isActive"
		:compact="true"
		@click="mobileCloseNavigation">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="16" />
			<IconCheck v-else-if="isExpired" :size="16" />
			<FormsIcon v-else :size="16" />
		</template>
		<template v-if="hasSubtitle" #subtitle>
			{{ formSubtitle }}
		</template>
		<template v-if="!loading && !readOnly" #actions>
			<NcActionRouter :close-after-click="true"
				:exact="true"
				:to="{ name: 'edit', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				<template #icon>
					<IconPencil :size="20" />
				</template>
				{{ t('forms', 'Edit form') }}
			</NcActionRouter>
			<NcActionButton :close-after-click="true" @click="onShareForm">
				<template #icon>
					<IconShareVariant :size="20" />
				</template>
				{{ t('forms', 'Share form') }}
			</NcActionButton>
			<NcActionRouter :close-after-click="true"
				:exact="true"
				:to="{ name: 'results', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				<template #icon>
					<IconPoll :size="20" />
				</template>
				{{ t('forms', 'Results') }}
			</NcActionRouter>
			<NcActionButton :close-after-click="true" @click="onCloneForm">
				<template #icon>
					<IconContentCopy :size="20" />
				</template>
				{{ t('forms', 'Copy form') }}
			</NcActionButton>
			<NcActionSeparator />
			<NcActionButton :close-after-click="true" @click="onDeleteForm">
				<template #icon>
					<IconDelete :size="20" />
				</template>
				{{ t('forms', 'Delete form') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton'
import NcActionRouter from '@nextcloud/vue/dist/Components/NcActionRouter'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon'
import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import IconCheck from 'vue-material-design-icons/Check'
import IconContentCopy from 'vue-material-design-icons/ContentCopy'
import IconDelete from 'vue-material-design-icons/Delete'
import IconPencil from 'vue-material-design-icons/Pencil'
import IconPoll from 'vue-material-design-icons/Poll'
import IconShareVariant from 'vue-material-design-icons/ShareVariant'

import FormsIcon from './Icons/FormsIcon.vue'

import logger from '../utils/Logger.js'

export default {
	name: 'AppNavigationForm',

	components: {
		FormsIcon,
		IconCheck,
		IconContentCopy,
		IconDelete,
		IconPencil,
		IconPoll,
		IconShareVariant,
		NcActionButton,
		NcActionRouter,
		NcActionSeparator,
		NcListItem,
		NcLoadingIcon,
	},

	props: {
		form: {
			type: Object,
			required: true,
		},
		readOnly: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		/**
		 * Check if form is current form and set active
		 */
		isActive() {
			return this.form.hash === this.$route.params.hash
		},

		/**
		 * Check if form is expired
		 */
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
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
		 * Return expiration details for subtitle
		 */
		formSubtitle() {
			if (this.form.expires) {
				const relativeDate = moment(this.form.expires, 'X').fromNow()
				if (this.isExpired) {
					return t('forms', 'Expired {relativeDate}', { relativeDate })
				}
				return t('forms', 'Expires {relativeDate}', { relativeDate })
			}
			return ''
		},

		/**
		 * Return, if form has Subtitle
		 */
		hasSubtitle() {
			return this.formSubtitle !== ''
		},

		/**
		 * Route to use, depending on readOnly
		 *
		 * @return {string} Route to 'submit' or 'formRoot'
		 */
		routerTarget() {
			if (this.readOnly) {
				return 'submit'
			}

			return 'formRoot'
		},
	},

	methods: {
		/**
		 * Closes the App-Navigation on mobile-devices
		 */
		mobileCloseNavigation() {
			this.$emit('mobile-close-navigation')
		},

		onShareForm() {
			this.$emit('open-sharing', this.form.hash)
		},
		onCloneForm() {
			this.$emit('clone', this.form.id)
		},

		async onDeleteForm() {
			if (!confirm(t('forms', 'Are you sure you want to delete {title}?', { title: this.formTitle }))) {
				return
			}

			// All good, let's delete
			this.loading = true
			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v2/form/{id}', { id: this.form.id }))
				this.$emit('delete', this.form.id)
			} catch (error) {
				logger.error(`Error while deleting ${this.formTitle}`, { error: error.response })
				showError(t('forms', 'Error while deleting {title}', { title: this.formTitle }))
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
