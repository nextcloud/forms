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
	<li>
		<div>{{ option.timestamp | localFullDate }}</div>
		<div>
			<a class="icon-delete" @click="$emit('remove')" />
		</div>
	</li>
</template>

<script>
import moment from 'moment'

export default {
	filters: {
		localFullDate(timestamp) {
			if (!timestamp) return ''
			if (!moment(timestamp).isValid()) return 'Invalid Date'
			if (timestamp < 999999999999) timestamp = timestamp * 1000
			return moment(timestamp).format('llll')
		}
	},
	props: {
		option: {
			type: Object,
			default: undefined
		}

	}
}
</script>
