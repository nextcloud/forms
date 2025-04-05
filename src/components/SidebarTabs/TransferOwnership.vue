<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcButton
			class="transfer-button"
			alignment="start"
			variant="tertiary"
			wide
			@click="openModal">
			<span class="transfer-button__text">{{
				t('forms', 'Transfer ownership')
			}}</span>
		</NcButton>

		<NcDialog
			:open.sync="showModal"
			content-classes="modal-content"
			:name="t('forms', 'Transfer ownership')"
			out-transition
			@close="closeModal">
			<template #default>
				<!-- eslint-disable vue/no-v-html -->
				<p
					v-html="
						t(
							'forms',
							'You\'re going to transfer the ownership of {name} to another account. Please select the account to which you want to transfer ownership.',
							{
								name: `<strong>${escapedString(form.title)}</strong>`,
							},
							undefined,
							{ escape: false },
						)
					" />
				<!-- eslint-enable vue/no-v-html -->
				<NcSelect
					v-model="selected"
					class="modal-content__select"
					:reset-focus-on-options-change="false"
					clear-search-on-select
					close-on-select
					:loading="loading"
					:get-option-key="(option) => option.key"
					:options="options"
					:placeholder="t('forms', 'Search for a user')"
					user-select
					label="displayName"
					@search="(query) => asyncSearch(query, true)">
					<template #no-options>
						{{ noResultText }}
					</template>
				</NcSelect>

				<br />

				<!-- eslint-disable vue/no-v-html -->
				<p
					v-html="
						t(
							'forms',
							'Type {text} to confirm.',
							{
								text: `<strong>${escapedString(confirmationString)}</strong>`,
							},
							undefined,
							{ escape: false },
						)
					" />
				<!-- eslint-enable vue/no-v-html -->
				<NcTextField
					:label="t('forms', 'Confirmation text')"
					:value.sync="confirmationInput"
					:success="confirmationInput === confirmationString" />

				<br />

				<p>
					<strong>{{ t('forms', 'This can not be undone.') }}</strong>
				</p>
			</template>
			<template #actions>
				<NcButton
					:disabled="!canTransfer"
					variant="error"
					@click="onOwnershipTransfer">
					{{ t('forms', 'I understand, transfer this form') }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>

<script>
import { showSuccess, showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import UserSearchMixin from '../../mixins/UserSearchMixin.js'
import logger from '../../utils/Logger.js'

export default {
	components: {
		NcButton,
		NcDialog,
		NcTextField,
		NcSelect,
	},
	mixins: [UserSearchMixin],

	props: {
		form: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			selected: null,
			showModal: false,
			confirmationInput: '',
			loading: false,
		}
	},
	computed: {
		canTransfer() {
			return (
				this.confirmationInput === this.confirmationString && !!this.selected
			)
		},
		confirmationString() {
			return `${this.form.ownerId}/${this.form.title}`
		},
		options() {
			if (this.isValidQuery) {
				// Suggestions without existing shares
				return this.suggestions
			}
			// Recommendations without existing shares
			return this.recommendations
		},
	},
	methods: {
		clearText() {
			this.confirmationInput = ''
		},
		closeModal() {
			this.showModal = false
		},
		escapedString(textToEscape) {
			return '' + textToEscape.replace('<', '&lt;').replace('>', '&gt;')
		},
		openModal() {
			this.showModal = true
		},
		async onOwnershipTransfer() {
			this.showModal = false
			if (this.form.id && this.selected.shareWith) {
				try {
					emit('forms:last-updated:set', this.form.id)
					await axios.patch(
						generateOcsUrl('apps/forms/api/v3/forms/{id}', {
							id: this.form.id,
						}),
						{
							keyValuePairs: {
								ownerId: this.selected.shareWith,
							},
						},
					)
					showSuccess(
						`${t('forms', 'This form is now owned by')} ${this.selected.displayName}`,
					)
					emit('forms:ownership-transfered', this.form.id)
				} catch (error) {
					logger.error('Error while transfering form ownership', {
						error,
					})
					showError(
						t('forms', 'An error occurred while transfering ownership'),
					)
				}
			} else {
				logger.error('Null parameters while transfering form ownership', {
					selectedUser: this.selected,
				})
				showError(
					t('forms', 'An error occurred while transfering ownership'),
				)
			}
		},
		clearSelected() {
			this.selected = null
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.modal-content) {
	padding-inline: 18px;

	display: flex;
	flex-direction: column;
	gap: 8px;
}

.transfer-button__text {
	color: var(--color-error-text);
}
</style>
