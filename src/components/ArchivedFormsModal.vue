<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		contentClasses="archived-forms"
		:name="t('forms', 'Archived forms')"
		:open="open"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<ul :aria-label="t('forms', 'Archived forms')">
			<AppNavigationForm
				v-for="(form, key) in shownForms"
				:key="key"
				:form="form"
				forceDisplayActions
				@clone="onCloneForm(form.id)"
				@delete="onDelete(form)"
				@mobileCloseNavigation="$emit('update:open', false)" />
		</ul>
	</NcDialog>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsForm } from '../types/Entities.d.ts'

import { t } from '@nextcloud/l10n'
import { defineComponent, ref, watch } from 'vue'
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
			type: Array as PropType<FormsForm[]>,
			required: true,
		},
	},

	emits: ['update:open', 'clone'],

	setup(props, { emit }) {
		const shownForms = ref<FormsForm[]>([])

		watch(
			() => props.forms,
			() => {
				shownForms.value = [...props.forms]
			},
			{ immediate: true },
		)

		const onCloneForm = (formId: number): void => {
			emit('clone', formId)
			emit('update:open', false)
		}

		const onDelete = (form: FormsForm): void => {
			shownForms.value = shownForms.value.filter(({ id }) => id !== form.id)
		}

		return {
			shownForms,
			onCloneForm,
			onDelete,
			t,
		}
	},
})
</script>

<style scoped>
:deep(.archived-forms) {
	min-height: 50vh !important;
	padding-block-end: 22px;
}
</style>
