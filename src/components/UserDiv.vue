<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
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
	<div class="user-row">
		<Avatar :user="shareWith" :display-name="computedDisplayName" :is-no-user="isNoUser" />
	</div>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import ShareTypes from '../mixins/ShareTypes'

export default {
	components: {
		Avatar,
	},
	mixins: [ShareTypes],

	props: {
		shareWith: {
			type: String,
			required: true,
		},
		displayName: {
			type: String,
			required: true,
		},
		shareType: {
			type: Number,
			required: true,
		},

	},

	computed: {
		isNoUser() {
			return this.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER
		},
		computedDisplayName() {
			if (this.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
				return `${this.displayName} (${t('forms', 'Group')})`
			}
			return this.displayName
		},

	},
}
</script>

<style lang="scss">
.user-row {
	display: flex;
	flex-grow: 0;
	align-items: center;
	margin-left: 0;
	margin-top: 0;

	> div {
		margin: 2px 4px;
	}

	.description {
		opacity: 0.7;
		flex-grow: 0;
	}

	.avatar {
		height: 32px;
		width: 32px;
		flex-grow: 0;
	}

	.user-name {
		opacity: 0.5;
		flex-grow: 1;
	}
}
</style>
