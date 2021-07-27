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
		<Avatar :user="share.shareWith"
			:disable-menu="true"
			:is-no-user="isNoUser" />
		<div class="share-div__desc">
			<span>{{ displayName }}</span>
			<span>{{ displayNameAppendix }}</span>
		</div>
		<Actions class="share-div__actions">
			<ActionButton icon="icon-close" @click="removeShare">
				{{ t('forms', 'Delete') }}
			</ActionButton>
		</Actions>
	</li>
</template>

<script>
import { Actions, ActionButton, Avatar } from '@nextcloud/vue'
import ShareTypes from '../../mixins/ShareTypes'

export default {
	components: {
		Actions,
		ActionButton,
		Avatar,
	},

	mixins: [ShareTypes],

	props: {
		share: {
			type: Object,
			required: true,
		},
	},

	computed: {
		isNoUser() {
			return this.share.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER
		},
		displayName() {
			if (this.share.displayName === '') return this.share.shareWith
			return this.share.displayName
		},
		displayNameAppendix() {
			if (this.share.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP) return `(${t('forms', 'Group')})`
			return ''
		},
	},

	methods: {
		removeShare() {
			this.$emit('remove-share', this.share)
		},
	},

}
</script>

<style lang="scss" scoped>
.share-div {
	display: flex;
	height: 44px;
	align-items: center;

	&__desc {
		padding: 8px;
		flex-grow: 1;
	}
}
</style>
