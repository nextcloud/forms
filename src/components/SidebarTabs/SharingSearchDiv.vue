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
import { defineComponent } from 'vue'
import NcSelectUsers from '@nextcloud/vue/components/NcSelectUsers'
import UserSearchMixin from '../../mixins/UserSearchMixin.ts'

interface CurrentShareLike {
	shareWith: string
	shareType: number
}

interface SearchShareLike {
	shareWith: string
	displayName: string
	shareType: number
}

export default defineComponent({
	components: {
		NcSelectUsers,
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

	setup() {
		return {
			t,
		}
	},

	computed: {
		/**
		 * Multiselect options. Recommendations by default, direct search when search query is valid.
		 * Filter out existing shares
		 *
		 * @return
		 */
		options(): SearchShareLike[] {
			if (this.isValidQuery) {
				// Suggestions without existing shares
				const suggestions = this.suggestions as SearchShareLike[]
				const currentShares = this.currentShares as CurrentShareLike[]
				return suggestions.filter(
					(item) =>
						!currentShares.find(
							(share) =>
								share.shareWith === item.shareWith
								&& share.shareType === item.shareType,
						),
				)
			}
			// Recommendations without existing shares
			const recommendations = this.recommendations as SearchShareLike[]
			const currentShares = this.currentShares as CurrentShareLike[]
			return recommendations.filter(
				(item) =>
					!currentShares.find(
						(share) =>
							share.shareWith === item.shareWith
							&& share.shareType === item.shareType,
					),
			)
		},

		/**
		 * Show Loading if loading is either set by parent or by this module (search)
		 */
		showLoadingCircle(): boolean {
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
		 * @param share New share to share with, format still for multiselect.
		 */
		addShare(share: SearchShareLike | SearchShareLike[] | null): void {
			const selectedShare = Array.isArray(share) ? share[0] : share
			if (!selectedShare) {
				return
			}
			const newShare = {
				shareWith: selectedShare.shareWith,
				displayName: selectedShare.displayName,
				shareType: selectedShare.shareType,
			}
			this.$emit('addShare', newShare)
		},
	},
})
</script>

<style lang="scss" scoped>
.select {
	margin-block-end: 8px !important;
	width: 100%;
}
</style>
