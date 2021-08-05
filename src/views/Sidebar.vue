<!--
 - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @author John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<AppSidebar
		v-show="opened"
		:title="t('forms', 'Form Settings')"
		@close="onClose">
		<AppSidebarTab
			id="settings"
			:order="1"
			:name="t('forms', 'Settings')"
			icon="icon-settings">
			<SettingsSidebarTab :form="form" />
		</AppSidebarTab>

		<AppSidebarTab
			id="sharing"
			:order="0"
			:name="t('forms', 'Sharing')"
			icon="icon-share">
			<SharingSidebarTab />
		</AppSidebarTab>

		<AppSidebarTab
			id="test"
			:order="2"
			name="test"
			icon="icon-no">
			<ShareDiv />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import SharingSidebarTab from '../components/SidebarTabs/SharingSidebarTab.vue'
import SettingsSidebarTab from '../components/SidebarTabs/SettingsSidebarTab.vue'
import ViewsMixin from '../mixins/ViewsMixin'

import ShareDiv from '../components/ShareDiv'

export default {
	name: 'Sidebar',

	components: {
		AppSidebar,
		AppSidebarTab,
		SharingSidebarTab,
		SettingsSidebarTab,
		ShareDiv,
	},
	mixins: [ViewsMixin],

	props: {
		opened: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		isPublic() {
			return this.form?.access?.type === 'public'
		},

		userShares() {
			return [...this.form?.access?.users || []]
		},
		groupShares() {
			return [...this.form?.access?.groups || []]
		},
	},

	methods: {
		/**
		 * Sidebar state methods
		 */
		onClose() {
			this.$emit('update:opened', false)
		},
		onToggle() {
			this.$emit('update:opened', !this.opened)
		},

		/**
		 * Save Form-Properties
		 */
		onAccessChange() {
			this.saveFormProperty('access')
		},
		onSharingChange({ groups, users }) {
			this.$set(this.form.access, 'groups', groups)
			this.$set(this.form.access, 'users', users)
			this.onAccessChange()
		},
	},
}
</script>

<style lang="scss" scoped>
.copyShareLink {
	margin: 8px;
}

h3 {
	font-weight: bold;
	margin-left: 8px;
	margin-bottom: 8px;
}

ul {
	margin-bottom: 24px;

	label {
		padding: 8px;
		display: block;

		span[class^='icon-'],
		span[class*=' icon-'] {
			background-position: 4px;
			padding-left: 24px;
		}
	}
}

input,
textarea {
	&.error {
		border: 2px solid var(--color-error);
		box-shadow: 1px 0 var(--border-radius) var(--color-box-shadow);
	}
}

</style>
