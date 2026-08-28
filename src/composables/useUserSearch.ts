/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { ComputedRef, Ref } from 'vue'

import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateOcsUrl } from '@nextcloud/router'
import debounce from 'debounce'
import { computed, ref } from 'vue'
import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import logger from '../utils/Logger.ts'
import OcsResponse2Data from '../utils/OcsResponse2Data.ts'
import { useShareTypes } from './useShareTypes.ts'

export interface Sharee {
	label: string
	shareWithDisplayNameUnique: string
	value: {
		shareType: number
		shareWith: string
	}
	status?: string | number | boolean | null
}

export interface SearchResultsResponse {
	exact?: Sharee[]
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

export interface UseUserSearchResult {
	SHARE_TYPES: ReturnType<typeof useShareTypes>['SHARE_TYPES']
	loading: Ref<boolean>
	query: Ref<string>
	recommendations: Ref<FormattedSharee[]>
	suggestions: Ref<FormattedSharee[]>
	isValidQuery: ComputedRef<boolean>
	noResultText: ComputedRef<string>
	asyncSearch: (query: string, shareType?: number[]) => Promise<void>
	getRecommendations: () => Promise<void>
}

/**
 * Composition API search logic shared by sharing and ownership transfer UI.
 */
export function useUserSearch(): UseUserSearchResult {
	const { SHARE_TYPES, SHARE_TYPES_USED, shareTypeToIcon } = useShareTypes()

	const loading = ref(false)
	const query = ref('')
	const maxAutocompleteResults =
		parseInt(
			String(window.OC?.config?.['sharing.maxAutocompleteResults'] ?? ''),
			10,
		) || 200
	const minSearchStringLength =
		parseInt(
			String(window.OC?.config?.['sharing.minSearchStringLength'] ?? ''),
			10,
		) || 0
	const recommendations = ref<FormattedSharee[]>([])
	const suggestions = ref<FormattedSharee[]>([])

	const isValidQuery = computed<boolean>(() => {
		return (
			Boolean(query.value)
			&& query.value.trim() !== ''
			&& query.value.length > minSearchStringLength
		)
	})

	const noResultText = computed(() => {
		if (!query.value) {
			return t('forms', 'No recommendations. Start typing.')
		}
		return t('forms', 'No elements found.')
	})

	const filterUnwantedShares = (shares: Sharee[]): Sharee[] => {
		return shares.filter((share) => {
			if (typeof share !== 'object') {
				return false
			}

			try {
				if (
					share.value.shareType === SHARE_TYPES.SHARE_TYPE_USER
					&& share.value.shareWith === getCurrentUser()?.uid
				) {
					return false
				}

				return true
			} catch {
				return false
			}
		})
	}

	const formatForMultiselect = (share: Sharee): FormattedSharee => {
		return {
			shareWith: share.value.shareWith,
			shareType: share.value.shareType,
			user: share.value.shareWith,
			isNoUser: share.value.shareType !== SHARE_TYPES.SHARE_TYPE_USER,
			id: share.value.shareWith,
			displayName: share.label,
			subname: share.shareWithDisplayNameUnique,
			iconSvg: shareTypeToIcon(share.value.shareType),
			key: share.value.shareWith + '-' + share.value.shareType,
		}
	}

	const formatSearchResults = (
		results:
			SearchResultsResponse | Record<string, Sharee[] | undefined> | Sharee[],
	): FormattedSharee[] => {
		const flatResults: Sharee[] = Array.isArray(results)
			? results
			: (Object.values(results).filter(Boolean) as Sharee[][]).flat()

		return filterUnwantedShares(flatResults)
			.map((share) => formatForMultiselect(share))
			.sort((a, b) => a.shareType - b.shareType)
	}

	const getSuggestions = async (
		search: string,
		shareType?: number[],
	): Promise<void> => {
		loading.value = true

		shareType ??= SHARE_TYPES_USED.filter(
			(type) => type !== SHARE_TYPES.SHARE_TYPE_LINK,
		)

		try {
			const request = await axios.get(
				generateOcsUrl('apps/files_sharing/api/v1/sharees'),
				{
					params: {
						format: 'json',
						itemType: 'file',
						perPage: maxAutocompleteResults,
						search,
						shareType,
					},
				},
			)

			const data = OcsResponse2Data<SearchResultsResponse>(request)
			const exact = data.exact ?? []
			delete data.exact

			const exactSuggestions = formatSearchResults(exact)
			const dynamicSuggestions = formatSearchResults(data)

			suggestions.value = exactSuggestions.concat(dynamicSuggestions)
		} catch (error) {
			logger.error('Loading Suggestions failed.', { error })
		} finally {
			loading.value = false
		}
	}

	const debounceGetSuggestions = debounce(
		(search: string, shareType?: number[]) => {
			void getSuggestions(search, shareType)
		},
		INPUT_DEBOUNCE_MS,
	)

	const asyncSearch = async (
		search: string,
		shareType?: number[],
	): Promise<void> => {
		query.value = search.trim()
		if (isValidQuery.value) {
			loading.value = true
			debounceGetSuggestions(search, shareType)
		}
	}

	const getRecommendations = async (): Promise<void> => {
		loading.value = true

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

			recommendations.value = formatSearchResults(
				OcsResponse2Data<SearchResultsResponse>(request).exact ?? [],
			)
		} catch (error) {
			logger.error('Fetching recommendations failed.', { error })
		} finally {
			loading.value = false
		}
	}

	return {
		SHARE_TYPES,
		loading,
		query,
		recommendations,
		suggestions,
		isValidQuery,
		noResultText,
		asyncSearch,
		getRecommendations,
	}
}
