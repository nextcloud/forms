<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcSettingsSection :name="t('forms', 'Form creation')">
			<NcCheckboxRadioSwitch
				ref="switchRestrictCreation"
				:checked.sync="appConfig.restrictCreation"
				class="forms-settings__creation__switch"
				type="switch"
				@update:checked="onRestrictCreationChange">
				{{ t('forms', 'Restrict form creation to selected groups') }}
			</NcCheckboxRadioSwitch>
			<NcSelect
				v-model="appConfig.creationAllowedGroups"
				:disabled="!appConfig.restrictCreation"
				:multiple="true"
				:options="availableGroups"
				:placeholder="t('forms', 'Select groups')"
				class="forms-settings__creation__multiselect"
				label="displayName"
				@input="onCreationAllowedGroupsChange" />
		</NcSettingsSection>
		<NcSettingsSection :name="t('forms', 'Form sharing')">
			<NcCheckboxRadioSwitch
				ref="switchAllowPublicLink"
				:checked.sync="appConfig.allowPublicLink"
				type="switch"
				@update:checked="onAllowPublicLinkChange">
				{{ t('forms', 'Allow sharing by link') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				ref="switchAllowPermitAll"
				:checked.sync="appConfig.allowPermitAll"
				type="switch"
				@update:checked="onAllowPermitAllChange">
				{{ t('forms', 'Allow sharing to all logged in accounts') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				ref="switchAllowShowToAll"
				:checked.sync="appConfig.allowShowToAll"
				type="switch"
				@update:checked="onAllowShowToAllChange">
				{{
					t(
						'forms',
						'Allow showing form to all logged in accounts on sidebar',
					)
				}}
			</NcCheckboxRadioSwitch>
		</NcSettingsSection>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'

import logger from './utils/Logger.js'

export default {
	name: 'FormsSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcSelect,
		NcSettingsSection,
	},

	data() {
		return {
			appConfig: loadState(appName, 'appConfig'),
			availableGroups: loadState(appName, 'availableGroups'),
		}
	},

	methods: {
		/**
		 * Similar procedures on**Change:
		 *
		 * - Show corresponding switch as loading
		 * - Update value via api
		 * - Only after everything is done (incl. possible reload on failure), unset loading.
		 *
		 * @param {boolean|Array} newVal The resp. new Value to store.
		 */
		async onRestrictCreationChange(newVal) {
			const el = this.$refs.switchRestrictCreation
			el.loading = true
			await this.saveAppConfig('restrictCreation', newVal)
			el.loading = false
		},
		async onCreationAllowedGroupsChange(newVal) {
			const el = this.$refs.switchRestrictCreation
			el.loading = true
			await this.saveAppConfig(
				'creationAllowedGroups',
				newVal.map((group) => group.groupId),
			)
			el.loading = false
		},
		async onAllowPublicLinkChange(newVal) {
			const el = this.$refs.switchAllowPublicLink
			el.loading = true
			await this.saveAppConfig('allowPublicLink', newVal)
			el.loading = false
		},
		async onAllowPermitAllChange(newVal) {
			const el = this.$refs.switchAllowPermitAll
			el.loading = true
			await this.saveAppConfig('allowPermitAll', newVal)
			el.loading = false
		},
		async onAllowShowToAllChange(newVal) {
			const el = this.$refs.switchAllowShowToAll
			el.loading = true
			await this.saveAppConfig('allowShowToAll', newVal)
			el.loading = false
		},

		/**
		 * Save a key-value pair to the appConfig.
		 *
		 * @param {string} configKey The key to store. Must be one of the used configKeys (See php-constants).
		 * @param {boolean|Array} configValue The value to store.
		 */
		async saveAppConfig(configKey, configValue) {
			try {
				await axios.patch(generateUrl('apps/forms/config'), {
					configKey,
					configValue,
				})
			} catch (error) {
				logger.error('Error while saving configuration', { error })
				showError(t('forms', 'Error while saving configuration'))
				await this.reloadAppConfig()
			}
		},

		/**
		 * Reload the current AppConfig. Used to restore in case of saving-failure.
		 */
		async reloadAppConfig() {
			try {
				const resp = await axios.get(generateUrl('apps/forms/config'))
				this.appConfig = resp.data
			} catch (error) {
				logger.error('Error while reloading config', { error })
				showError(t('forms', 'Error while reloading config'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.forms-settings {
	&__creation__switch {
		margin-block-end: 4px;
	}

	&__creation__multiselect {
		width: 100%;
	}
}
</style>
