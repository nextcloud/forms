<!--
  - @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
  -
  - @author Ferdinand Thiessen <opensource@fthiessen.de>
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
	<NcDialog content-classes="archived-forms"
		:name="t('forms', 'Archived forms')"
		:open="open"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<ul :aria-label="t('forms', 'Archived forms')">
			<AppNavigationForm v-for="form, key in shownForms"
				:key="key"
				:form="form"
				:read-only="false"
				force-display-actions
				@delete="onDelete(form)"
				@mobile-close-navigation="$emit('update:open', false)" />
		</ul>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'

import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import AppNavigationForm from './AppNavigationForm.vue'

export default defineComponent({
	name: 'ArchivedFormsModal',

	components: {
		AppNavigationForm,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
		forms: {
			type: Array,
			required: true,
		},
	},

	emits: ['update:open'],

	data() {
		return {
			shownForms: [],
		}
	},

	watch: {
		forms: {
			immediate: true,
			handler() {
				this.shownForms = [...this.forms]
			},
		},
	},

	methods: {
		t,

		onDelete(form) {
			this.shownForms = this.shownForms.filter(({ id }) => id !== form.id)
		},
	},
})
</script>

<style scoped>
:deep(.archived-forms) {
	min-height: 50vh !important;
	padding-block-end: 22px;
}
</style>
