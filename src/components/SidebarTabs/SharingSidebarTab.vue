<!--
  - @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<div class="sidebar-tabs__content">
		<SharingSearchDiv
			:current-shares="currentShares"
			@add-share="addShare" />

		<div class="share-div">
			<div class="share-div__avatar icon-public" />
			<div class="share-div__desc share-div__desc--twoline">
				<span>{{ t('forms', 'Internal Link') }}</span>
				<span>{{ t('forms', 'Logged-in users with access-permission can use this link.') }}</span>
			</div>
			<Actions>
				<ActionButton icon="icon-clippy" @click="copyShareLink">
					{{ t('forms', 'Copy to clipboard') }}
				</ActionButton>
			</Actions>
		</div>
		<!-- TODO Implement public share link -->
		<!-- <div class="share-div share-div--link">
			<div class="share-div__avatar icon-public-white" />
			<span class="share-div__desc">{{ t('forms', 'Public Share Link') }}</span>
			<Actions>
				<ActionButton icon="icon-add" @click="copyShareLink">
					{{ t('forms', 'Copy to clipboard') }}
				</ActionButton>
			</Actions>
		</div> -->
		<div class="share-div">
			<div class="share-div__avatar icon-group" />
			<span class="share-div__desc">
				{{ t('forms', 'Permit access to all users') }}
			</span>
			<CheckboxRadioSwitch :checked="form.access.permitAllUsers"
				type="switch"
				@update:checked="onPermitAllUsersChange" />
		</div>
		<div v-if="form.access.permitAllUsers" class="share-div share-div--indent">
			<div class="share-div__avatar icon-forms" />
			<span class="share-div__desc">
				{{ t('forms', 'Show to all users on sidebar') }}
			</span>
			<CheckboxRadioSwitch :checked="form.access.showToAllUsers"
				type="switch"
				@update:checked="onShowToAllUsersChange" />
		</div>

		<TransitionGroup tag="ul">
			<SharingShareDiv v-for="share in sortedUserShares"
				:key="'userShare-' + share.shareWith"
				:share="share"
				@remove-share="removeShare" />
			<SharingShareDiv v-for="share in sortedGroupShares"
				:key="'groupShare-' + share.shareWith"
				:share="share"
				@remove-share="removeShare" />
		</TransitionGroup>
	</div>
</template>

<script>
import { Actions, ActionButton, CheckboxRadioSwitch } from '@nextcloud/vue'
import SharingSearchDiv from './SharingSearchDiv.vue'
import SharingShareDiv from './SharingShareDiv.vue'
import ShareTypes from '../../mixins/ShareTypes'

export default {
	components: {
		Actions,
		ActionButton,
		CheckboxRadioSwitch,
		SharingSearchDiv,
		SharingShareDiv,
	},

	mixins: [ShareTypes],

	props: {
		form: {
			type: Object,
			required: true,
		},
	},

	computed: {
		currentShares() {
			return [...this.form.access.users, ...this.form.access.groups]
		},
		sortedUserShares() {
			return this.form.access.users.slice().sort(this.sortByDisplayname)
		},
		sortedGroupShares() {
			return this.form.access.groups.slice().sort(this.sortByDisplayname)
		},
	},

	methods: {
		addShare(share) {
			const newAccess = { ...this.form.access }

			if (share.shareType === this.SHARE_TYPES.SHARE_TYPE_USER) {
				newAccess.users.push(share)
			}
			if (share.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
				newAccess.groups.push(share)
			}

			this.$emit('update:formProp', 'access', newAccess)
		},

		removeShare(share) {
			const newAccess = { ...this.form.access }

			if (share.shareType === this.SHARE_TYPES.SHARE_TYPE_USER) {
				newAccess.users = this.form.access.users.filter(user => user !== share)
			}
			if (share.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
				newAccess.groups = this.form.access.groups.filter(group => group !== share)
			}

			this.$emit('update:formProp', 'access', newAccess)
		},

		sortByDisplayname(a, b) {
			if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
			if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
			return 0
		},

		onPermitAllUsersChange(newVal) {
			const newAccess = { ...this.form.access }
			newAccess.permitAllUsers = newVal
			this.$emit('update:formProp', 'access', newAccess)
		},
		onShowToAllUsersChange(newVal) {
			const newAccess = { ...this.form.access }
			newAccess.showToAllUsers = newVal
			this.$emit('update:formProp', 'access', newAccess)
		},
	},
}
</script>

<style lang="scss" scoped>
.share-div {
	display: flex;
	height: 44px;
	align-items: center;

	&--link {
		.share-div__avatar {
			background-color: var(--color-primary);
		}
	}

	&--indent {
		margin-left: 40px;
	}

	&__avatar {
		height: 32px;
		width: 32px;
		border-radius: 50%;
		background-color: var(--color-background-dark);
		background-size: 16px;
	}

	&__desc {
		padding: 8px;
		flex-grow: 1;

		&--twoline {
			span {
				display: block;
				height: 18px;
				line-height: 1.2em;
			}
			:last-child {
				color: var(--color-text-maxcontrast);
			}
		}
	}
}
</style>
