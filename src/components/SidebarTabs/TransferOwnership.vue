<!--
  - @copyright Copyright (c) 2022 Hamza Mahjoubi <hamzamahjoubi221@gmail.com>
  -
  - @author Hamza Mahjoubi <hamzamahjoubi221@gmail.com>
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
		<NcButton
			class="transfer-button"
			alignment="start"
			type="tertiary"
			:wide="true"
			@click="openModal">
			<span class="transfer-button__text">{{
				t('forms', 'Transfer ownership')
			}}</span>
		</NcButton>

		<NcDialog
			:open.sync="showModal"
			content-classes="modal-content"
			:name="t('forms', 'Transfer ownership')"
			:out-transition="true"
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
					:clear-search-on-select="true"
					:close-on-select="true"
					:loading="loading"
					:get-option-key="(option) => option.key"
					:options="options"
					:placeholder="t('forms', 'Search for a user')"
					:user-select="true"
					label="displayName"
					@search="
						(query) => asyncSearch(query, [SHARE_TYPES.SHARE_TYPE_USER])
					">
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
					type="error"
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
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDialog from '@nextcloud/vue/dist/Components/NcDialog.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
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
					logger.error('Error while transfering form ownership', { error })
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
