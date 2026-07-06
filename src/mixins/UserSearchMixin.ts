/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { defineComponent } from 'vue'
import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import ShareTypes from './ShareTypes.ts'

export interface Sharee {
	label: string
	shareWithDisplayNameUnique: string
	value: {
		shareType: number
		shareWith: string
	}
	status?: unknown
}

export interface SearchResultsResponse {
	exact: Sharee[]
	users?: Sharee[]
	groups?: Sharee[]
	[key: string]: Sharee[] | undefined
}

export interface FormattedSharee {
	shareWith: string
	shareType: number
	user: string
	isNoUser: boolean
	id: string
	displayName: string
	subname: string
	iconSvg: string
	key: string
}

export interface UserSearchMixinData {
	loading: boolean
	query: string
	maxAutocompleteResults: number
	minSearchStringLength: number
	recommendations: FormattedSharee[]
	suggestions: FormattedSharee[]
}

export default defineComponent({
	name: 'UserSearchMixin',

	mixins: [ShareTypes],

	data(): UserSearchMixinData {
		return {
			loading: false,

			query: '',
			// TODO: have a global mixin for this, shared with server?
			maxAutocompleteResults:
				parseInt(
					(window as any).OC.config['sharing.maxAutocompleteResults'],
					10,
				) || 200,
			minSearchStringLength:
				parseInt(
					(window as any).OC.config['sharing.minSearchStringLength'],
					10,
				) || 0,
			// Search Results
			recommendations: [],
			suggestions: [],
		}
	},

	computed: {
		/**
		 * Is the search query valid ?
		 */
		isValidQuery(): boolean {
			return (
				this.query
				&& this.query.trim() !== ''
				&& this.query.length > this.minSearchStringLength
			)
		},

		/**
		 * Text when there is no Results to be shown
		 */
		noResultText(): string {
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
		 * @param query The search query to search for
		 * @param shareType The type of recipient to search.
		 */
		async asyncSearch(query: string, shareType?: number[]): Promise<void> {
			// save query to check if valid
			this.query = query.trim()
			if (this.isValidQuery) {
				// already set loading to have proper ux feedback during debounce
				this.loading = true
				this.debounceGetSuggestions(query, shareType)
			}
		},

		/**
		 * Debounce getSuggestions
		 */
		debounceGetSuggestions: debounce(function (
			this: any,
			...args: [string, number[] | undefined]
		) {
			this.getSuggestions(...args)
		}, INPUT_DEBOUNCE_MS),

		/**
		 * Get suggestions
		 *
		 * @param query the search query
		 * @param shareType The type of recipient to search.
		 */
		async getSuggestions(query: string, shareType?: number[]): Promise<void> {
			this.loading = true

			// Search for all used share-types, except public link.

			shareType ??= (this as any).SHARE_TYPES_USED.filter(
				(type: number) => type !== (this as any).SHARE_TYPES.SHARE_TYPE_LINK,
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

				const data = OcsResponse2Data<SearchResultsResponse>(request)
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
		async getRecommendations(): Promise<void> {
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
					OcsResponse2Data<SearchResultsResponse>(request).exact,
				)
			} catch (error) {
				logger.error('Fetching recommendations failed.', { error })
			} finally {
				this.loading = false
			}
		},

		/**
		 * Format search results
		 *
		 * @param results Results as returned by search
		 * @return results as we use them on storage
		 */
		formatSearchResults(results: Record<string, Sharee[]>): FormattedSharee[] {
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
		 * @param shares the array of share objects
		 * @return
		 */
		filterUnwantedShares(shares: Sharee[]): Sharee[] {
			return shares.filter((share) => {
				// only use proper objects
				if (typeof share !== 'object') {
					return false
				}

				try {
					// filter out current user

					if (
						share.value.shareType
							=== (this as any).SHARE_TYPES.SHARE_TYPE_USER
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
		 * @param share Share in search formatting
		 * @return Share in multiselect formatting
		 */
		formatForMultiselect(share: Sharee): FormattedSharee {
			return {
				shareWith: share.value.shareWith,
				shareType: share.value.shareType,
				user: share.value.shareWith,

				isNoUser:
					share.value.shareType
					!== (this as any).SHARE_TYPES.SHARE_TYPE_USER,
				id: share.value.shareWith,
				displayName: share.label,
				subname: share.shareWithDisplayNameUnique,

				iconSvg: (this as any).shareTypeToIcon(share.value.shareType),
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: share.value.shareWith + '-' + share.value.shareType,
			}
		},
	},
})
