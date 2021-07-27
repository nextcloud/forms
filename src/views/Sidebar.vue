<!--
 - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 -
 - @author John Molakvoæ <skjnldsv@protonmail.com>
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
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<AppSidebar v-show="opened"
		:title="t('forms', 'Form settings')"
		@close="onClose">
		<AppSidebarTab id="forms-sharing"
			:order="0"
			:name="t('forms', 'Sharing')"
			icon="icon-share">
			<SharingSidebarTab :form="form"
				@update:formProp="onPropertyChange" />
		</AppSidebarTab>

		<AppSidebarTab id="forms-settings"
			:order="1"
			:name="t('forms', 'Settings')"
			icon="icon-settings">
			<SettingsSidebarTab :form="form"
				@update:formProp="onPropertyChange" />
		</AppSidebarTab>
	</AppSidebar>
</template>

<script>
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import SharingSidebarTab from '../components/SidebarTabs/SharingSidebarTab.vue'
import SettingsSidebarTab from '../components/SidebarTabs/SettingsSidebarTab.vue'
import ViewsMixin from '../mixins/ViewsMixin'

export default {
	name: 'Sidebar',

	components: {
		AppSidebar,
		AppSidebarTab,
		SharingSidebarTab,
		SettingsSidebarTab,
	},
	mixins: [ViewsMixin],

	props: {
		opened: {
			type: Boolean,
			required: true,
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
		 *
		 * @param {string} property The Name of the Property to update
		 * @param {any} newVal The new Property value
		 */
		onPropertyChange(property, newVal) {
			this.$set(this.form, property, newVal)
			this.saveFormProperty(property)
		},
	},
}
</script>

<style lang="scss" scoped>
.app-sidebar__tab:focus {
	box-shadow: none;
}

.sidebar-tabs__content {
	margin: 4px;
}

h3 {
	font-weight: bold;
	margin-left: 8px;
	margin-bottom: 8px;
}
</style>
