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
	<div>
		<NcSelect
			:clear-search-on-select="false"
			:close-on-select="false"
			:loading="showLoadingCircle"
			:get-option-key="(option) => option.key"
			:options="options"
			:placeholder="t('forms', 'Search for user, group or team …')"
			:user-select="true"
			:filter-by="() => true"
			label="displayName"
			@search="asyncSearch"
			@input="addShare">
			<template #no-options>
				{{ noResultText }}
			</template>
		</NcSelect>
	</div>
</template>

<script>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'

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
