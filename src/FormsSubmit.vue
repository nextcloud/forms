<!--
  - @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
  -
  - @license AGPL-3.0-or-later
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
	<NcContent app-name="forms" :class="{'app-forms-embedded': isEmbedded}">
		<Submit :form="form"
			:public-view="true"
			:share-hash="shareHash"
			:is-logged-in="isLoggedIn" />
	</NcContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import Submit from './views/Submit.vue'

export default {
	name: 'FormsSubmit',

	components: {
		NcContent,
		Submit,
	},

	data() {
		return {
			form: loadState('forms', 'form'),
			isLoggedIn: loadState('forms', 'isLoggedIn'),
			isEmbedded: loadState('forms', 'isEmbedded', false),
			shareHash: loadState('forms', 'shareHash'),
		}
	},
}
</script>
