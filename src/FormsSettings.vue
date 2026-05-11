<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcSettingsSection :name="t('forms', 'Form creation')">
			<NcCheckboxRadioSwitch
				v-model="appConfig.restrictCreation"
				class="forms-settings__creation__switch"
				:loading="loading.restrictCreation"
				type="switch"
				@update:modelValue="onRestrictCreationChange">
				{{ t('forms', 'Restrict form creation to selected groups') }}
			</NcCheckboxRadioSwitch>
			<NcSelect
				v-model="appConfig.creationAllowedGroups"
				:disabled="!appConfig.restrictCreation"
				multiple
				:options="availableGroups"
				:placeholder="t('forms', 'Select groups')"
				class="forms-settings__creation__multiselect"
				label="displayName"
				@input="onCreationAllowedGroupsChange" />
		</NcSettingsSection>
		<NcSettingsSection
			:name="t('forms', 'Confirmation emails')"
			:description="
				t(
					'forms',
					'Allow form owners to send a confirmation email to respondents after submission.',
				)
			">
			<NcCheckboxRadioSwitch
				v-model="appConfig.allowConfirmationEmail"
				:disabled="!appConfig.isMailConfigured"
				:loading="loading.allowConfirmationEmail"
				type="switch"
				@update:modelValue="onAllowConfirmationEmailChange">
				{{ t('forms', 'Allow confirmation emails to form respondents') }}
			</NcCheckboxRadioSwitch>
			<NcNoteCard v-if="!appConfig.isMailConfigured" type="warning">
				{{
					t(
						'forms',
						'Mail server is not configured. Please configure it in the basic settings before enabling this feature.',
					)
				}}
			</NcNoteCard>
			<NcInputField
				v-if="appConfig.allowConfirmationEmail"
				v-model="confirmationEmailRateLimitInput"
				:label="t('forms', 'Rate limit (emails per recipient per 24 hours)')"
				:helperText="
					t(
						'forms',
						'Maximum number of confirmation emails sent to the same address per 24 hours.',
					)
				"
				type="number"
				:min="1"
				:max="100"
				class="forms-settings__rate-limit"
				@change="onConfirmationEmailRateLimitChange" />
		</NcSettingsSection>
		<NcSettingsSection :name="t('forms', 'Form sharing')">
			<NcCheckboxRadioSwitch
				v-model="appConfig.allowPublicLink"
				:loading="loading.allowPublicLink"
				type="switch"
				@update:modelValue="onAllowPublicLinkChange">
				{{ t('forms', 'Allow sharing by link') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-model="appConfig.allowPermitAll"
				:loading="loading.allowPermitAll"
				type="switch"
				@update:modelValue="onAllowPermitAllChange">
				{{ t('forms', 'Allow sharing to all logged in accounts') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				v-model="appConfig.allowShowToAll"
				:loading="loading.allowShowToAll"
				type="switch"
				@update:modelValue="onAllowShowToAllChange">
				{{
					t(
						'forms',
						'Allow showing form to all logged in accounts on sidebar',
					)
				}}
			</NcCheckboxRadioSwitch>
		</NcSettingsSection>
		<NcSettingsSection :name="t('forms', 'Comments')">
			<NcCheckboxRadioSwitch
				v-model="appConfig.allowComments"
				:loading="loading.allowComments"
				type="switch"
				@update:modelValue="onAllowCommentsChange">
				{{ t('forms', 'Allow comments') }}
			</NcCheckboxRadioSwitch>
		</NcSettingsSection>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import logger from './utils/Logger.js'

export default {
	name: 'FormsSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcInputField,
		NcNoteCard,
		NcSelect,
		NcSettingsSection,
	},

	data() {
		return {
			appConfig: loadState(appName, 'appConfig'),
			availableGroups: loadState(appName, 'availableGroups'),

			confirmationEmailRateLimitInput: String(
				loadState(appName, 'appConfig').confirmationEmailRateLimit ?? 3,
			),

			loading: {},
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
			this.loading.restrictCreation = true
			await this.saveAppConfig('restrictCreation', newVal)
			this.loading.restrictCreation = false
		},

		async onCreationAllowedGroupsChange(newVal) {
			this.loading.creationAllowedGroups = true
			await this.saveAppConfig(
				'creationAllowedGroups',
				newVal.map((group) => group.groupId),
			)
			this.loading.creationAllowedGroups = false
		},

		async onAllowPublicLinkChange(newVal) {
			this.loading.allowPublicLink = true
			await this.saveAppConfig('allowPublicLink', newVal)
			this.loading.allowPublicLink = false
		},

		async onAllowPermitAllChange(newVal) {
			this.loading.allowPermitAll = true
			await this.saveAppConfig('allowPermitAll', newVal)
			this.loading.allowPermitAll = false
		},

		async onAllowShowToAllChange(newVal) {
			this.loading.allowShowToAll = true
			await this.saveAppConfig('allowShowToAll', newVal)
			this.loading.allowShowToAll = false
		},

		async onAllowConfirmationEmailChange(newVal) {
			this.loading.allowConfirmationEmail = true
			await this.saveAppConfig('allowConfirmationEmail', newVal)
			this.loading.allowConfirmationEmail = false
		},

		async onConfirmationEmailRateLimitChange() {
			const value = Math.max(
				1,
				Math.min(
					100,
					parseInt(this.confirmationEmailRateLimitInput, 10) || 3,
				),
			)
			this.confirmationEmailRateLimitInput = String(value)
			await this.saveAppConfig('confirmationEmailRateLimit', value)
		},

		async onAllowCommentsChange(newVal) {
			this.loading.allowComments = true
			await this.saveAppConfig('allowComments', newVal)
			this.loading.allowComments = false
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

	&__rate-limit {
		margin-top: calc(var(--default-grid-baseline) * 3);
	}
}
</style>
