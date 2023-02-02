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
		<div v-if="!canOnlySubmit" class="top-bar__view-select">
			<NcButton v-if="canSubmit"
				:aria-label="isMobile ? t('forms', 'View form') : null"
				:type="$route.name === 'submit' ? 'secondary' : 'tertiary'"
				@click="showSubmit">
				<template #icon>
					<IconEye :size="20" />
				</template>
				<template v-if="!isMobile">
					{{ t('forms', 'View') }}
				</template>
			</NcButton>
			<NcButton v-if="canEdit"
				:aria-label="isMobile ? t('forms', 'Edit form') : null"
				:type="$route.name === 'edit' ? 'secondary' : 'tertiary'"
				@click="showEdit">
				<template #icon>
					<IconPencil :size="20" />
				</template>
				<template v-if="!isMobile">
					{{ t('forms', 'Edit') }}
				</template>
			</NcButton>
			<NcButton v-if="canSeeResults"
				:aria-label="isMobile ? t('forms', 'Show results') : null"
				:type="$route.name === 'results' ? 'secondary' : 'tertiary'"
				@click="showResults">
				<template #icon>
					<IconPoll :size="20" />
				</template>
				<template v-if="!isMobile">
					{{ t('forms', 'Results') }}
				</template>
			</NcButton>
		</div>
		<NcButton v-if="canShare && !sidebarOpened"
			:aria-label="isMobile ? t('forms', 'Share form') : null"
			type="tertiary"
			@click="onShareForm">
			<template #icon>
				<IconShareVariant :size="20" />
			</template>
			<template v-if="!isMobile">
				{{ t('forms', 'Share') }}
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
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
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

	mixins: [isMobile, PermissionTypes],

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
			return this.canEdit
		},
		canOnlySubmit() {
			return this.permissions.length === 1 && this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_SUBMIT)
		},
		showSidebarToggle() {
			return this.canEdit && this.sidebarOpened !== null
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
			if (this.$route.name !== 'edit') {
				this.$router.push({
					name: 'edit',
					params: {
						hash: this.$route.params.hash,
					},
				})
			}
		},

		showResults() {
			if (this.$route.name !== 'results') {
				this.$router.push({
					name: 'results',
					params: {
						hash: this.$route.params.hash,
					},
				})
			}
		},

		showSubmit() {
			if (this.$route.name !== 'submit') {
				this.$router.push({
					name: 'submit',
					params: {
						hash: this.$route.params.hash,
					},
				})
			}
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

	&__view-select {
		display: flex;
		height: 44px;
		align-items: center;
		align-self: flex-end;
		justify-content: flex-end;
		background: var(--color-main-background);
		border: 2px solid var(--color-border);
		border-radius: var(--border-radius-pill);
	}
}

.icon--flipped {
	transform: scaleX(-1);
}
</style>
