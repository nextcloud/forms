<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="share-div">
		<NcAvatar
			:user="share.shareWith"
			disableMenu
			:displayName="displayName"
			:isNoUser="isNoUser" />
		<div class="share-div__desc">
			<span>{{ displayName }}</span>
			<span>{{ displayNameAppendix }}</span>
		</div>
		<NcActions class="share-div__actions" :disabled="!isCurrentUserOwner">
			<NcActionCaption :name="t('forms', 'Permissions')" />
			<NcActionCheckbox
				:modelValue="canEditForm"
				:disabled="locked"
				@update:modelValue="updatePermissionEdit">
				{{ t('forms', 'Edit form') }}
			</NcActionCheckbox>
			<NcActionCheckbox
				:modelValue="canAccessResults"
				:disabled="locked"
				@update:modelValue="updatePermissionResults">
				{{ t('forms', 'View responses') }}
			</NcActionCheckbox>
			<NcActionCheckbox
				:modelValue="canDeleteResults"
				:disabled="!canAccessResults || locked"
				@update:modelValue="updatePermissionDeleteResults">
				{{ t('forms', 'Delete responses') }}
			</NcActionCheckbox>
			<NcActionSeparator />
			<NcActionButton :disabled="locked" @click="removeShare">
				<template #icon>
					<NcIconSvgWrapper :svg="IconClose" />
				</template>
				{{ t('forms', 'Delete') }}
			</NcActionButton>
		</NcActions>
	</li>
</template>

<script lang="ts">
import IconClose from '@material-symbols/svg-400/outlined/close.svg?raw'
import { t } from '@nextcloud/l10n'
import { computed, defineComponent } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { useShareTypes } from '../../composables/useShareTypes.ts'
import { PERMISSION_TYPES } from '../../models/Permissions.ts'

interface ShareLike {
	id: number
	shareType: number
	shareWith: string
	displayName?: string
	permissions: string[]
}

export default defineComponent({
	components: {
		NcIconSvgWrapper,
		NcActions,
		NcActionButton,
		NcActionCaption,
		NcActionCheckbox,
		NcActionSeparator,
		NcAvatar,
	},

	props: {
		share: {
			type: Object,
			required: true,
		},

		locked: {
			type: Boolean,
			required: true,
		},

		isCurrentUserOwner: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['removeShare', 'update:share'],

	setup(props, { emit }) {
		const { SHARE_TYPES } = useShareTypes()
		const canAccessResults = computed(() =>
			props.share.permissions.includes(PERMISSION_TYPES.PERMISSION_RESULTS),
		)
		const canDeleteResults = computed(() =>
			props.share.permissions.includes(
				PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
			),
		)
		const canEditForm = computed(() =>
			props.share.permissions.includes(PERMISSION_TYPES.PERMISSION_EDIT),
		)
		const isNoUser = computed(
			() => props.share.shareType !== SHARE_TYPES.SHARE_TYPE_USER,
		)
		const displayName = computed(() =>
			props.share.displayName
				? props.share.displayName
				: props.share.shareWith,
		)
		const displayNameAppendix = computed(() => {
			switch (props.share.shareType) {
				case SHARE_TYPES.SHARE_TYPE_GROUP:
					return `(${t('forms', 'Group')})`
				case SHARE_TYPES.SHARE_TYPE_CIRCLE:
					return `(${t('forms', 'Team')})`
				default:
					return ''
			}
		})

		const removeShare = (): void => {
			emit('removeShare', props.share)
		}

		/**
		 * Grant or remove permission from share
		 *
		 * @param permission The permission to grant or remove
		 * @param hasPermission True if granted, False if removed
		 */
		const updatePermission = (
			permission: string,
			hasPermission: boolean,
		): void => {
			const share = { ...(props.share as ShareLike) }
			if (hasPermission) {
				share.permissions = [...new Set([...share.permissions, permission])]
			} else {
				share.permissions = share.permissions.filter(
					(perm: string) => perm !== permission,
				)
			}
			emit('update:share', share)
		}

		/**
		 * @param hasPermission If the results permission should be granted
		 */
		const updatePermissionResults = (hasPermission: boolean): void => {
			if (hasPermission === false) {
				updatePermission(PERMISSION_TYPES.PERMISSION_RESULTS_DELETE, false)
			}
			updatePermission(PERMISSION_TYPES.PERMISSION_RESULTS, hasPermission)
		}

		/**
		 * @param hasPermission If the results_delete permission should be granted
		 */
		const updatePermissionDeleteResults = (hasPermission: boolean): void => {
			updatePermission(
				PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
				hasPermission,
			)
		}

		/**
		 * @param hasPermission If the results_delete permission should be granted
		 */
		const updatePermissionEdit = (hasPermission: boolean): void => {
			updatePermission(PERMISSION_TYPES.PERMISSION_EDIT, hasPermission)
		}

		return {
			t,
			IconClose,
			SHARE_TYPES,
			canAccessResults,
			canDeleteResults,
			canEditForm,
			isNoUser,
			displayName,
			displayNameAppendix,
			removeShare,
			updatePermissionResults,
			updatePermissionDeleteResults,
			updatePermissionEdit,
		}
	},
})
</script>

<style lang="scss" scoped>
.share-div {
	display: flex;
	height: var(--default-clickable-area);
	align-items: center;

	&__desc {
		padding: 8px;
		flex-grow: 1;
	}
}
</style>
