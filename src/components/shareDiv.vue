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
	<div>
		<h2> {{ t('forms', 'Share with') }}</h2>

		<Multiselect :clear-on-select="false"
					 :internal-search="false"
					 :loading="isLoading"
					 :multiple="true"
					 :options="users"
					 :options-limit="20"
					 :placeholder="placeholder"
					 :preselect-first="true"
					 :preserve-search="true"
					 :searchable="true"
					 :tag-width="80"
					 :user-select="true"
					 @close="updateShares"
					 @search-change="loadUsersAsync"
					 id="ajax"
					 label="displayName"
					 track-by="user"
					 v-model="shares"
		>
			<template slot="selection" slot-scope="{ values, search, isOpen }">
				<span class="multiselect__single"
					  v-if="values.length &amp;&amp; !isOpen">
					{{ values.length }} users selected
				</span>
			</template>
		</Multiselect>

		<TransitionGroup :css="false" class="shared-list" tag="ul">
			<li :data-index="index" :key="item.displayName"
				v-for="(item, index) in sortedShares">
				<UserDiv :display-name="item.displayName" :hide-names="hideNames"
						 :type="item.type"
						 :user-id="item.user"
				/>
				<div class="options">
					<a @click="removeShare(index, item)"
					   class="icon icon-delete svg delete-form"/>
				</div>
			</li>
		</TransitionGroup>
	</div>
</template>

<script>
	import { Multiselect } from 'nextcloud-vue'
	import axios from 'nextcloud-axios'

	export default {
		components: {
			Multiselect
		},

		props: {
			placeholder: {
				type: String,
				default: ''
			},

			activeShares: {
				type: Array,
				default: function() {
					return []
				}
			},

			hideNames: {
				type: Boolean,
				default: false
			}
		},

		data() {
			return {
				shares: [],
				users: [],
				isLoading: false,
				siteUsersListOptions: {
					getUsers: true,
					getGroups: true,
					query: ''
				}
			}
		},

		computed: {
			sortedShares() {
				return this.shares.slice(0).sort(this.sortByDisplayname)
			}
		},

		watch: {
			activeShares(value) {
				this.shares = value.slice(0)
			}
		},

		methods: {
			removeShare(index, item) {
				this.$emit('remove-share', item)
			},

			updateShares() {
				this.$emit('update-shares', this.shares)
			},

			loadUsersAsync(query) {
				this.isLoading = false
				this.siteUsersListOptions.query = query
				axios.post(OC.generateUrl('apps/forms/get/siteusers'), this.siteUsersListOptions)
					.then((response) => {
						this.users = response.data.siteusers
						this.isLoading = false
					}, (error) => {
						/* eslint-disable-next-line no-console */
						console.log(error.response)
					})
			},

			sortByDisplayname(a, b) {
				if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
				if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
				return 0
			}

		}
	}
</script>

<style lang="scss">
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
