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
import { defineComponent } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import PermissionTypes from '../../mixins/PermissionTypes.ts'
import ShareTypes from '../../mixins/ShareTypes.ts'

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

	mixins: [PermissionTypes, ShareTypes],

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

	setup() {
		return {
			t,
			IconClose,
		}
	},

	computed: {
		canAccessResults(): boolean {
			return this.share.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS,
			)
		},

		canDeleteResults(): boolean {
			return this.share.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
			)
		},

		canEditForm(): boolean {
			return this.share.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
			)
		},

		isNoUser(): boolean {
			return this.share.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER
		},

		displayName(): string {
			return !this.share.displayName
				? this.share.shareWith
				: this.share.displayName
		},

		displayNameAppendix(): string {
			switch (this.share.shareType) {
				case this.SHARE_TYPES.SHARE_TYPE_GROUP:
					return `(${t('forms', 'Group')})`
				case this.SHARE_TYPES.SHARE_TYPE_CIRCLE:
					return `(${t('forms', 'Team')})`
				default:
					return ''
			}
		},
	},

	methods: {
		removeShare(): void {
			this.$emit('removeShare', this.share)
		},

		/**
		 * @param hasPermission If the results permission should be granted
		 */
		updatePermissionResults(hasPermission: boolean): void {
			if (hasPermission === false) {
				// ensure to remove the delete permission if results permission is dropped
				this.updatePermission(
					this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
					false,
				)
			}
			return this.updatePermission(
				this.PERMISSION_TYPES.PERMISSION_RESULTS,
				hasPermission,
			)
		},

		/**
		 * @param hasPermission If the results_delete permission should be granted
		 */
		updatePermissionDeleteResults(hasPermission: boolean): void {
			return this.updatePermission(
				this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
				hasPermission,
			)
		},

		/**
		 * @param hasPermission If the results_delete permission should be granted
		 */
		updatePermissionEdit(hasPermission: boolean): void {
			return this.updatePermission(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
				hasPermission,
			)
		},

		/**
		 * Grant or remove permission from share
		 *
		 * @param permission The permission to grant or remove
		 * @param hasPermission True if granted, False if removed
		 */
		updatePermission(permission: string, hasPermission: boolean): void {
			const share = { ...(this.share as ShareLike) }
			if (hasPermission) {
				share.permissions = [...new Set([...share.permissions, permission])]
			} else {
				share.permissions = share.permissions.filter(
					(perm: string) => perm !== permission,
				)
			}
			this.$emit('update:share', share)
		},
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
