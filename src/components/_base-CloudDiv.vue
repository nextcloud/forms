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

/* global Vue, oc_userconfig */
<template>
	<div class="cloud">
		<span v-if="options.expired" class="expired">
			{{ t('forms', 'Expired') }}
		</span>
		<span v-if="options.expires" class="open">
			{{ t('forms', 'Expires %n', 1, expirationDate) }}
		</span>
		<span v-else class="open">
			{{ t('forms', 'Expires never') }}
		</span>

		<span class="information">
			{{ options.access }}
		</span>
		<span v-if="options.isAnonymous" class="information">
			{{ t('forms', 'Anonymous form') }}
		</span>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'

export default {
	props: {
		options: {
			type: Object,
			default: undefined,
		},

	},

	computed: {
		expirationDate() {
			const date = moment(this.options.expirationDate, moment.localeData().longDateFormat('L')).fromNow()
			return date
		},
	},
}
</script>

<style scoped>
</style>
