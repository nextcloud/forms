<!--
  - @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<div class="sidebar-tabs__content">
		<SharingSearchDiv :current-shares="form.shares"
			:show-loading="isLoading"
			@add-share="addShare" />

		<!-- Public Link -->
		<div v-if="!hasPublicLink && appConfig.allowPublicLink" class="share-div share-div--link">
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
			<div v-for="share in publicLinkShares"
				:key="'share-' + share.shareType + '-' + share.shareWith"
				class="share-div share-div--link">
				<div class="share-div__avatar">
					<IconLinkVariant :size="20" />
				</div>
				<span class="share-div__desc">{{ t('forms', 'Share link') }}</span>
				<NcActions>
					<NcActionButton @click="copyPublicShareLink($event, share.shareWith)">
						<template #icon>
							<IconCopyAll :size="20" />
						</template>
						{{ t('forms', 'Copy to clipboard') }}
					</NcActionButton>
				</NcActions>
				<NcActions>
					<NcActionButton @click="removeShare(share)">
						<template #icon>
							<IconDelete :size="20" />
						</template>
						{{ t('forms', 'Remove link') }}
					</NcActionButton>
					<NcActionButton v-if="appConfig.allowPublicLink"
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

		<!-- Legacy Info, if present -->
		<div v-if="form.access.legacyLink" class="share-div">
			<div class="share-div__avatar">
				<IconLinkVariant :size="20" />
			</div>
			<div class="share-div__desc share-div__desc--twoline">
				<span>{{ t('forms', 'Legacy Link') }}</span>
				<span>{{ t('forms', 'Form still supports old sharing-link.') }}</span>
			</div>
			<div v-tooltip="t('forms', 'For compatibility with the old Sharing, the internal link is still usable as Share link. We recommend replacing the link with a new Share link.')"
				class="share-div__legacy-warning">
				<IconAlertCircleOutline :size="20" />
			</div>
			<NcActions>
				<NcActionButton @click="removeLegacyLink">
					<template #icon>
						<IconDelete :size="20" />
					</template>
					{{ t('forms', 'Remove Legacy Link') }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Internal link -->
		<div class="share-div">
			<div class="share-div__avatar">
				<IconLinkVariant :size="20" />
			</div>
			<div class="share-div__desc share-div__desc--twoline">
				<span>{{ t('forms', 'Internal link') }}</span>
				<span>{{ t('forms', 'Only works for logged in accounts with access rights') }}</span>
			</div>
			<NcActions>
				<NcActionButton @click="copyInternalShareLink($event, form.hash)">
					<template #icon>
						<IconCopyAll :size="20" />
					</template>
					{{ t('forms', 'Copy to clipboard') }}
				</NcActionButton>
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
				<NcCheckboxRadioSwitch id="share-switch__permit-all"
					:checked="form.access.permitAllUsers"
					type="switch"
					@update:checked="onPermitAllUsersChange" />
			</div>
			<div v-if="form.access.permitAllUsers" class="share-div share-div--indent">
				<div class="share-div__avatar">
					<FormsIcon :size="16" />
				</div>
				<label for="share-switch__show-to-all" class="share-div__desc">
					{{ t('forms', 'Show to all accounts on sidebar') }}
				</label>
				<NcCheckboxRadioSwitch id="share-switch__show-to-all"
					:checked="form.access.showToAllUsers"
					type="switch"
					@update:checked="onShowToAllUsersChange" />
			</div>
		</div>

		<!-- Single shares -->
		<TransitionGroup tag="ul">
			<SharingShareDiv v-for="share in sortedShares"
				:key="'share-' + share.shareType + '-' + share.shareWith"
				:share="share"
				@remove-share="removeShare"
				@set-responder="setResponder"
				@set-editor="setEditor" />
		</TransitionGroup>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconLinkVariant from 'vue-material-design-icons/LinkVariant.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'

import FormsIcon from '../Icons/FormsIcon.vue'
import IconCopyAll from '../Icons/IconCopyAll.vue'
import SharingSearchDiv from './SharingSearchDiv.vue'
import SharingShareDiv from './SharingShareDiv.vue'
import ShareTypes from '../../mixins/ShareTypes.js'
import ShareLinkMixin from '../../mixins/ShareLinkMixin.js'
import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import logger from '../../utils/Logger.js'

export default {
	components: {
		FormsIcon,
		IconAccountMultiple,
		IconAlertCircleOutline,
		IconCopyAll,
		IconDelete,
		IconLinkVariant,
		IconPlus,
		NcActions,
		NcActionButton,
		NcCheckboxRadioSwitch,
		SharingSearchDiv,
		SharingShareDiv,
	},

	mixins: [ShareTypes, ShareLinkMixin],

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
		}
	},

	computed: {
		sortedShares() {
			// Remove Link-Shares, which are handled separately, then sort
			return this.form.shares
				.filter(share => share.shareType !== this.SHARE_TYPES.SHARE_TYPE_LINK)
				.sort(this.sortByTypeAndDisplayname)
		},
		hasPublicLink() {
			return this.publicLinkShares.length !== 0
		},
		publicLinkShares() {
			return this.form.shares.filter(share => share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK)
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
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2/share'), {
					formId: this.form.id,
					shareType: newShare.shareType,
					shareWith: newShare.shareWith,
				})
				const share = OcsResponse2Data(response)

				// Add new share
				this.$emit('add-share', share)

			} catch (error) {
				logger.error('Error while adding new share', { error, share: newShare })
				showError(t('forms', 'There was an error while adding the share'))
			} finally {
				this.isLoading = false
			}
		},

		async addPublicLink() {
			this.isLoading = true

			try {
				const response = await axios.post(generateOcsUrl('apps/forms/api/v2/share'), {
					formId: this.form.id,
					shareType: this.SHARE_TYPES.SHARE_TYPE_LINK,
				})
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
		 * Remove share
		 *
		 * @param {object} share the share to delete
		 */
		async removeShare(share) {
			this.isLoading = true

			try {
				await axios.delete(generateOcsUrl('apps/forms/api/v2/share/{id}', {
					id: share.id,
				}))
				this.$emit('remove-share', share)
			} catch (error) {
				logger.error('Error while removing share', { error, share })
				showError(t('forms', 'There was an error while removing the share'))
			} finally {
				this.isLoading = false
			}
		},
		/**
		 *
		 * set as Responder
		 *
		 * @param {object} share the share of the user to set as responder
		 */
		async setResponder(share) {
			try {
				await axios.post(generateOcsUrl('apps/forms/api/v2/share/toggleEditor'), {
					formId: this.form.id,
					isEditor: false,
					uid: share.shareWith,
				})
			} catch (error) {
				logger.error('Error while setting share as responder', { error, share })
				showError(t('forms', 'There while setting share as responder'))
			}
		},
		/**
		 * set as Responder
		 *
		 * @param {object} share the share of the user to set as an editor
		 */
		async setEditor(share) {
			try {
				await axios.post(generateOcsUrl('apps/forms/api/v2/share/toggleEditor'), {
					formId: this.form.id,
					isEditor: true,
					uid: share.shareWith,
				})
			} catch (error) {
				logger.error('Error while setting share as responder', { error, share })
				showError(t('forms', 'There while setting share as responder'))
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
		removeLegacyLink() {
			const newAccess = { ...this.form.access }
			delete newAccess.legacyLink
			this.$emit('update:formProp', 'access', newAccess)
		},
	},
}
</script>

<style lang="scss" scoped>
.share-div {
	display: flex;
	min-height: 44px;
	align-items: center;

	&--link {
		.share-div__avatar {
			background-color: var(--color-primary);
			color: var(--color-primary-text);
		}
	}

	&--indent {
		margin-left: 40px;
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

	&__legacy-warning {
		background-size: 18px;
		margin-right: 4px;
		color: var(--color-error)
	}
}
</style>
