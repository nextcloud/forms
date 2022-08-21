<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  -
  - UPDATE: Adds Quiz option and takes the input:
  - is yet to store input of quizzes and cannot represtent them
  - requires quizFormItem.vue (should be added to svn)
  -->
<template>
	<div class="top-bar" role="toolbar">
		<slot />
		<NcButton v-if="showSidebarToggle"
			v-tooltip="t('forms', 'Toggle settings')"
			:aria-label="t('forms', 'Toggle settings')"
			type="tertiary"
			@click="toggleSidebar">
			<template #icon>
				<IconMenuOpen :size="24"
					:class="{ 'icon--flipped' : sidebarOpened }" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton'
import IconMenuOpen from 'vue-material-design-icons/MenuOpen'

export default {
	name: 'TopBar',

	components: {
		IconMenuOpen,
		NcButton,
	},

	props: {
		sidebarOpened: {
			type: Boolean,
			default: null,
		},
	},

	computed: {
		showSidebarToggle() {
			return this.sidebarOpened !== null
		},
	},

	methods: {
		toggleSidebar() {
			this.$emit('update:sidebarOpened', !this.sidebarOpened)
		},
	},
}
</script>

<style lang="scss" scoped>
$top-bar-height: 60px;

.top-bar {
	position: sticky;
	z-index: 100;
	top: var(--header-height);
	display: flex;
	align-items: center;
	align-self: flex-end;
	justify-content: flex-end;
	height: var(--top-bar-height);
	margin-top: calc(var(--top-bar-height) * -1);
	padding: 0 6px;
}

.icon--flipped {
	transform: scaleX(-1);
}
</style>
