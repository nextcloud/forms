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
				@update:modelValue="onCreationAllowedGroupsChange" />
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
				v-model="appConfig.allowCustomPublicShareTokens"
				:loading="loading.allowCustomPublicShareTokens"
				type="switch"
				@update:modelValue="onAllowCustomPublicShareTokensChange">
				{{ t('forms', 'Allow custom public share tokens') }}
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

<script lang="ts">
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineComponent, ref } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import logger from './utils/Logger.ts'

const formsAppName = 'forms'

interface AppConfig {
	restrictCreation: boolean
	creationAllowedGroups: GroupOption[]
	allowConfirmationEmail: boolean
	allowComments: boolean
	allowCustomPublicShareTokens: boolean
	allowPermitAll: boolean
	allowPublicLink: boolean
	allowShowToAll: boolean
	isMailConfigured: boolean
	confirmationEmailRateLimit?: number
}

interface GroupOption {
	groupId: string
	displayName?: string
}

export default defineComponent({
	name: 'FormsSettings',

	components: {
		NcCheckboxRadioSwitch,
		NcInputField,
		NcNoteCard,
		NcSelect,
		NcSettingsSection,
	},

	setup() {
		const appConfig = ref<AppConfig>(
			loadState(formsAppName, 'appConfig') as AppConfig,
		)
		const availableGroups = ref<GroupOption[]>(
			loadState(formsAppName, 'availableGroups') as GroupOption[],
		)
		const confirmationEmailRateLimitInput = ref(
			String(
				(loadState(formsAppName, 'appConfig') as AppConfig)
					.confirmationEmailRateLimit ?? 3,
			),
		)
		const loading = ref<Record<string, boolean>>({})

		/**
		 * Reload the current AppConfig. Used to restore in case of saving-failure.
		 */
		const reloadAppConfig = async (): Promise<void> => {
			try {
				const resp = await axios.get(generateUrl('apps/forms/config'))
				appConfig.value = resp.data
			} catch (error) {
				logger.error('Error while reloading config', { error })
				showError(t('forms', 'Error while reloading config'))
			}
		}

		/**
		 * Save a key-value pair to the appConfig.
		 *
		 * @param configKey The key to store. Must be one of the used configKeys (See php-constants).
		 * @param configValue The value to store.
		 */
		const saveAppConfig = async (
			configKey: string,
			configValue: unknown,
		): Promise<void> => {
			try {
				await axios.patch(generateUrl('apps/forms/config'), {
					configKey,
					configValue,
				})
			} catch (error) {
				logger.error('Error while saving configuration', { error })
				showError(t('forms', 'Error while saving configuration'))
				await reloadAppConfig()
			}
		}

		/**
		 * Similar procedures on**Change:
		 *
		 * - Show corresponding switch as loading
		 * - Update value via api
		 * - Only after everything is done (incl. possible reload on failure), unset loading.
		 *
		 * @param newVal The resp. new Value to store.
		 */
		const onRestrictCreationChange = async (newVal: boolean): Promise<void> => {
			loading.value.restrictCreation = true
			await saveAppConfig('restrictCreation', newVal)
			loading.value.restrictCreation = false
		}

		const onCreationAllowedGroupsChange = async (
			newVal: GroupOption[],
		): Promise<void> => {
			loading.value.creationAllowedGroups = true
			await saveAppConfig(
				'creationAllowedGroups',
				newVal.map((group) => group.groupId),
			)
			loading.value.creationAllowedGroups = false
		}

		const onAllowPublicLinkChange = async (newVal: boolean): Promise<void> => {
			loading.value.allowPublicLink = true
			await saveAppConfig('allowPublicLink', newVal)
			loading.value.allowPublicLink = false
		}

		const onAllowCustomPublicShareTokensChange = async (
			newVal: boolean,
		): Promise<void> => {
			loading.value.allowCustomPublicShareTokens = true
			await saveAppConfig('allowCustomPublicShareTokens', newVal)
			loading.value.allowCustomPublicShareTokens = false
		}

		const onAllowPermitAllChange = async (newVal: boolean): Promise<void> => {
			loading.value.allowPermitAll = true
			await saveAppConfig('allowPermitAll', newVal)
			loading.value.allowPermitAll = false
		}

		const onAllowShowToAllChange = async (newVal: boolean): Promise<void> => {
			loading.value.allowShowToAll = true
			await saveAppConfig('allowShowToAll', newVal)
			loading.value.allowShowToAll = false
		}

		const onAllowConfirmationEmailChange = async (
			newVal: boolean,
		): Promise<void> => {
			loading.value.allowConfirmationEmail = true
			await saveAppConfig('allowConfirmationEmail', newVal)
			loading.value.allowConfirmationEmail = false
		}

		const onConfirmationEmailRateLimitChange = async (): Promise<void> => {
			const value = Math.max(
				1,
				Math.min(
					100,
					parseInt(confirmationEmailRateLimitInput.value, 10) || 3,
				),
			)
			confirmationEmailRateLimitInput.value = String(value)
			await saveAppConfig('confirmationEmailRateLimit', value)
		}

		const onAllowCommentsChange = async (newVal: boolean): Promise<void> => {
			loading.value.allowComments = true
			await saveAppConfig('allowComments', newVal)
			loading.value.allowComments = false
		}

		return {
			appConfig,
			availableGroups,
			confirmationEmailRateLimitInput,
			loading,
			onRestrictCreationChange,
			onCreationAllowedGroupsChange,
			onAllowPublicLinkChange,
			onAllowCustomPublicShareTokensChange,
			onAllowPermitAllChange,
			onAllowShowToAllChange,
			onAllowConfirmationEmailChange,
			onConfirmationEmailRateLimitChange,
			onAllowCommentsChange,
			t,
		}
	},
})
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
