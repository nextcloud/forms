<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<AppNavigationItem
		:exact="true"
		:title="form.title"
		:to="{ name: 'edit', params: { hash: form.hash } }">
		<AppNavigationIconBullet slot="icon" :color="bulletColor" />
		<template #actions>
			<ActionRouter :close-after-click="true"
				:exact="true"
				icon="icon-checkmark"
				:to="{ name: 'results', params: { hash: form.hash } }">
				{{ t('forms', 'Show results') }}
			</ActionRouter>
			<ActionRouter :close-after-click="true"
				:exact="true"
				icon="icon-clone"
				:to="{ name: 'clone', params: { hash: form.hash } }">
				{{ t('forms', 'Clone form') }}
			</ActionRouter>
			<ActionSeparator />
			<ActionButton :close-after-click="true" icon="icon-delete" @click="deleteForm">
				{{ t('forms', 'Delete form') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationIconBullet from '@nextcloud/vue/dist/Components/AppNavigationIconBullet'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionRouter from '@nextcloud/vue/dist/Components/ActionRouter'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'

export default {
	name: 'AppNavigationForm',

	components: {
		AppNavigationItem,
		AppNavigationIconBullet,
		ActionButton,
		ActionRouter,
		ActionSeparator,
	},

	props: {
		form: {
			type: Object,
			required: true,
		},
	},

	computed: {
		/**
		 * Map form state to bullet color
		 *
		 * @returns {string} hex color
		 */
		bulletColor() {
			const style = getComputedStyle(document.body)
			if (this.form.expired) {
				return style.getPropertyValue('--color-error').slice(-6)
			}
			return style.getPropertyValue('--color-success').slice(-6)
		},
	},

	methods: {
		async deleteForm() {

		},
	},

}
</script>
