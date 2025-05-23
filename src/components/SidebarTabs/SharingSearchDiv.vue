<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcSelect
			:clear-search-on-select="false"
			:close-on-select="false"
			:loading="showLoadingCircle"
			:get-option-key="(option) => option.key"
			:options="options"
			:placeholder="t('forms', 'Search for user, group or team …')"
			user-select
			:filter-by="() => true"
			label="displayName"
			:aria-label-combobox="t('forms', 'Search for user, group or team …')"
			@search="asyncSearch"
			@input="addShare">
			<template #no-options>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import NcSelect from '@nextcloud/vue/components/NcSelect'

import UserSearchMixin from '../../mixins/UserSearchMixin.js'

export default {
	components: {
		NcSelect,
	},

	mixins: [UserSearchMixin],

	props: {
		currentShares: {
			type: Array,
			default: () => [],
		},
		showLoading: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		/**
		 * Multiselect options. Recommendations by default, direct search when search query is valid.
		 * Filter out existing shares
		 *
		 * @return {Array}
		 */
		options() {
			if (this.isValidQuery) {
				// Suggestions without existing shares
				return this.suggestions.filter(
					(item) =>
						!this.currentShares.find(
							(share) =>
								share.shareWith === item.shareWith
								&& share.shareType === item.shareType,
						),
				)
			}
			// Recommendations without existing shares
			return this.recommendations.filter(
				(item) =>
					!this.currentShares.find(
						(share) =>
							share.shareWith === item.shareWith
							&& share.shareType === item.shareType,
					),
			)
		},

		/**
		 * Show Loading if loading is either set by parent or by this module (search)
		 */
		showLoadingCircle() {
			return this.showLoading || this.loading
		},
	},

	mounted() {
		// Preloading recommendations
		this.getRecommendations()
	},

	methods: {
		/**
		 * Format share for form.shares and add it.
		 *
		 * @param {object} share New share to share with, format still for multiselect.
		 */
		addShare(share) {
			const newShare = {
				shareWith: share.shareWith,
				displayName: share.displayName,
				shareType: share.shareType,
			}
			this.$emit('add-share', newShare)
		},
	},
}
</script>

<style lang="scss" scoped>
.select {
	margin-block-end: 8px !important;
	width: 100%;
}
</style>
