<!--
  - @copyright Copyright (c) 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
	<NcContent app-name="forms" class="forms-emptycontent">
		<NcEmptyContent :title="currentModel.title"
			:description="currentModel.description">
			<template #icon>
				<Icon :is="currentModel.icon" :size="64" />
			</template>
		</NcEmptyContent>
	</NcContent>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import IconCheck from 'vue-material-design-icons/Check.vue'
import FormsIcon from './components/Icons/FormsIcon.vue'

export default {
	name: 'FormsEmptyContent',

	components: {
		FormsIcon,
		IconCheck,
		NcContent,
		NcEmptyContent,
	},

	data() {
		return {
			/**
			 * !! Keep Model-Names in sync with Constants EMTPY_... in lib/Constants.php !!
			 * Models for each EmptyContent rendering taking resp. title and subtitle
			 */
			renderModels: {
				notfound: {
					title: t('forms', 'Form not found'),
					description: t('forms', 'This form does not exist'),
					icon: FormsIcon,
				},
				expired: {
					title: t('forms', 'Form expired'),
					description: t('forms', 'This form has expired and is no longer taking answers'),
					icon: IconCheck,
				},
			},
			renderAs: loadState(appName, 'renderAs'),
		}
	},

	computed: {
		currentModel() {
			return this.renderModels[this.renderAs]
		},
	},
}
</script>

<style lang="scss" scoped>
.forms-emptycontent {
	flex-basis: 100vw;
	flex-direction: column;
}
</style>
