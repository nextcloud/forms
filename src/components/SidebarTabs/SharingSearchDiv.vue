<!--
  - @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
	<div>
		<Multiselect
			:options="options">

		</Multiselect>
		<button @click="getRecommendations">TEST</button>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import OcsResponse2Data from '../../utils/OcsResponse2Data'

export default {
	components: {
		Multiselect
	},

	data() {
		return {
			loading: false,

			// Search Results
			recommendations: [],
			suggestions: [],
		}
	},

	computed: {
		options() {
			return ['a', 'b', 'c']
		}

	},

	mounted() {
		// Preloading recommendations
		this.getRecommendations()
	},

	methods: {
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
console.debug(flatRecommendations)
			// remove invalid data and format to user-select layout
			this.recommendations = this.filterUnwantedShares(flatRecommendations)
				.map(share => this.formatForMultiselect(share))

			this.loading = false
			console.info('recommendations', this.recommendations)
		},

		/**
		 * Remove unwanted shares from search results
		 *
		 * @param {object[]} shares the array of share object
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

					//TODO FILTER out existing sharees

					// ALL GOOD
					// let's add the suggestion
					arr.push(share)
				} catch {
					return arr
				}
				return arr
			}, [])
		},
	}
}
</script>
