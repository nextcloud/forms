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
		<NcButton v-if="canSubmit && $route.name !== 'submit'"
			v-tooltip="t('forms', 'View form')"
			:aria-label="t('forms', 'View form')"
			type="tertiary"
			@click="showSubmit">
			<template #icon>
				<IconEye :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="canEdit && $route.name !== 'edit'"
			v-tooltip="t('forms', 'Edit form')"
			:aria-label="t('forms', 'Edit form')"
			type="tertiary"
			@click="showEdit">
			<template #icon>
				<IconPencil :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="canSeeResults && $route.name !== 'results'"
			v-tooltip="t('forms', 'Results')"
			:aria-label="t('forms', 'Results')"
			type="tertiary"
			@click="showResults">
			<template #icon>
				<IconPoll :size="20" />
			</template>
		</NcButton>
		<NcButton v-if="canShare && !sidebarOpened"
			v-tooltip="t('forms', 'Share form')"
			:aria-label="t('forms', 'Share form')"
			type="tertiary"
			@click="onShareForm">
			<template #icon>
				<IconShareVariant :size="20" />
			</template>
		</NcButton>
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
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import IconEye from 'vue-material-design-icons/Eye.vue'
import IconMenuOpen from 'vue-material-design-icons/MenuOpen.vue'
import IconPencil from 'vue-material-design-icons/Pencil.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import IconShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import PermissionTypes from '../mixins/PermissionTypes.js'

export default {
	name: 'TopBar',

	components: {
		IconEye,
		IconMenuOpen,
		IconPencil,
		IconPoll,
		IconShareVariant,
		NcButton,
	},

	mixins: [PermissionTypes],

	props: {
		sidebarOpened: {
			type: Boolean,
			default: null,
		},
		permissions: {
			type: Array,
			default: () => [],
		},
	},

	computed: {
		canEdit() {
			return this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_EDIT)
		},
		canSubmit() {
			return this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_SUBMIT)
		},
		canSeeResults() {
			return this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_RESULTS)
		},
		canShare() {
			// This probably can get a permission of itself
			return this.canEdit || this.canSeeResults
		},
		showSidebarToggle() {
			return this.sidebarOpened !== null
		},
	},

	methods: {
		toggleSidebar() {
			this.$emit('update:sidebarOpened', !this.sidebarOpened)
		},

		/**
		 * Router methods
		 */
		showEdit() {
			this.$router.push({
				name: 'edit',
				params: {
					hash: this.$route.params.hash,
				},
			})
		},

		showResults() {
			this.$router.push({
				name: 'results',
				params: {
					hash: this.$route.params.hash,
				},
			})
		},

		showSubmit() {
			this.$router.push({
				name: 'submit',
				params: {
					hash: this.$route.params.hash,
				},
			})
		},

		onShareForm() {
			this.$emit('share-form')
		},
	},
}
</script>

<style lang="scss" scoped>
.top-bar {
	top: 0;
	z-index: 100;
	display: flex;
	position: sticky;
	align-items: center;
	align-self: flex-end;
	justify-content: flex-end;
	padding: calc(var(--default-grid-baseline, 4px) * 2);
}

.icon--flipped {
	transform: scaleX(-1);
}
</style>
