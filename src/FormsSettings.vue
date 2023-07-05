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
	<div>
		<NcSettingsSection :title="t('forms', 'Form creation')">
			<NcCheckboxRadioSwitch ref="switchRestrictCreation"
				:checked.sync="appConfig.restrictCreation"
				class="forms-settings__creation__switch"
				type="switch"
				@update:checked="onRestrictCreationChange">
				{{ t('forms', 'Restrict form creation to selected groups') }}
			</NcCheckboxRadioSwitch>
			<NcSelect v-model="appConfig.creationAllowedGroups"
				:disabled="!appConfig.restrictCreation"
				:multiple="true"
				:options="availableGroups"
				:placeholder="t('forms', 'Select groups')"
				class="forms-settings__creation__multiselect"
				label="displayName"
				@input="onCreationAllowedGroupsChange" />
		</NcSettingsSection>
		<NcSettingsSection :title="t('forms', 'Form sharing')">
			<NcCheckboxRadioSwitch ref="switchAllowPublicLink"
				:checked.sync="appConfig.allowPublicLink"
				type="switch"
				@update:checked="onAllowPublicLinkChange">
				{{ t('forms', 'Allow sharing by link') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch ref="switchAllowPermitAll"
				:checked.sync="appConfig.allowPermitAll"
				type="switch"
				@update:checked="onAllowPermitAllChange">
				{{ t('forms', 'Allow sharing to all logged in accounts') }}
			</NcCheckboxRadioSwitch>
		</NcSettingsSection>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'

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
			await this.saveAppConfig('creationAllowedGroups', newVal.map(group => group.groupId))
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

		/**
		 * Save a key-value pair to the appConfig.
		 *
		 * @param {string} configKey The key to store. Must be one of the used configKeys (See php-constants).
		 * @param {boolean|Array} configValue The value to store.
		 */
		async saveAppConfig(configKey, configValue) {
			try {
				await axios.post(generateUrl('apps/forms/config/update'), {
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
