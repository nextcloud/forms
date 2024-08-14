<!--
  - @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<li class="share-div">
		<NcAvatar
			:user="share.shareWith"
			:disable-menu="true"
			:display-name="displayName"
			:is-no-user="isNoUser" />
		<div class="share-div__desc">
			<span>{{ displayName }}</span>
			<span>{{ displayNameAppendix }}</span>
		</div>
		<NcActions class="share-div__actions">
			<NcActionCaption :name="t('forms', 'Permissions')" />
			<NcActionCheckbox
				:checked="canAccessResults"
				@update:checked="updatePermissionResults">
				{{ t('forms', 'View responses') }}
			</NcActionCheckbox>
			<NcActionCheckbox
				:checked="canDeleteResults"
				:disabled="!canAccessResults"
				@update:checked="updatePermissionDeleteResults">
				{{ t('forms', 'Delete responses') }}
			</NcActionCheckbox>
			<NcActionSeparator />
			<NcActionButton @click="removeShare">
				<template #icon>
					<IconClose :size="20" />
				</template>
				{{ t('forms', 'Delete') }}
			</NcActionButton>
		</NcActions>
	</li>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionCaption from '@nextcloud/vue/dist/Components/NcActionCaption.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'

import IconClose from 'vue-material-design-icons/Close.vue'
import ShareTypes from '../../mixins/ShareTypes.js'
import PermissionTypes from '../../mixins/PermissionTypes.js'

export default {
	components: {
		IconClose,
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
	},

	computed: {
		canAccessResults() {
			return this.share.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS,
			)
		},
		canDeleteResults() {
			return this.share.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
			)
		},
		isNoUser() {
			return this.share.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER
		},
		displayName() {
			return !this.share.displayName
				? this.share.shareWith
				: this.share.displayName
		},
		displayNameAppendix() {
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
		removeShare() {
			this.$emit('remove-share', this.share)
		},

		/**
		 * @param {boolean} hasPermission If the results permission should be granted
		 */
		updatePermissionResults(hasPermission) {
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
		 * @param {boolean} hasPermission If the results_delete permission should be granted
		 */
		updatePermissionDeleteResults(hasPermission) {
			return this.updatePermission(
				this.PERMISSION_TYPES.PERMISSION_RESULTS_DELETE,
				hasPermission,
			)
		},

		/**
		 * Grant or remove permission from share
		 *
		 * @param {string} permission The permission to grant or remove
		 * @param {boolean} hasPermission True if granted, False if removed
		 */
		updatePermission(permission, hasPermission) {
			const share = { ...this.share }
			if (hasPermission) {
				share.permissions = [...new Set([...share.permissions, permission])]
			} else {
				share.permissions = share.permissions.filter(
					(perm) => perm !== permission,
				)
			}
			this.$emit('update:share', share)
		},
	},
}
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
