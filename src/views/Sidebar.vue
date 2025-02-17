<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar
		:open="sidebarOpened"
		:active="active"
		:name="t('forms', 'Form settings')"
		@update:active="onUpdateActive"
		@update:open="$emit('update:sidebarOpened', $event)">
		<NcAppSidebarTab id="forms-sharing" :order="0" :name="t('forms', 'Sharing')">
			<template #icon>
				<IconShareVariant :size="20" />
			</template>
			<SharingSidebarTab
				:form="form"
				@update:formProp="onPropertyChange"
				@add-share="onAddShare"
				@remove-share="onRemoveShare"
				@update-share="onUpdateShare" />
		</NcAppSidebarTab>

		<NcAppSidebarTab
			id="forms-settings"
			:order="1"
			:name="t('forms', 'Settings')">
			<template #icon>
				<IconSettings :size="20" />
			</template>
			<SettingsSidebarTab :form="form" @update:formProp="onPropertyChange" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
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
			const index = this.form.shares.findIndex(
				(search) => search.id === share.id,
			)
			this.form.shares.splice(index, 1)
			emit('forms:last-updated:set', this.form.id)
		},
		onUpdateShare(share) {
			const index = this.form.shares.findIndex(
				(search) => search.id === share.id,
			)
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
	margin-inline-start: 8px;
	margin-block-end: 8px;
}
</style>
