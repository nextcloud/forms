<!--
  - @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
  -
  - @author René Gieling <github@dartcafe.de>
  -
  - @license GNU AGPL version 3 or any later version
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
	<div class="sharing">
		<Multiselect id="ajax"
			:clear-on-select="false"
			:hide-selected="true"
			:internal-search="false"
			:loading="loading"
			:multiple="true"
			:options="options"
			:placeholder="t('forms', 'User or group name …')"
			:preselect-first="true"
			:preserve-search="true"
			:searchable="true"
			:user-select="true"
			label="displayName"
			track-by="shareWith"
			@search-change="asyncFind"
			@select="addShare">
			<template #noOptions>
				{{ t('forms', 'No recommendations. Start typing.') }}
			</template>
			<template #noResult>
				{{ noResultText }}
			</template>
		</Multiselect>

		<TransitionGroup :css="false" tag="ul" class="shared-list">
			<!-- TODO: Iterate two times, will be cleaner, one for users, one for groups -->
			<li v-for="(item, index) in sortedShares" :key="item.shareWith + '-' + item.shareType" :data-index="index">
				<UserDiv v-bind="item" />
				<div class="options">
					<a class="icon icon-delete svg delete-form" @click="removeShare(item)" />
				</div>
			</li>
		</TransitionGroup>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import ShareTypes from '../mixins/ShareTypes'
import OcsResponse2Data from '../utils/OcsResponse2Data'

// TODO: replace with same design as core sharing
import UserDiv from './UserDiv'

export default {
	components: {
		Multiselect,
		UserDiv,
	},

	mixins: [ShareTypes],

	props: {
		groupShares: {
			type: Array,
			default: () => ([]),
		},
		userShares: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			query: '',
			loading: false,

			// TODO: have a global mixin for this, shared with server?
			minSearchStringLength: parseInt(OC.config['sharing.minSearchStringLength'], 10) || 0,
			maxAutocompleteResults: parseInt(OC.config['sharing.maxAutocompleteResults'], 10) || 200,

			// Search data
			recommendations: [],
			suggestions: [],
		}
	},

	computed: {
		sortedShares() {
			return [...this.userShares, ...this.groupShares].slice()
				.sort(this.sortByDisplayname)
		},

		/**
		 * Is the search valid ?
		 *
		 * @return {boolean}
		 */
		isValidQuery() {
			return this.query && this.query.trim() !== '' && this.query.length > this.minSearchStringLength
		},

		/**
		 * Multiseelct options. Recommendations by default,
		 * direct search when search query is valid.
		 * Filter out existing shares
		 *
		 * @return {Array}
		 */
		options() {
			const shares = [...this.userShares, ...this.groupShares]
			if (this.isValidQuery) {
				// Filter out existing shares
				return this.suggestions.filter(item => !shares.find(share => share.shareWith === item.shareWith && share.shareType === item.shareType))
			}
			// Filter out existing shares
			return this.recommendations.filter(item => !shares.find(share => share.shareWith === item.shareWith && share.shareType === item.shareType))
		},

		noResultText() {
			if (this.loading) {
				return t('forms', 'Searching …')
			}
			return t('forms', 'No elements found.')
		},
	},

	mounted() {
		this.getRecommendations()
	},

	methods: {
		removeShare(item) {
			// Filter out the removed item
			const users = this.userShares.filter(user => !(user.shareWith === item.shareWith && item.shareType === this.SHARE_TYPES.SHARE_TYPE_USER))
			const groups = this.groupShares.filter(group => !(group.shareWith === item.shareWith && item.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP))
			this.$emit('update:shares', { users, groups })
		},

		/**
		 * Add a new share and dispatch the change to the parent
		 *
		 * @param {object} share the new share
		 */
		addShare(share) {
			const users = this.userShares.slice()
			const groups = this.groupShares.slice()
			const newShare = {
				shareWith: share.shareWith,
				displayName: share.displayName,
				shareType: share.shareType,
			}

			// TODO: detect if already present

			if (share.shareType === this.SHARE_TYPES.SHARE_TYPE_USER) {
				users.push(newShare)
			} else if (share.shareType === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
				groups.push(newShare)
			}

			console.debug('Adding new share', share, users, groups)
			this.$emit('update:shares', { users, groups })
		},

		sortByDisplayname(a, b) {
			if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
			if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
			return 0
		},

		async asyncFind(query) {
			// save current query to check if we display
			// recommendations or search results
			this.query = query.trim()
			if (this.isValidQuery) {
				// start loading now to have proper ux feedback
				// during the debounce
				this.loading = true
				await this.debounceGetSuggestions(query)
			}
		},

		/**
		 * Get suggestions
		 *
		 * @param {string} search the search query
		 */
		async getSuggestions(search) {
			this.loading = true

			const shareType = [
				this.SHARE_TYPES.SHARE_TYPE_USER,
				this.SHARE_TYPES.SHARE_TYPE_GROUP,
			]

			const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), {
				params: {
					format: 'json',
					itemType: 'file',
					search,
					perPage: this.maxAutocompleteResults,
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
			const exactSuggestions = this.filterOutUnwantedShares(rawExactSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
			const suggestions = this.filterOutUnwantedShares(rawSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)

			this.suggestions = exactSuggestions.concat(suggestions)

			this.loading = false
			console.info('suggestions', this.suggestions)
		},

		/**
		 * Debounce getSuggestions
		 *
		 * @param {...*} args the arguments
		 */
		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

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

			const exact = OcsResponse2Data(request).exact

			// flatten array of arrays
			const rawRecommendations = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			this.recommendations = this.filterOutUnwantedShares(rawRecommendations)
				.map(share => this.formatForMultiselect(share))

			this.loading = false
			console.info('recommendations', this.recommendations)
		},

		/**
		 * Filter out unwated shares
		 *
		 * @param {object[]} shares the array of shares object
		 * @return {object[]}
		 */
		filterOutUnwantedShares(shares) {
			return shares.reduce((arr, share) => {
				// only check proper objects
				if (typeof share !== 'object') {
					return arr
				}

				try {
					// filter out current user
					if (share.value.shareType === this.SHARE_TYPES.SHARE_TYPE_USER
						&& share.value.shareWith === getCurrentUser().uid) {
						return arr
					}

					// ALL GOOD
					// let's add the suggestion
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
		 * @param {object} result select entry item
		 * @return {object}
		 */
		formatForMultiselect(result) {
			return {
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				user: result.uuid || result.value.shareWith,
				isNoUser: result.value.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER,
				displayName: result.name || result.label,
				icon: this.shareTypeToIcon(result.value.shareType),
				// Vue unique binding to render within Multiselect's AvatarSelectOption
				key: result.uuid || result.value.shareWith + '-' + result.value.shareType + '-' + result.name || result.label,
			}
		},

		/**
		 * Get the icon based on the share type
		 *
		 * @param {number} type the share type
		 * @return {string} the icon class
		 */
		shareTypeToIcon(type) {
			switch (type) {
			case this.SHARE_TYPES.SHARE_TYPE_GUEST:
				// default is a user, other icons are here to differenciate
				// themselves from it, so let's not display the user icon
				// case this.SHARE_TYPES.SHARE_TYPE_REMOTE:
				// case this.SHARE_TYPES.SHARE_TYPE_USER:
				return 'icon-user'
			case this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP:
			case this.SHARE_TYPES.SHARE_TYPE_GROUP:
				return 'icon-group'
			case this.SHARE_TYPES.SHARE_TYPE_EMAIL:
				return 'icon-mail'
			case this.SHARE_TYPES.SHARE_TYPE_CIRCLE:
				return 'icon-circle'
			case this.SHARE_TYPES.SHARE_TYPE_ROOM:
				return 'icon-room'

			default:
				return ''
			}
		},

	},
}
</script>

<style lang="scss" scoped>
	.sharing {
		margin: 8px 8px 8px 36px;
	}

	.shared-list {
		display: flex;
		flex-wrap: wrap;
		justify-content: flex-start;
		padding-top: 8px;

		> li {
			display: flex;
		}
	}

	.options {
		display: flex;
		position: relative;
		top: -12px;
		left: -13px;
	}

	.multiselect {
		width: 100% !important;
		max-width: 100% !important;
	}
</style>
