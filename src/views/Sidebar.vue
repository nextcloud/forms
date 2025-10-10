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
				:locked="isFormLocked"
				:locked-until="lockedUntilFormatted"
				@update:form-prop="onPropertyChange"
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
			<SettingsSidebarTab
				:form="form"
				:locked="isFormLocked"
				:locked-until="lockedUntilFormatted"
				@update:form-prop="onPropertyChange" />
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import IconSettings from 'vue-material-design-icons/CogOutline.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariantOutline.vue'
import SettingsSidebarTab from '../components/SidebarTabs/SettingsSidebarTab.vue'
import SharingSidebarTab from '../components/SidebarTabs/SharingSidebarTab.vue'
import ViewsMixin from '../mixins/ViewsMixin.js'

export default {
	// eslint-disable-next-line vue/multi-word-component-names
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

	emits: ['update:sidebarOpened', 'update:active'],

	computed: {
		lockedUntilFormatted() {
			if (this.form.lockedUntil === 0 || this.form.lockedUntil === null) {
				return ''
			}
			return moment(this.form.lockedUntil, 'X').fromNow()
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

h3 {
	font-weight: bold;
	margin-inline-start: 8px;
	margin-block-end: 8px;
}
</style>
