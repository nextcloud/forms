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
		<Multiselect :clear-on-select="false"
			:close-on-select="false"
			:hide-selected="true"
			:internal-search="false"
			:loading="loading"
			:options="options"
			:placeholder="t('forms', 'Search for user or group …')"
			:preselect-first="true"
			:searchable="true"
			:user-select="true"
			label="displayName"
			track-by="shareWith"
			@search-change="asyncSearch"
			@select="addShare">
			<template #noOptions>
				{{ t('forms', 'No recommendations. Start typing.') }}
			</template>
			<template #noResult>
				{{ noResultText }}
			</template>
		</Multiselect>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

import OcsResponse2Data from '../../utils/OcsResponse2Data'
import ShareTypes from '../../mixins/ShareTypes'

export default {
	components: {
		Multiselect,
	},

	mixins: [ShareTypes],

	props: {
		currentShares: {
			type: Array,
			default: () => ([]),
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
			if (this.loading) {
				return t('forms', 'Searching …')
			}
			return t('forms', 'No elements found.')
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
			console.debug('Search', query)
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

			// Types to search for
			const shareType = [
				this.SHARE_TYPES.SHARE_TYPE_USER,
				this.SHARE_TYPES.SHARE_TYPE_GROUP,
			]

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
			data.exact = [] // removing exact from general results

			// flatten array of arrays
			const rawExactSuggestions = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
			const rawSuggestions = Object.values(data).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			const exactSuggestions = this.filterUnwantedShares(rawExactSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
			const suggestions = this.filterUnwantedShares(rawSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)

			this.suggestions = exactSuggestions.concat(suggestions)

			this.loading = false
		},

		/**
		 * Get the sharing recommendations
		 */
		async getRecommendations() {
			this.loading = true

			const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees_recommended'), {
				params: {
					format: 'json',
					itemType: 'file',
				},
			})
			const rawRecommendations = OcsResponse2Data(request).exact

			// flatten array of arrays
			const flatRecommendations = Object.values(rawRecommendations).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			this.recommendations = this.filterUnwantedShares(flatRecommendations)
				.map(share => this.formatForMultiselect(share))

			this.loading = false
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
				user: share.uuid || share.value.shareWith,
				isNoUser: share.value.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER,
				displayName: share.name || share.label,
				icon: this.shareTypeToIcon(share.value.shareType),
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: share.uuid || share.value.shareWith + '-' + share.value.shareType + '-' + share.name || share.label,
			}
		},
	},
}
</script>

<style lang="scss" scoped>
	.multiselect {
		margin-bottom: 8px !important;
		width: 100%;
	}
</style>
