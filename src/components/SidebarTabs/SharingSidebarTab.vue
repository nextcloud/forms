<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="sidebar-tabs__content">
		<SharingSearchDiv
			:current-shares="form.shares"
			:show-loading="isLoading"
			@add-share="addShare" />

		<!-- Public Link -->
		<div
			v-if="!hasPublicLink && appConfig.allowPublicLink"
			class="share-div share-div--link">
			<div class="share-div__avatar">
				<IconLinkVariant :size="20" />
			</div>
			<span class="share-div__desc">{{ t('forms', 'Share link') }}</span>
			<NcActions>
				<NcActionButton @click="addPublicLink">
					<template #icon>
						<IconPlus :size="20" />
					</template>
					{{ t('forms', 'Add link') }}
				</NcActionButton>
			</NcActions>
		</div>
		<TransitionGroup v-else tag="div">
			<div
				v-for="share in publicLinkShares"
				:key="'share-' + share.shareType + '-' + share.shareWith"
				:set="void (isEmbeddable = isEmbeddingAllowed(share))"
				class="share-div share-div--link"
				:class="{ 'share-div--embeddable': isEmbeddingAllowed(share) }">
				<div class="share-div__avatar">
					<IconLinkBoxVariantOutline v-if="isEmbeddable" :size="20" />
					<IconLinkVariant v-else :size="20" />
				</div>
				<span class="share-div__desc">{{
					isEmbeddable
						? t('forms', 'Embeddable link')
						: t('forms', 'Share link')
				}}</span>
				<NcActions :inline="1">
					<NcActionLink
						:href="getPublicShareLink(share)"
						@click.prevent="copyLink($event, getPublicShareLink(share))">
						<template #icon>
							<IconCopyAll :size="20" />
						</template>
						{{ t('forms', 'Copy to clipboard') }}
					</NcActionLink>
					<NcActionButton @click="openQrDialog(share)">
						<template #icon>
							<IconQr :size="20" />
						</template>
						{{ t('forms', 'Show QR code') }}
					</NcActionButton>
					<NcActionButton
						v-if="isEmbeddable"
						@click="copyEmbeddingCode(share)">
						<template #icon>
							<IconCodeBrackets :size="20" />
						</template>
						{{ t('forms', 'Copy embedding code') }}
					</NcActionButton>
					<NcActionButton v-else @click="makeEmbeddable(share)">
						<template #icon>
							<IconLinkBoxVariantOutline :size="20" />
						</template>
						<!-- TRANSLATORS: This means the link can be embedded into external websites -->
						{{ t('forms', 'Convert to embeddable link') }}
					</NcActionButton>
					<NcActionButton @click="removeShare(share)">
						<template #icon>
							<IconDelete :size="20" />
						</template>
						{{ t('forms', 'Remove link') }}
					</NcActionButton>
					<NcActionButton
						v-if="appConfig.allowPublicLink"
						:close-after-click="true"
						@click="addPublicLink">
						<template #icon>
							<IconPlus :size="20" />
						</template>
						{{ t('forms', 'Add link') }}
					</NcActionButton>
				</NcActions>
			</div>
		</TransitionGroup>

		<QRDialog
			:title="t('forms', 'Share {formTitle}', { formTitle: form.title })"
			:text="qrDialogText"
			@closed="qrDialogText = ''" />

		<!-- Internal link -->
		<div class="share-div">
			<div class="share-div__avatar">
				<IconLinkVariant :size="20" />
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
						<IconCopyAll :size="20" />
					</template>
					{{ t('forms', 'Copy to clipboard') }}
				</NcActionLink>
			</NcActions>
		</div>

		<!-- All users on Instance -->
		<div v-if="appConfig.allowPermitAll">
			<div class="share-div">
				<div class="share-div__avatar">
					<IconAccountMultiple :size="20" />
				</div>
				<label for="share-switch__permit-all" class="share-div__desc">
					{{ t('forms', 'Permit access to all logged in accounts') }}
				</label>
				<NcCheckboxRadioSwitch
					id="share-switch__permit-all"
					:checked="form.access.permitAllUsers"
					type="switch"
					@update:checked="onPermitAllUsersChange" />
			</div>
			<div
				v-if="appConfig.allowShowToAll && form.access.permitAllUsers"
				class="share-div share-div--indent">
				<div class="share-div__avatar">
					<FormsIcon :size="16" />
				</div>
				<label for="share-switch__show-to-all" class="share-div__desc">
					{{ t('forms', 'Show to all accounts on sidebar') }}
				</label>
				<NcCheckboxRadioSwitch
					id="share-switch__show-to-all"
					:checked="form.access.showToAllUsers"
					type="switch"
					@update:checked="onShowToAllUsersChange" />
			</div>
		</div>

		<!-- Single shares -->
		<TransitionGroup tag="ul">
			<SharingShareDiv
				v-for="share in sortedShares"
				:key="'share-' + share.shareType + '-' + share.shareWith"
				:share="share"
				@remove-share="removeShare"
				@update:share="updateShare" />
		</TransitionGroup>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'

import axios from '@nextcloud/axios'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconCodeBrackets from 'vue-material-design-icons/CodeBrackets.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconLinkBoxVariantOutline from 'vue-material-design-icons/LinkBoxVariantOutline.vue'
import IconLinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconQr from 'vue-material-design-icons/Qrcode.vue'

import FormsIcon from '../Icons/FormsIcon.vue'
import IconCopyAll from '../Icons/IconCopyAll.vue'
import SharingSearchDiv from './SharingSearchDiv.vue'
import SharingShareDiv from './SharingShareDiv.vue'
import PermissionTypes from '../../mixins/PermissionTypes.js'
import QRDialog from '../QRDialog.vue'
import ShareTypes from '../../mixins/ShareTypes.js'
import ShareLinkMixin from '../../mixins/ShareLinkMixin.js'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import logger from '../../utils/Logger.js'

export default {
	components: {
		FormsIcon,
		IconAccountMultiple,
		IconCodeBrackets,
		IconCopyAll,
		IconDelete,
		IconLinkBoxVariantOutline,
		IconLinkVariant,
		IconPlus,
		IconQr,
		NcActions,
		NcActionButton,
		NcActionLink,
		NcCheckboxRadioSwitch,
		QRDialog,
		SharingSearchDiv,
		SharingShareDiv,
	},

	mixins: [ShareTypes, ShareLinkMixin, PermissionTypes],

	props: {
		form: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			isLoading: false,
			appConfig: loadState(appName, 'appConfig'),
			qrDialogText: '',
		}
	},

	computed: {
		sortedShares() {
			// Remove Link-Shares, which are handled separately, then sort
			return this.form.shares
				.filter(
					(share) => share.shareType !== this.SHARE_TYPES.SHARE_TYPE_LINK,
				)
				.sort(this.sortByTypeAndDisplayname)
		},
		hasPublicLink() {
			return this.publicLinkShares.length !== 0
		},
		publicLinkShares() {
			const shares = this.form.shares.filter(
				(share) => share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK,
			)
			shares.sort((a, b) =>
				this.isEmbeddingAllowed(a) ? 1 : this.isEmbeddingAllowed(b) ? -1 : 0,
			)
			return shares
		},
	},

	methods: {
		/**
		 * Add share
		 *
		 * @param {object} newShare the share object
		 */
		async addShare(newShare) {
			this.isLoading = true

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares', {
						id: this.form.id,
					}),
					{
						shareType: newShare.shareType,
						shareWith: newShare.shareWith,
					},
				)
				const share = OcsResponse2Data(response)

				// Add new share
				this.$emit('add-share', share)
			} catch (error) {
				logger.error('Error while adding new share', {
					error,
					share: newShare,
				})
				showError(t('forms', 'There was an error while adding the share'))
			} finally {
				this.isLoading = false
			}
		},

		async addPublicLink() {
			this.isLoading = true

			try {
				const response = await axios.post(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares', {
						id: this.form.id,
					}),
					{
						shareType: this.SHARE_TYPES.SHARE_TYPE_LINK,
					},
				)
				const share = OcsResponse2Data(response)

				// Add new share
				this.$emit('add-share', share)
			} catch (error) {
				logger.error('Error adding public link', { error })
				showError(t('forms', 'There was an error while adding the link'))
			} finally {
				this.isLoading = false
			}
		},

		/**
		 * Make a share embeddable into websites (sets the internal permission)
		 * @param {{ permissions: string[] }} share The public link share to make embeddable
		 */
		makeEmbeddable(share) {
			this.updateShare({
				...share,
				permissions: [
					...share.permissions,
					this.PERMISSION_TYPES.PERMISSION_EMBED,
				],
			})
		},

		/**
		 * Update share
		 *
		 * @param {object} updatedShare the updated object
		 */
		async updateShare(updatedShare) {
			this.isLoading = true

			try {
				const response = await axios.patch(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares/{shareId}', {
						id: this.form.id,
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
				this.$emit('update-share', share)
			} catch (error) {
				logger.error('Error while updating share', {
					error,
					share: updatedShare,
				})
				showError(t('forms', 'There was an error while updating the share'))
			} finally {
				this.isLoading = false
			}
		},

		/**
		 * Remove share
		 *
		 * @param {object} share the share to delete
		 */
		async removeShare(share) {
			this.isLoading = true

			try {
				await axios.delete(
					generateOcsUrl('apps/forms/api/v3/forms/{id}/shares/{shareId}', {
						id: this.form.id,
						shareId: share.id,
					}),
				)
				this.$emit('remove-share', share)
			} catch (error) {
				logger.error('Error while removing share', { error, share })
				showError(t('forms', 'There was an error while removing the share'))
			} finally {
				this.isLoading = false
			}
		},

		/**
		 * Sort by shareType and DisplayName
		 *
		 * @param {object} a first share for comparison
		 * @param {object} b second share for comparison
		 */
		sortByTypeAndDisplayname(a, b) {
			// First return, if ShareType does not match
			if (a.shareType < b.shareType) return -1
			if (a.shareType > b.shareType) return 1

			// Otherwise sort by displayname
			if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
			if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
			return 0
		},

		onPermitAllUsersChange(newVal) {
			const newAccess = { ...this.form.access }
			newAccess.permitAllUsers = newVal
			this.$emit('update:formProp', 'access', newAccess)
		},
		onShowToAllUsersChange(newVal) {
			const newAccess = { ...this.form.access }
			newAccess.showToAllUsers = newVal
			this.$emit('update:formProp', 'access', newAccess)
		},

		openQrDialog(share) {
			this.qrDialogText = this.getPublicShareLink(share)
		},
	},
}
</script>

<style lang="scss" scoped>
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
