<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sidebar-tabs__content">
		<NcNoteCard
			v-if="locked"
			type="info"
			:heading="t('forms', 'Form is locked')"
			:text="
				t('forms', 'Lock by {lockedBy}, expires: {lockedUntil}', {
					lockedBy: form.lockedBy ? form.lockedBy : form.ownerId,
					lockedUntil:
						lockedUntil === '' ? t('forms', 'never') : lockedUntil,
				})
			" />
		<SharingSearchDiv
			:currentShares="form.shares"
			:showLoading="isLoading"
			:locked="locked"
			:isCurrentUserOwner="isCurrentUserOwner"
			@addShare="addShare" />

		<!-- Public Link -->
		<div
			v-if="!hasPublicLink && appConfig.allowPublicLink"
			class="share-div share-div--link">
			<div class="share-div__avatar">
				<NcIconSvgWrapper :svg="IconLinkVariant" />
			</div>
			<span class="share-div__desc">{{ t('forms', 'Share link') }}</span>
			<NcActions>
				<NcActionButton
					:disabled="locked || !isCurrentUserOwner"
					@click="addPublicLink">
					<template #icon>
						<NcIconSvgWrapper :svg="IconPlus" />
					</template>
					{{ t('forms', 'Add link') }}
				</NcActionButton>
			</NcActions>
		</div>
		<TransitionGroup v-else tag="div">
			<div
				v-for="share in publicLinkShares"
				:key="'share-' + share.id"
				class="share-div share-div--link"
				:class="{ 'share-div--embeddable': isEmbeddingAllowed(share) }">
				<div class="share-div__avatar">
					<NcIconSvgWrapper
						v-if="isEmbeddingAllowed(share)"
						:svg="IconLinkBoxVariantOutline" />
					<NcIconSvgWrapper v-else :svg="IconLinkVariant" />
				</div>
				<div class="share-div__desc share-div__desc--tokenized">
					<span v-if="!appConfig.allowCustomPublicShareTokens">{{
						isEmbeddingAllowed(share)
							? t('forms', 'Embeddable link')
							: t('forms', 'Share link')
					}}</span>
					<NcInputField
						v-else
						:modelValue="getShareTokenInput(share)"
						:disabled="locked || !isCurrentUserOwner"
						autocomplete="off"
						:label="
							isEmbeddingAllowed(share)
								? t('forms', 'Embeddable link token')
								: t('forms', 'Share link token')
						"
						:helperText="
							t(
								'forms',
								'Set the public share link token to something easy to remember or generate a new token.',
							)
						"
						showTrailingButton
						:trailingButtonLabel="
							isShareTokenLoading(share)
								? t('forms', 'Generating…')
								: t('forms', 'Generate new token')
						"
						@trailingButtonClick="generateNewToken(share)"
						@update:modelValue="setShareTokenInput(share, $event)">
						<template #trailing-button-icon>
							<NcLoadingIcon v-if="isShareTokenLoading(share)" />
							<NcIconSvgWrapper v-else :svg="IconRefresh" />
						</template>
					</NcInputField>
				</div>
				<NcActions :inline="1">
					<NcActionLink
						:href="getPublicShareLink(share)"
						@click.prevent="copyLink($event, getPublicShareLink(share))">
						<template #icon>
							<NcIconSvgWrapper :svg="IconCopyAll" />
						</template>
						{{ t('forms', 'Copy to clipboard') }}
					</NcActionLink>
					<NcActionButton @click="openQrDialog(share)">
						<template #icon>
							<NcIconSvgWrapper :svg="IconQr" />
						</template>
						{{ t('forms', 'Show QR code') }}
					</NcActionButton>
					<NcActionButton
						v-if="isEmbeddingAllowed(share)"
						@click="copyEmbeddingCode($event, share)">
						<template #icon>
							<NcIconSvgWrapper :svg="IconCodeBrackets" />
						</template>
						{{ t('forms', 'Copy embedding code') }}
					</NcActionButton>
					<NcActionButton
						v-else
						:disabled="locked || !isCurrentUserOwner"
						@click="makeEmbeddable(share)">
						<template #icon>
							<NcIconSvgWrapper :svg="IconLinkBoxVariantOutline" />
						</template>
						<!-- TRANSLATORS: This means the link can be embedded into external websites -->
						{{ t('forms', 'Convert to embeddable link') }}
					</NcActionButton>
					<NcActionButton
						:disabled="locked || !isCurrentUserOwner"
						@click="removeShare(share)">
						<template #icon>
							<NcIconSvgWrapper :svg="IconDelete" />
						</template>
						{{ t('forms', 'Remove link') }}
					</NcActionButton>
					<NcActionButton
						v-if="appConfig.allowPublicLink"
						closeAfterClick
						:disabled="locked || !isCurrentUserOwner"
						@click="addPublicLink">
						<template #icon>
							<NcIconSvgWrapper :svg="IconPlus" />
						</template>
						{{ t('forms', 'Add link') }}
					</NcActionButton>
				</NcActions>
			</div>
		</TransitionGroup>

		<QRDialog
			:title="
				t(
					'forms',
					'Share {formTitle}',
					{ formTitle: form.title },
					{ escape: false, sanitize: false },
				)
			"
			:text="qrDialogText"
			@closed="qrDialogText = ''" />

		<!-- Internal link -->
		<div class="share-div">
			<div class="share-div__avatar">
				<NcIconSvgWrapper :svg="IconLinkVariant" />
			</div>
			<div class="share-div__desc share-div__desc--twoline">
				<span>{{ t('forms', 'Internal link') }}</span>
				<span>{{
					t(
						'forms',
						'Only works for logged in accounts with access rights',
					)
				}}</span>
			</div>
			<NcActions>
				<NcActionLink
					:href="getInternalShareLink(form.hash)"
					@click.prevent="
						copyLink($event, getInternalShareLink(form.hash))
					">
					<template #icon>
						<NcIconSvgWrapper :svg="IconCopyAll" />
					</template>
					{{ t('forms', 'Copy to clipboard') }}
				</NcActionLink>
			</NcActions>
		</div>

		<!-- All users on Instance -->
		<div v-if="appConfig.allowPermitAll">
			<div class="share-div">
				<div class="share-div__avatar">
					<NcIconSvgWrapper :svg="IconAccountMultiple" />
				</div>
				<label for="share-switch__permit-all" class="share-div__desc">
					{{ t('forms', 'Permit access to all logged in accounts') }}
				</label>
				<NcCheckboxRadioSwitch
					id="share-switch__permit-all"
					:modelValue="form.access.permitAllUsers"
					:disabled="locked || !isCurrentUserOwner"
					type="switch"
					@update:modelValue="onPermitAllUsersChange" />
			</div>
			<div
				v-if="appConfig.allowShowToAll && form.access.permitAllUsers"
				class="share-div share-div--indent">
				<div class="share-div__avatar">
					<NcIconSvgWrapper :svg="FormsIcon" :size="16" />
				</div>
				<label for="share-switch__show-to-all" class="share-div__desc">
					{{ t('forms', 'Show to all accounts on sidebar') }}
				</label>
				<NcCheckboxRadioSwitch
					id="share-switch__show-to-all"
					:modelValue="form.access.showToAllUsers"
					:disabled="locked || !isCurrentUserOwner"
					type="switch"
					@update:modelValue="onShowToAllUsersChange" />
			</div>
		</div>

		<!-- Single shares -->
		<TransitionGroup tag="ul">
			<SharingShareDiv
				v-for="share in sortedShares"
				:key="'share-' + share.id"
				:share="share"
				:locked="locked"
				:isCurrentUserOwner="isCurrentUserOwner"
				@removeShare="removeShare"
				@update:share="updateShare" />
		</TransitionGroup>
	</div>
</template>

<script lang="ts">
import IconPlus from '@material-symbols/svg-400/outlined/add.svg?raw'
import IconCheck from '@material-symbols/svg-400/outlined/check.svg?raw'
import IconCodeBrackets from '@material-symbols/svg-400/outlined/code.svg?raw'
import IconCopyAll from '@material-symbols/svg-400/outlined/copy_all.svg?raw'
import IconDelete from '@material-symbols/svg-400/outlined/delete.svg?raw'
import IconAccountMultiple from '@material-symbols/svg-400/outlined/group.svg?raw'
import IconLinkBoxVariantOutline from '@material-symbols/svg-400/outlined/iframe.svg?raw'
import IconLinkVariant from '@material-symbols/svg-400/outlined/link_2.svg?raw'
import IconQr from '@material-symbols/svg-400/outlined/qr_code.svg?raw'
import IconRefresh from '@material-symbols/svg-400/outlined/refresh.svg?raw'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, defineComponent, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcInputField from '@nextcloud/vue/components/NcInputField'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import QRDialog from '../QRDialog.vue'
import SharingSearchDiv from './SharingSearchDiv.vue'
import SharingShareDiv from './SharingShareDiv.vue'
import FormsIcon from '../../../img/forms-dark.svg?raw'
import { useShareLink } from '../../composables/useShareLink.ts'
import { useShareTypes } from '../../composables/useShareTypes.ts'
import { INPUT_DEBOUNCE_MS } from '../../models/Constants.ts'
import { PERMISSION_TYPES } from '../../models/Permissions.ts'
import logger from '../../utils/Logger.ts'
import OcsResponse2Data from '../../utils/OcsResponse2Data.ts'

const formsAppName = 'forms'

interface ShareLike {
	id: number
	shareType: number
	shareWith: string
	displayName: string
	permissions: string[]
}

interface SharingSidebarAppConfig {
	allowPublicLink: boolean
	allowCustomPublicShareTokens: boolean
	allowPermitAll: boolean
	allowShowToAll: boolean
	[key: string]: unknown
}

export default defineComponent({
	components: {
		NcIconSvgWrapper,
		NcActions,
		NcActionButton,
		NcActionLink,
		NcCheckboxRadioSwitch,
		NcInputField,
		NcLoadingIcon,
		NcNoteCard,
		QRDialog,
		SharingSearchDiv,
		SharingShareDiv,
	},

	props: {
		form: {
			type: Object,
			required: true,
		},

		locked: {
			type: Boolean,
			required: true,
		},

		lockedUntil: {
			type: String,
			default: '',
		},
	},

	emits: ['addShare', 'updateShare', 'removeShare', 'update:formProp'],

	setup(props, { emit }) {
		const { SHARE_TYPES } = useShareTypes()
		const {
			copyEmbeddingCode,
			copyLink,
			getInternalShareLink,
			getPublicShareLink,
			isEmbeddingAllowed,
		} = useShareLink({
			PERMISSION_TYPES,
			SHARE_TYPES,
		})
		const isLoading = ref(false)
		const appConfig = loadState(
			formsAppName,
			'appConfig',
		) as SharingSidebarAppConfig
		const shareTokens = ref<Record<number, string>>({})
		const savingShareTokens = ref<Record<number, boolean>>({})
		const loadingShareTokenId = ref<number | null>(null)
		const qrDialogText = ref('')

		/**
		 * Sort by shareType and DisplayName
		 *
		 * @param a first share for comparison
		 * @param b second share for comparison
		 */
		const sortByTypeAndDisplayname = (a: ShareLike, b: ShareLike): number => {
			const aDisplayName = (a.displayName ?? '').toLowerCase()
			const bDisplayName = (b.displayName ?? '').toLowerCase()
			if (a.shareType < b.shareType) {
				return -1
			}
			if (a.shareType > b.shareType) {
				return 1
			}
			if (aDisplayName < bDisplayName) {
				return -1
			}
			if (aDisplayName > bDisplayName) {
				return 1
			}
			return 0
		}

		const isCurrentUserOwner = computed(
			() => getCurrentUser()?.uid === props.form.ownerId,
		)
		const sortedShares = computed<ShareLike[]>(() =>
			(props.form.shares as ShareLike[])
				.filter((share) => share.shareType !== SHARE_TYPES.SHARE_TYPE_LINK)
				.sort(sortByTypeAndDisplayname),
		)
		const publicLinkShares = computed<ShareLike[]>(() => {
			const shares = (props.form.shares as ShareLike[]).filter(
				(share) => share.shareType === SHARE_TYPES.SHARE_TYPE_LINK,
			)
			shares.sort((a: ShareLike, b: ShareLike) =>
				isEmbeddingAllowed(a) ? 1 : isEmbeddingAllowed(b) ? -1 : 0,
			)
			return shares
		})
		const hasPublicLink = computed(() => publicLinkShares.value.length !== 0)

		watch(
			publicLinkShares,
			(shares: ShareLike[]) => {
				const nextShareTokens: Record<number, string> = {}
				for (const share of shares) {
					nextShareTokens[share.id] =
						shareTokens.value[share.id] ?? share.shareWith
				}
				shareTokens.value = nextShareTokens
			},
			{ immediate: true },
		)

		/**
		 * Add share
		 *
		 * @param newShare the share object
		 * @param newShare.shareType type of the share
		 * @param newShare.shareWith with whom it should be shared
		 */
		const addShare = async (newShare: {
			shareType: number
			shareWith: string
		}): Promise<void> => {
			isLoading.value = true

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares', {
						id: props.form.id,
					}),
					{
						shareType: newShare.shareType,
						shareWith: newShare.shareWith,
					},
				)
				const share = OcsResponse2Data(response)

				// Add new share
				emit('addShare', share)
			} catch (error) {
				logger.error('Error while adding new share', {
					error,
					share: newShare,
				})
				showError(t('forms', 'There was an error while adding the share'))
			} finally {
				isLoading.value = false
			}
		}

		const addPublicLink = async (): Promise<void> => {
			isLoading.value = true

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares', {
						id: props.form.id,
					}),
					{
						shareType: SHARE_TYPES.SHARE_TYPE_LINK,
					},
				)
				const share = OcsResponse2Data(response)

				// Add new share
				emit('addShare', share)
			} catch (error) {
				logger.error('Error adding public link', { error })
				showError(t('forms', 'There was an error while adding the link'))
			} finally {
				isLoading.value = false
			}
		}

		/**
		 * Update the permissions for a share through the API.
		 *
		 * @param updatedShare The share payload with the new permission set.
		 */
		async function updateShare(updatedShare: ShareLike): Promise<void> {
			isLoading.value = true

			try {
				const response = await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares/{shareId}', {
						id: props.form.id,
						shareId: updatedShare.id,
					}),
					{
						keyValuePairs: {
							permissions: updatedShare.permissions,
						},
					},
				)
				const share = Object.assign(updatedShare, {
					id: OcsResponse2Data(response),
				})

				// Add new share
				emit('updateShare', share)
			} catch (error) {
				logger.error('Error while updating share', {
					error,
					share: updatedShare,
				})
				showError(t('forms', 'There was an error while updating the share'))
			} finally {
				isLoading.value = false
			}
		}

		/**
		 * Update the permissions for a share through the API.
		 *
		 * @param share The share being updated.
		 */
		function makeEmbeddable(share: ShareLike): void {
			updateShare({
				...share,
				permissions: [
					...share.permissions,
					PERMISSION_TYPES.PERMISSION_EMBED,
				],
			})
		}

		/**
		 * Remove share
		 *
		 * @param share the share to delete
		 */
		const removeShare = async (share: ShareLike): Promise<void> => {
			isLoading.value = true

			try {
				await axios.delete(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares/{shareId}', {
						id: props.form.id,
						shareId: share.id,
					}),
				)
				emit('removeShare', share)
			} catch (error) {
				logger.error('Error while removing share', { error, share })
				showError(t('forms', 'There was an error while removing the share'))
			} finally {
				isLoading.value = false
			}
		}

		/**
		 * Update the permit-all-users access flag.
		 *
		 * @param newVal The next checkbox value.
		 */
		const onPermitAllUsersChange = (newVal: boolean): void => {
			const newAccess = { ...props.form.access }
			newAccess.permitAllUsers = newVal
			emit('update:formProp', 'access', newAccess)
		}

		/**
		 * Update the show-to-all-users access flag.
		 *
		 * @param newVal The next checkbox value.
		 */
		const onShowToAllUsersChange = (newVal: boolean): void => {
			const newAccess = { ...props.form.access }
			newAccess.showToAllUsers = newVal
			emit('update:formProp', 'access', newAccess)
		}

		const getShareTokenInput = (share: ShareLike): string =>
			shareTokens.value[share.id] ?? share.shareWith

		const isShareTokenSaving = (share: ShareLike): boolean =>
			!!savingShareTokens.value[share.id]

		const isShareTokenLoading = (share: ShareLike): boolean =>
			loadingShareTokenId.value === share.id

		const updateShareToken = debounce(async (share: ShareLike) => {
			const token = shareTokens.value[share.id] ?? share.shareWith
			loadingShareTokenId.value = share.id
			savingShareTokens.value = {
				...savingShareTokens.value,
				[share.id]: true,
			}

			try {
				const response = await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares/{shareId}', {
						id: props.form.id,
						shareId: share.id,
					}),
					{
						keyValuePairs: {
							token,
						},
					},
				)

				emit('updateShare', {
					...share,
					id: OcsResponse2Data(response),
					shareWith: token,
				})
			} catch (error) {
				logger.error('Error while updating share token', {
					error,
					share,
					token,
				})
				showError(
					t('forms', 'There was an error while updating the link token'),
				)
			} finally {
				savingShareTokens.value = {
					...savingShareTokens.value,
					[share.id]: false,
				}
				loadingShareTokenId.value = null
			}
		}, INPUT_DEBOUNCE_MS)

		const setShareTokenInput = (
			share: ShareLike,
			token: string | number,
		): void => {
			shareTokens.value = {
				...shareTokens.value,
				[share.id]: String(token),
			}
			void updateShareToken(share)
		}

		const generateNewToken = async (share: ShareLike): Promise<void> => {
			loadingShareTokenId.value = share.id

			try {
				const { data } = await axios.get(
					generateOcsUrl('apps/forms/api/v3/token'),
				)
				setShareTokenInput(share, data.ocs.data.token)
			} catch (error) {
				logger.error('Error while generating share token', {
					error,
					share,
				})
				showError(
					t('forms', 'There was an error while generating the link token'),
				)
			} finally {
				loadingShareTokenId.value = null
			}
		}

		const openQrDialog = (share: ShareLike): void => {
			qrDialogText.value = getPublicShareLink(share)
		}

		return {
			appConfig,
			copyEmbeddingCode,
			copyLink,
			getInternalShareLink,
			getPublicShareLink,
			isCurrentUserOwner,
			isLoading,
			loadingShareTokenId,
			publicLinkShares,
			hasPublicLink,
			sortedShares,
			shareTokens,
			savingShareTokens,
			qrDialogText,
			SHARE_TYPES,
			FormsIcon,
			IconCheck,
			IconCopyAll,
			IconPlus,
			IconCodeBrackets,
			IconDelete,
			IconLinkVariant,
			IconLinkBoxVariantOutline,
			IconAccountMultiple,
			IconQr,
			IconRefresh,
			isEmbeddingAllowed,
			t,
			addShare,
			addPublicLink,
			makeEmbeddable,
			updateShare,
			removeShare,
			sortByTypeAndDisplayname,
			onPermitAllUsersChange,
			onShowToAllUsersChange,
			getShareTokenInput,
			isShareTokenSaving,
			isShareTokenLoading,
			setShareTokenInput,
			generateNewToken,
			openQrDialog,
		}
	},
})
</script>

<style lang="scss" scoped>
.sidebar-tabs__content {
	display: flex;
	flex-direction: column;
}

.share-div {
	display: flex;
	min-height: var(--default-clickable-area);
	align-items: center;

	&--link {
		.share-div__avatar {
			background-color: var(--color-primary-element);
			color: var(--color-primary-element-text);
		}
	}

	&--embeddable {
		.share-div__avatar {
			background-color: var(--color-primary-element-light);
			color: var(--color-primary-element-light-text);
		}
	}

	&--indent {
		margin-inline-start: 40px;
	}

	&__avatar {
		height: 32px;
		width: 32px;
		display: flex;
		align-items: center;
		flex-shrink: 0;
		border-radius: 50%;
		background-color: var(--color-background-dark);

		.material-design-icon {
			margin: auto;
		}
	}

	&__desc {
		padding: 0px 8px;
		flex-grow: 1;

		&--twoline {
			span {
				display: block;
				min-height: 18px;
				line-height: 1.2em;
			}
			:last-child {
				color: var(--color-text-maxcontrast);
			}
		}
	}
}
</style>
