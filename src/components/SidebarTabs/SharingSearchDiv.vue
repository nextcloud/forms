<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<NcSelectUsers
			keepOpen
			:loading="showLoadingCircle"
			:disabled="locked || !isCurrentUserOwner"
			:options="options"
			:placeholder="t('forms', 'Search for user, group or team …')"
			:aria-label-listbox="t('forms', 'Search for user, group or team …')"
			@search="asyncSearch"
			@update:modelValue="addShare">
			<template #no-options>
				{{ noResultText }}
			</template>
		</NcSelectUsers>
	</div>
</template>

<script lang="ts">
import { t } from '@nextcloud/l10n'
import { computed, defineComponent, onMounted } from 'vue'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import { useUserSearch } from '../../composables/useUserSearch.ts'

interface CurrentShareLike {
	shareWith: string
	shareType: number
}

export default defineComponent({
	components: {
		NcSelectUsers,
	},

	props: {
		currentShares: {
			type: Array,
			default: () => [],
		},

		showLoading: {
			type: Boolean,
			default: false,
		},

		locked: {
			type: Boolean,
			required: true,
		},

		isCurrentUserOwner: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['addShare'],

	setup(props, { emit }) {
		const userSearch = useUserSearch()
		const options = computed(() => {
			const currentShares = props.currentShares as CurrentShareLike[]
			const source = userSearch.isValidQuery.value
				? userSearch.suggestions.value
				: userSearch.recommendations.value

			return source.filter(
				(item) =>
					!currentShares.find(
						(share) =>
							share.shareWith === item.shareWith
							&& share.shareType === item.shareType,
					),
			)
		})
		const showLoadingCircle = computed(
			() => props.showLoading || userSearch.loading.value,
		)

		/**
		 * Format share for form.shares and add it.
		 *
		 * @param share New share to share with, format still for multiselect.
		 */
		const addShare = (share: unknown): void => {
			const selectedShare = Array.isArray(share) ? share[0] : share
			if (!selectedShare || typeof selectedShare !== 'object') {
				return
			}
			if (!('shareWith' in selectedShare) || !('shareType' in selectedShare)) {
				return
			}

			const newShare = {
				shareWith: String(selectedShare.shareWith),

				displayName:
					'displayName' in selectedShare
						? String(selectedShare.displayName)
						: String(selectedShare.shareWith),

				shareType: Number(selectedShare.shareType),
			}
			emit('addShare', newShare)
		}

		// Preloading recommendations
		onMounted(() => {
			void userSearch.getRecommendations()
		})

		return {
			...userSearch,
			addShare,
			options,
			showLoadingCircle,
			t,
		}
	},
})
</script>

<style lang="scss" scoped>
.select {
	margin-block-end: 8px !important;
	width: 100%;
}
</style>
