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

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsForm } from '../types/Entities.d.ts'

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
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, defineComponent, ref } from 'vue'
import { useRoute } from 'vue-router'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionRouter from '@nextcloud/vue/components/NcActionRouter'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import FormsIcon from '../../img/forms-dark.svg?raw'
import { FormState } from '../models/Constants.ts'
import { PERMISSION_TYPES } from '../models/Permissions.ts'
import logger from '../utils/Logger.ts'

type NavigationTarget = 'submit' | 'formRoot'

export default defineComponent({
	name: 'AppNavigationForm',

	components: {
		NcActionButton,
		NcActionRouter,
		NcActionSeparator,
		NcIconSvgWrapper,
		NcListItem,
		NcLoadingIcon,
	},

	props: {
		form: {
			type: Object as PropType<FormsForm>,
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

	setup(props, { emit }) {
		const route = useRoute()
		const loading = ref(false)

		const canEdit = computed(() => {
			return props.form.permissions.includes(PERMISSION_TYPES.PERMISSION_EDIT)
		})
		const canSeeResults = computed(
			() =>
				props.form.permissions.includes(PERMISSION_TYPES.PERMISSION_RESULTS)
				|| (props.form.submissionCount ?? 0) > 0,
		)

		/**
		 * Check if form is current form and set active
		 */
		const isActive = computed(
			() => props.form.hash === String(route.params.hash),
		)

		/**
		 * Check if the form is archived
		 */
		const isArchived = computed(
			() => props.form.state === FormState.FormArchived,
		)

		/**
		 * Check if form is expired
		 */
		const isExpired = computed(() =>
			Boolean(props.form.expires && moment().unix() > props.form.expires),
		)

		/**
		 * Check if form is locked
		 */
		const isFormLocked = computed(() => {
			const currentUserUid = getCurrentUser()?.uid ?? ''
			const lockedUntil = props.form.lockedUntil ?? -1
			return (
				lockedUntil === 0
				|| (lockedUntil > moment().unix()
					&& props.form.lockedBy !== currentUserUid)
			)
		})

		/**
		 * Return form title, or placeholder if not set
		 *
		 * @return
		 */
		const formTitle = computed(() => {
			if (props.form.title) {
				return props.form.title
			}
			return t('forms', 'New form')
		})

		/**
		 * Return expiration details for subtitle
		 */
		const formSubtitle = computed(() => {
			if (props.form.state === FormState.FormClosed) {
				return t('forms', 'Form closed')
			}
			if (props.form.expires) {
				const relativeDate = moment(props.form.expires, 'X')
					.locale(window.OC?.getLanguage())
					.fromNow()
				if (isExpired.value) {
					return t('forms', 'Expired {relativeDate}', {
						relativeDate,
					})
				}
				return t('forms', 'Expires {relativeDate}', { relativeDate })
			}
			return ''
		})

		/**
		 * Return, if form has Subtitle
		 */
		const hasSubtitle = computed(() => formSubtitle.value !== '')

		/**
		 * Route to use, depending on readOnly
		 *
		 * @return Route to 'submit' or 'formRoot'
		 */
		const routerTarget = computed<NavigationTarget>(() => {
			if (props.readOnly) {
				return 'submit'
			}

			return 'formRoot'
		})

		/**
		 * Closes the App-Navigation on mobile-devices
		 */
		const mobileCloseNavigation = (): void => {
			emit('mobileCloseNavigation')
		}

		const onShareForm = (): void => {
			emit('openSharing', props.form.hash)
		}

		const onCloneForm = (): void => {
			emit('clone', props.form.id)
		}

		const onDeleteForm = async (): Promise<void> => {
			loading.value = true
			try {
				await axios.delete(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: props.form.id,
					}),
				)
				emit('delete', props.form.id)
			} catch (error) {
				const response = (error as { response?: unknown }).response
				logger.error(`Error while deleting ${formTitle.value}`, {
					error: response,
				})
				showError(
					t('forms', 'Error while deleting {title}', {
						title: formTitle.value,
					}),
				)
			} finally {
				loading.value = false
			}
		}

		const onConfirmDelete = async (): Promise<void> => {
			const shouldDelete = await showConfirmation({
				name: t('forms', 'Delete form'),
				text: t('forms', 'Are you sure you want to delete {title}?', {
					title: formTitle.value,
				}),
				labelConfirm: t('forms', 'Delete form'),
				labelReject: t('forms', 'Cancel'),
			})

			if (shouldDelete) {
				await onDeleteForm()
			}
		}

		const onToggleArchive = async (): Promise<void> => {
			try {
				// TODO: add loading status feedback ?
				await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}', {
						id: props.form.id,
					}),
					{
						keyValuePairs: {
							state: isArchived.value
								? FormState.FormClosed
								: FormState.FormArchived,
						},
					},
				)

				;(props.form as FormsForm).state = isArchived.value
					? FormState.FormClosed
					: FormState.FormArchived
			} catch (error) {
				logger.error('Error changing archived state of form', {
					error,
				})
				showError(t('forms', 'Error changing archived state of form'))
			}
		}

		return {
			t,
			FormsIcon,
			IconArchive,
			IconArchiveOff,
			IconCheck,
			IconContentCopy,
			IconDelete,
			IconPencil,
			IconPoll,
			IconShareVariant,
			loading,
			canEdit,
			canSeeResults,
			isActive,
			isArchived,
			isExpired,
			isFormLocked,
			formTitle,
			formSubtitle,
			hasSubtitle,
			routerTarget,
			mobileCloseNavigation,
			onShareForm,
			onCloneForm,
			onConfirmDelete,
			onToggleArchive,
			onDeleteForm,
		}
	},
})
</script>
