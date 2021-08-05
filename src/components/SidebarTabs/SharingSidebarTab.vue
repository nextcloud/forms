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
		<Multiselect>
			:options="options"
		</Multiselect>
		<!-- <Multiselect
			:clear-on-select="false"
			:hide-selected="true"
			:internal-search="false"
			:loading="loading"
			:multiple="true"
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
		</Multiselect> -->

		Share Link
		Sharees
		Project
	</div>
</template>

<script>
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import debounce from 'debounce'

export default {
	components: {
		Multiselect
	},

	computed: {

	},

	methods: {
	}
}
</script>
