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
	<NcAppSidebar v-show="sidebarOpened"
		:active="active"
		:title="t('forms', 'Form settings')"
		@close="onClose"
		@update:active="onUpdateActive">
		<NcAppSidebarTab id="forms-sharing"
			:order="0"
			:name="t('forms', 'Sharing')">
			<template #icon>
				<IconShareVariant :size="20" />
			</template>
			<SharingSidebarTab :form="form"
				@update:formProp="onPropertyChange"
				@add-share="onAddShare"
				@remove-share="onRemoveShare"
				@update-share="onUpdateShare" />
		</NcAppSidebarTab>

		<NcAppSidebarTab id="forms-settings"
			:order="1"
			:name="t('forms', 'Settings')">
			<template #icon>
				<IconSettings :size="20" />
			</template>
			<SettingsSidebarTab :form="form"
				@update:formProp="onPropertyChange" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import NcAppSidebar from '@nextcloud/vue/dist/Components/NcAppSidebar.js'
import NcAppSidebarTab from '@nextcloud/vue/dist/Components/NcAppSidebarTab.js'
import IconSettings from 'vue-material-design-icons/Cog.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariant.vue'

import SharingSidebarTab from '../components/SidebarTabs/SharingSidebarTab.vue'
import SettingsSidebarTab from '../components/SidebarTabs/SettingsSidebarTab.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'

export default {
	name: 'Sidebar',

	components: {
		IconSettings,
		IconShareVariant,
		NcAppSidebar,
		NcAppSidebarTab,
		SharingSidebarTab,
		SettingsSidebarTab,
	},

	mixins: [ViewsMixin],

	props: {
		active: {
			type: String,
			default: 'forms-sharing',
		},
	},

	methods: {
		/**
		 * Sidebar state methods
		 */
		onClose() {
			this.$emit('update:sidebarOpened', false)
		},
		onToggle() {
			this.$emit('update:sidebarOpened', !this.sidebarOpened)
		},
		onUpdateActive(active) {
			this.$emit('update:active', active)
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

		/**
		 * Adding/Removing Share from the reactive object. API-Request is done in sharing-tab.
		 *
		 * @param {object} share The respective share object
		 */
		onAddShare(share) {
			this.form.shares.push(share)
			emit('forms:last-updated:set', this.form.id)
		},
		onRemoveShare(share) {
			const index = this.form.shares.findIndex(search => search.id === share.id)
			this.form.shares.splice(index, 1)
			emit('forms:last-updated:set', this.form.id)
		},
		onUpdateShare(share) {
			const index = this.form.shares.findIndex(search => search.id === share.id)
			this.form.shares.splice(index, 1, share)
			emit('forms:last-updated:set', this.form.id)
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
