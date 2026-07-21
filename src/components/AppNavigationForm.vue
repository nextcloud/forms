<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem
		:active="isActive"
		:actions-aria-label="t('forms', 'Form actions')"
		:counterNumber="form.submissionCount"
		compact
		forceMenu
		:forceDisplayActions="forceDisplayActions"
		:name="formTitle"
		:to="{
			name: routerTarget,
			params: { hash: form.hash },
		}"
		@click="mobileCloseNavigation">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="16" />
			<NcIconSvgWrapper v-else-if="isExpired" :svg="IconCheck" :size="16" />
			<NcIconSvgWrapper v-else :svg="FormsIcon" :size="16" />
		</template>
		<template v-if="hasSubtitle" #subname>
			{{ formSubtitle }}
		</template>
		<template
			v-if="!loading && (!readOnly || canEdit || canSeeResults)"
			#actions>
			<NcActionRouter
				v-if="!isArchived && canEdit"
				closeAfterClick
				:disabled="isFormLocked"
				:to="{ name: 'edit', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				<template #icon>
					<NcIconSvgWrapper :svg="IconPencil" />
				</template>
				{{ t('forms', 'Edit form') }}
			</NcActionRouter>
			<NcActionButton
				v-if="!isArchived && !readOnly"
				closeAfterClick
				@click="onShareForm">
				<template #icon>
					<NcIconSvgWrapper :svg="IconShareVariant" />
				</template>
				{{ t('forms', 'Share form') }}
			</NcActionButton>
			<NcActionRouter
				v-if="canSeeResults"
				closeAfterClick
				:to="{ name: 'results', params: { hash: form.hash } }"
				@click="mobileCloseNavigation">
				<template #icon>
					<NcIconSvgWrapper :svg="IconPoll" />
				</template>
				{{ t('forms', 'Responses') }}
			</NcActionRouter>
			<NcActionButton v-if="canEdit" closeAfterClick @click="onCloneForm">
				<template #icon>
					<NcIconSvgWrapper :svg="IconContentCopy" />
				</template>
				{{ t('forms', 'Copy form') }}
			</NcActionButton>
			<NcActionSeparator v-if="canEdit && !readOnly" />
			<NcActionButton
				v-if="canEdit && !readOnly"
				closeAfterClick
				:disabled="isFormLocked"
				@click="onToggleArchive">
				<template #icon>
					<NcIconSvgWrapper
						v-if="isArchived"
						:svg="IconArchiveOff"
						:size="20" />
					<NcIconSvgWrapper v-else :svg="IconArchive" :size="20" />
				</template>
				{{
					isArchived
						? t('forms', 'Unarchive form')
						: t('forms', 'Archive form')
				}}
			</NcActionButton>
			<NcActionButton
				v-if="canEdit && !readOnly"
				closeAfterClick
				:disabled="isFormLocked"
				@click="onConfirmDelete">
				<template #icon>
					<NcIconSvgWrapper :svg="IconDelete" />
				</template>
				{{ t('forms', 'Delete form') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import IconArchive from '@material-symbols/svg-400/outlined/archive.svg?raw'
import IconPoll from '@material-symbols/svg-400/outlined/bar_chart.svg?raw'
import IconCheck from '@material-symbols/svg-400/outlined/check.svg?raw'
import IconContentCopy from '@material-symbols/svg-400/outlined/content_copy.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconPencil from '@material-symbols/svg-400/outlined/edit.svg?raw'
import IconShareVariant from '@material-symbols/svg-400/outlined/share.svg?raw'
import IconArchiveOff from '@material-symbols/svg-400/outlined/unarchive.svg?raw'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showConfirmation, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRouter from '@nextcloud/vue/components/NcActionRouter'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import FormsIcon from '../../img/forms-dark.svg?raw'
import PermissionTypes from '../mixins/PermissionTypes.ts'
import { FormState } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'

export default {
	name: 'AppNavigationForm',

	components: {
		NcActionButton,
		NcActionRouter,
		NcActionSeparator,
		NcIconSvgWrapper,
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

	emits: ['mobileCloseNavigation', 'openSharing', 'clone', 'delete'],

	setup() {
		return {
			FormsIcon,
			IconArchive,
			IconArchiveOff,
			IconCheck,
			IconContentCopy,
			IconDelete,
			IconPencil,
			IconPoll,
			IconShareVariant,
		}
	},

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
				const relativeDate = moment(this.form.expires, 'X')
					.locale(window.OC.getLanguage())
					.fromNow()
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
			this.$emit('mobileCloseNavigation')
		},

		onShareForm() {
			this.$emit('openSharing', this.form.hash)
		},

		onCloneForm() {
			this.$emit('clone', this.form.id)
		},

		async onConfirmDelete() {
			const shouldDelete = await showConfirmation({
				name: t('forms', 'Delete form'),
				text: t('forms', 'Are you sure you want to delete {title}?', {
					title: this.formTitle,
				}),
				labelConfirm: t('forms', 'Delete form'),
				labelReject: t('forms', 'Cancel'),
			})

			if (shouldDelete) {
				await this.onDeleteForm()
			}
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
				// eslint-disable-next-line vue/no-mutating-props
				this.form.state = this.isArchived
					? FormState.FormClosed
					: FormState.FormArchived
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
