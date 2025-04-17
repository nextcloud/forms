/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import debounce from 'debounce'

import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.js'
import logger from '../utils/Logger.js'
import ShareTypes from './ShareTypes.js'

export default {
	mixins: [ShareTypes],
	data() {
		return {
			loading: false,

			query: '',
			// TODO: have a global mixin for this, shared with server?
			maxAutocompleteResults:
				parseInt(OC.config['sharing.maxAutocompleteResults'], 10) || 200,
			minSearchStringLength:
				parseInt(OC.config['sharing.minSearchStringLength'], 10) || 0,
			// Search Results
			recommendations: [],
			suggestions: [],
		}
	},
	computed: {
		/**
		 * Is the search query valid ?
		 *
		 * @return {boolean}
		 */
		isValidQuery() {
			return (
				this.query
				&& this.query.trim() !== ''
				&& this.query.length > this.minSearchStringLength
			)
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
	},
	methods: {
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
				this.debounceGetSuggestions(query)
			}
		},

		/**
		 * Debounce getSuggestions
		 *
		 * @param {...*} args arguments to pass
		 */
		debounceGetSuggestions: debounce(function (...args) {
			this.getSuggestions(...args)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Get suggestions
		 *
		 * @param {string} query the search query
		 */
		async getSuggestions(query) {
			this.loading = true

			// Search for all used share-types, except public link.
			const shareType = this.SHARE_TYPES_USED.filter(
				(type) => type !== this.SHARE_TYPES.SHARE_TYPE_LINK,
			)

			try {
				const request = await axios.get(
					generateOcsUrl('apps/files_sharing/api/v1/sharees'),
					{
						params: {
							format: 'json',
							itemType: 'file',
							perPage: this.maxAutocompleteResults,
							search: query,
							shareType,
						},
					},
				)

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
				const request = await axios.get(
					generateOcsUrl('apps/files_sharing/api/v1/sharees_recommended'),
					{
						params: {
							format: 'json',
							itemType: 'file',
						},
					},
				)

				this.recommendations = this.formatSearchResults(
					OcsResponse2Data(request).exact,
				)
			} catch (error) {
				logger.error('Fetching recommendations failed.', { error })
			} finally {
				this.loading = false
			}
		},
		/**
		 * A OCS Sharee response
		 * @typedef {{label: string, shareWithDisplayNameUnique: string, value: { shareType: number, shareWith: string }, status?: unknown }} Sharee
		 */

		/**
		 * Format search results
		 *
		 * @param {Record<string, Sharee>} results Results as returned by search
		 * @return {Sharee[]} results as we use them on storage
		 */
		formatSearchResults(results) {
			// flatten array of arrays
			const flatResults = Object.values(results).flat()

			return (
				this.filterUnwantedShares(flatResults)
					.map((share) => this.formatForMultiselect(share))
					// sort by type so we can get user&groups first...
					.sort((a, b) => a.shareType - b.shareType)
			)
		},

		/**
		 * Remove static unwanted shares from search results
		 * Existing shares must be done dynamically to account for new shares.
		 *
		 * @param {Sharee[]} shares the array of share objects
		 * @return {Sharee[]}
		 */
		filterUnwantedShares(shares) {
			return shares.filter((share) => {
				// only use proper objects
				if (typeof share !== 'object') {
					return false
				}

				try {
					// filter out current user
					if (
						share.value.shareType === this.SHARE_TYPES.SHARE_TYPE_USER
						&& share.value.shareWith === getCurrentUser().uid
					) {
						return false
					}

					// All good, let's add the suggestion
					return true
				} catch {
					return false
				}
			})
		},

		/**
		 * Format shares for the multiselect options
		 *
		 * @param {Sharee} share Share in search formatting
		 * @return {object} Share in multiselect formatting
		 */
		formatForMultiselect(share) {
			return {
				shareWith: share.value.shareWith,
				shareType: share.value.shareType,
				user: share.value.shareWith,
				isNoUser: share.value.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER,
				id: share.value.shareWith,
				displayName: share.label,
				subname: share.shareWithDisplayNameUnique,
				iconSvg: this.shareTypeToIcon(share.value.shareType),
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: share.value.shareWith + '-' + share.value.shareType,
			}
		},
	},
}
