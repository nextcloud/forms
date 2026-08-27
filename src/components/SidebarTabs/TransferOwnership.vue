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
			:disabled="locked || !isOwner"
			@click="openModal">
			<span class="transfer-button__text">{{
				t('forms', 'Transfer ownership')
			}}</span>
		</NcButton>

		<NcDialog
			v-model:open="showModal"
			contentClasses="modal-content"
			:name="t('forms', 'Transfer ownership')"
			outTransition
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
				<NcSelectUsers
					v-model="selected"
					class="modal-content__select"
					:loading="loading"
					:options="options"
					:placeholder="t('forms', 'Search for a user')"
					@search="
						(query) => asyncSearch(query, [SHARE_TYPES.SHARE_TYPE_USER])
					">
					<template #no-options>
						{{ noResultText }}
					</template>
				</NcSelectUsers>

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
					v-model="confirmationInput"
					:label="t('forms', 'Confirmation text')"
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

<script lang="ts">
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import { computed, defineComponent, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { useUserSearch } from '../../composables/useUserSearch.ts'
import logger from '../../utils/Logger.ts'

interface SelectedUserLike {
	id: string
	shareWith: string
	displayName: string
}

export default defineComponent({
	components: {
		NcButton,
		NcDialog,
		NcTextField,
		NcSelectUsers,
	},

	props: {
		form: {
			type: Object,
			required: true,
		},

		isOwner: {
			type: Boolean,
			required: true,
		},

		locked: {
			type: Boolean,
			required: true,
		},
	},

	setup(props) {
		const userSearch = useUserSearch()
		const { SHARE_TYPES } = userSearch
		const selected = ref<SelectedUserLike | undefined>(undefined)
		const showModal = ref(false)
		const confirmationInput = ref('')

		const confirmationString = computed(
			() =>
				`${props.form.ownerId}/${props.form.title.replace(/\s/g, ' ').trim()}`,
		)
		const canTransfer = computed(
			() =>
				confirmationInput.value === confirmationString.value
				&& !!selected.value,
		)
		const options = computed(() => {
			if (userSearch.isValidQuery.value) {
				return userSearch.suggestions.value
			}
			return userSearch.recommendations.value
		})

		const clearText = (): void => {
			confirmationInput.value = ''
		}
		const closeModal = (): void => {
			showModal.value = false
		}
		const escapedString = (textToEscape: string): string =>
			'' + textToEscape.replace('<', '&lt;').replace('>', '&gt;')
		const openModal = (): void => {
			showModal.value = true
		}
		const onSearch = (query: string): void => {
			void userSearch.asyncSearch(query, [SHARE_TYPES.SHARE_TYPE_USER])
		}
		const onOwnershipTransfer = async (): Promise<void> => {
			showModal.value = false
			if (props.form.id && selected.value?.shareWith) {
				try {
					emit('forms:last-updated:set', props.form.id)
					await axios.patch(
						generateOcsUrl('apps/forms/api/v3/forms/{id}', {
							id: props.form.id,
						}),
						{
							keyValuePairs: {
								ownerId: selected.value?.shareWith,
							},
						},
					)
					showSuccess(
						`${t('forms', 'This form is now owned by')} ${selected.value?.displayName}`,
					)
					emit('forms:ownership-transfered', props.form.id)
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
					selectedUser: selected.value,
				})
				showError(
					t('forms', 'An error occurred while transfering ownership'),
				)
			}
		}

		return {
			...userSearch,
			SHARE_TYPES,
			selected,
			showModal,
			confirmationInput,
			canTransfer,
			confirmationString,
			options,
			clearText,
			closeModal,
			escapedString,
			openModal,
			onSearch,
			onOwnershipTransfer,
			t,
		}
	},
})
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
