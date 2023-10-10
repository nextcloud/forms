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
		<NcSelect :clear-search-on-select="false"
			:close-on-select="false"
			:loading="showLoadingCircle"
			:get-option-key="(option) => option.key"
			:options="options"
			:placeholder="t('forms', 'Search for user, group or circle …')"
			:user-select="true"
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
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import debounce from 'debounce'

import OcsResponse2Data from '../../utils/OcsResponse2Data.js'
import ShareTypes from '../../mixins/ShareTypes.js'
import logger from '../../utils/Logger.js'

export default {
	components: {
		NcSelect,
	},

	mixins: [ShareTypes],

	props: {
		currentShares: {
			type: Array,
			default: () => ([]),
		},
		showLoading: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			loading: false,
			query: '',

			// TODO: have a global mixin for this, shared with server?
			minSearchStringLength: parseInt(OC.config['sharing.minSearchStringLength'], 10) || 0,
			maxAutocompleteResults: parseInt(OC.config['sharing.maxAutocompleteResults'], 10) || 200,

			// Search Results
			recommendations: [],
			suggestions: [],
		}
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
				return this.suggestions.filter(item => !this.currentShares.find(share => share.shareWith === item.shareWith && share.shareType === item.shareType))
			}
			// Recommendations without existing shares
			return this.recommendations.filter(item => !this.currentShares.find(share => share.shareWith === item.shareWith && share.shareType === item.shareType))
		},

		/**
		 * Is the search query valid ?
		 *
		 * @return {boolean}
		 */
		isValidQuery() {
			return this.query && this.query.trim() !== '' && this.query.length > this.minSearchStringLength
		},

		/**
		 * Text when there is no Results to be shown
		 *
		 * @return {string}
		 */
		noResultText() {
			if (!this.query) {
				return t('forms', 'No recommendations. Start typing.')
			}
			return t('forms', 'No elements found.')
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

		/**
		 * Search for suggestions
		 *
		 * @param {string} query The search query to search for
		 */
		async asyncSearch(query) {
			// save query to check if valid
			this.query = query.trim()
			if (this.isValidQuery) {
				// already set loading to have proper ux feedback during debounce
				this.loading = true
				await this.debounceGetSuggestions(query)
			}
		},

		/**
		 * Debounce getSuggestions
		 *
		 * @param {...*} args arguments to pass
		 */
		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

		/**
		 * Get suggestions
		 *
		 * @param {string} query the search query
		 */
		async getSuggestions(query) {
			this.loading = true

			// Search for all used share-types, except public link.
			const shareType = this.SHARE_TYPES_USED.filter(type => type !== this.SHARE_TYPES.SHARE_TYPE_LINK)

			try {
				const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), {
					params: {
						format: 'json',
						itemType: 'file',
						perPage: this.maxAutocompleteResults,
						search: query,
						shareType,
					},
				})

				const data = OcsResponse2Data(request)
				const exact = data.exact
				delete data.exact // removing exact from general results

				const exactSuggestions = this.formatSearchResults(exact)
				const suggestions = this.formatSearchResults(data)

				this.suggestions = exactSuggestions.concat(suggestions)
			} catch (error) {
				logger.error('Loading Suggestions failed.', { error })
			} finally {
				this.loading = false
			}
		},

		/**
		 * Get the sharing recommendations
		 */
		async getRecommendations() {
			this.loading = true

			try {
				const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees_recommended'), {
					params: {
						format: 'json',
						itemType: 'file',
					},
				})

				this.recommendations = this.formatSearchResults(OcsResponse2Data(request).exact)
			} catch (error) {
				logger.error('Fetching recommendations failed.', { error })
			} finally {
				this.loading = false
			}
		},

		/**
		 * Format search results
		 *
		 * @param {object[]} results Results as returned by search
		 * @return {object[]} results as we use them on storage
		 */
		formatSearchResults(results) {
			// flatten array of arrays
			const flatResults = Object.values(results).reduce((arr, elem) => arr.concat(elem), [])

			return this.filterUnwantedShares(flatResults)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
		},

		/**
		 * Remove static unwanted shares from search results
		 * Existing shares must be done dynamically to account for new shares.
		 *
		 * @param {object[]} shares the array of share objects
		 * @return {object[]}
		 */
		filterUnwantedShares(shares) {
			return shares.reduce((arr, share) => {
				// only use proper objects
				if (typeof share !== 'object') {
					return arr
				}

				try {
					// filter out current user
					if (share.value.shareType === this.SHARE_TYPES.SHARE_TYPE_USER
						&& share.value.shareWith === getCurrentUser().uid) {
						return arr
					}

					// All good, let's add the suggestion
					arr.push(share)
				} catch {
					return arr
				}
				return arr
			}, [])
		},

		/**
		 * Format shares for the multiselect options
		 *
		 * @param {object} share Share in search formatting
		 * @return {object} Share in multiselect formatting
		 */
		formatForMultiselect(share) {
			return {
				shareWith: share.value.shareWith,
				shareType: share.value.shareType,
				user: share.value.shareWith,
				isNoUser: share.value.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER,
				displayName: share.label,
				icon: this.shareTypeToIcon(share.value.shareType),
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: share.value.shareWith + '-' + share.value.shareType,
			}
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
