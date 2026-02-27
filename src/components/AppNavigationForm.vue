<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem
		:active="isActive"
		:actions-aria-label="t('forms', 'Form actions')"
		:counter-number="form.submissionCount"
		compact
		:force-display-actions="forceDisplayActions"
		:name="formTitle"
		:to="{
			name: routerTarget,
			params: { hash: form.hash },
		}"
		@click="mobileCloseNavigation">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="16" />
			<IconCheck v-else-if="isExpired" :size="16" />
			<FormsIcon v-else :size="16" />
		</template>
		<template v-if="hasSubtitle" #subname>
			{{ formSubtitle }}
		</template>
		<template
			v-if="!loading && (!readOnly || canEdit || canSeeResults)"
			#actions>
			<NcActionRouter
				v-if="!isArchived && canEdit"
				close-after-click
				:disabled="isFormLocked"
				exact
				:to="{ name: 'edit', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				<template #icon>
					<IconPencil :size="20" />
				</template>
				{{ t('forms', 'Edit form') }}
			</NcActionRouter>
			<NcActionButton
				v-if="!isArchived && !readOnly"
				close-after-click
				@click="onShareForm">
				<template #icon>
					<IconShareVariant :size="20" />
				</template>
				{{ t('forms', 'Share form') }}
			</NcActionButton>
			<NcActionRouter
				v-if="canSeeResults"
				close-after-click
				exact
				:to="{ name: 'results', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				<template #icon>
					<IconPoll :size="20" />
				</template>
				{{ t('forms', 'Results') }}
			</NcActionRouter>
			<NcActionButton v-if="canEdit" close-after-click @click="onCloneForm">
				<template #icon>
					<IconContentCopy :size="20" />
				</template>
				{{ t('forms', 'Copy form') }}
			</NcActionButton>
			<NcActionSeparator v-if="canEdit && !readOnly" />
			<NcActionButton
				v-if="canEdit && !readOnly"
				close-after-click
				:disabled="isFormLocked"
				@click="onToggleArchive">
				<template #icon>
					<IconArchiveOff v-if="isArchived" :size="20" />
					<IconArchive v-else :size="20" />
				</template>
				{{
					isArchived
						? t('forms', 'Unarchive form')
						: t('forms', 'Archive form')
				}}
			</NcActionButton>
			<NcActionButton
				v-if="canEdit && !readOnly"
				close-after-click
				:disabled="isFormLocked"
				@click="onConfirmDelete">
				<template #icon>
					<IconDelete :size="20" />
				</template>
				{{ t('forms', 'Delete form') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { DialogSeverity, getDialogBuilder, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRouter from '@nextcloud/vue/components/NcActionRouter'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconArchiveOff from 'vue-material-design-icons/ArchiveOffOutline.vue'
import IconArchive from 'vue-material-design-icons/ArchiveOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconPencil from 'vue-material-design-icons/PencilOutline.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariantOutline.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'
import FormsIcon from './Icons/FormsIcon.vue'
import PermissionTypes from '../mixins/PermissionTypes.js'
import { FormState } from '../models/Constants.ts'
import logger from '../utils/Logger.js'

export default {
	name: 'AppNavigationForm',

	components: {
		FormsIcon,
		IconArchive,
		IconArchiveOff,
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

	mixins: [PermissionTypes],

	props: {
		form: {
			type: Object,
			required: true,
		},

		forceDisplayActions: {
			type: Boolean,
			default: false,
			required: false,
		},

		readOnly: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'mobile-close-navigation',
		'open-sharing',
		'clone',
		'update-form-state',
		'delete',
	],

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		canEdit() {
			return this.form.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
			)
		},

		canSeeResults() {
			return (
				this.form.permissions.includes(
					this.PERMISSION_TYPES.PERMISSION_RESULTS,
				) || this.form.submissionCount > 0
			)
		},

		/**
		 * Check if form is current form and set active
		 */
		isActive() {
			return this.form.hash === this.$route.params.hash
		},

		/**
		 * Check if the form is archived
		 */
		isArchived() {
			return this.form.state === FormState.FormArchived
		},

		/**
		 * Check if form is expired
		 */
		isExpired() {
			return this.form.expires && moment().unix() > this.form.expires
		},

		/**
		 * Check if form is locked
		 */
		isFormLocked() {
			return (
				this.form.lockedUntil === 0
				|| (this.form.lockedUntil > moment().unix()
					&& this.form.lockedBy !== getCurrentUser().uid)
			)
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
			if (this.form.state === FormState.FormClosed) {
				// TRANSLATORS: The form was closed manually so it does not take new submissions
				return t('forms', 'Form closed')
			}
			if (this.form.expires) {
				const relativeDate = moment(this.form.expires, 'X').fromNow()
				if (this.isExpired) {
					return t('forms', 'Expired {relativeDate}', {
						relativeDate,
					})
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

		async onConfirmDelete() {
			const dialog = getDialogBuilder(t('forms', 'Delete form'))
				.setText(
					t('forms', 'Are you sure you want to delete {title}?', {
						title: this.formTitle,
					}),
				)
				.setSeverity(DialogSeverity.Error)
				.setButtons([
					{
						label: t('forms', 'Cancel'),
						callback: () => {},
						variant: 'secondary',
					},
					{
						label: t('forms', 'Delete form'),
						callback: () => {
							this.onDeleteForm()
						},
						variant: 'error',
					},
				])
				.build()

			await dialog.show()
		},

		async onToggleArchive() {
			try {
				// TODO: add loading status feedback ?
				await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: this.form.id,
					}),
					{
						keyValuePairs: {
							state: this.isArchived
								? FormState.FormClosed
								: FormState.FormArchived,
						},
					},
				)
				this.$set(
					this.form,
					'state',
					this.isArchived ? FormState.FormClosed : FormState.FormArchived,
				)
			} catch (error) {
				logger.error('Error changing archived state of form', {
					error,
				})
				showError(t('forms', 'Error changing archived state of form'))
			}
		},

		async onDeleteForm() {
			this.loading = true
			try {
				await axios.delete(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: this.form.id,
					}),
				)
				this.$emit('delete', this.form.id)
			} catch (error) {
				logger.error(`Error while deleting ${this.formTitle}`, {
					error: error.response,
				})
				showError(
					t('forms', 'Error while deleting {title}', {
						title: this.formTitle,
					}),
				)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
