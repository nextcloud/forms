<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		content-classes="archived-forms"
		:name="t('forms', 'Archived forms')"
		:open="open"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<ul :aria-label="t('forms', 'Archived forms')">
			<AppNavigationForm
				v-for="(form, key) in shownForms"
				:key="key"
				:form="form"
				force-display-actions
				@clone="onCloneForm(form.id)"
				@delete="onDelete(form)"
				@mobile-close-navigation="$emit('update:open', false)" />
		</ul>
	</NcDialog>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
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

	emits: ['update:open', 'clone'],

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

		onCloneForm(formId) {
			this.$emit('clone', formId)
			this.$emit('update:open', false)
		},

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
