<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div
		:aria-label="t('forms', 'View mode')"
		class="top-bar"
		:class="{
			'top-bar--has-sidebar': sidebarOpened,
		}"
		role="toolbar">
		<PillMenu
			v-if="!canOnlySubmit"
			:active="currentView"
			:options="availableViews"
			@update:active="onChangeView" />
		<NcButton
			v-if="canShare && !sidebarOpened"
			:aria-label="isMobile ? t('forms', 'Share form') : null"
			variant="tertiary"
			@click="onShareForm">
			<template #icon>
				<IconShareVariant :size="20" />
			</template>
			<template v-if="!isMobile" #default>
				{{ t('forms', 'Share') }}
			</template>
		</NcButton>
	</div>
</template>

<script>
import { mdiEye, mdiPencil, mdiPoll } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import IconShareVariant from 'vue-material-design-icons/ShareVariant.vue'

import logger from '../utils/Logger.js'
import PermissionTypes from '../mixins/PermissionTypes.js'
import PillMenu from './PillMenu.vue'

const submitView = {
	ariaLabel: t('forms', 'View form'),
	icon: mdiEye,
	title: t('forms', 'View'),
	id: 'submit',
}
const editView = {
	ariaLabel: t('forms', 'Edit form'),
	icon: mdiPencil,
	title: t('forms', 'Edit'),
	id: 'edit',
}
const resultsView = {
	ariaLabel: t('forms', 'Show results'),
	icon: mdiPoll,
	title: t('forms', 'Results'),
	id: 'results',
}

export default {
	name: 'TopBar',

	components: {
		IconShareVariant,
		NcButton,
		PillMenu,
	},

	mixins: [PermissionTypes],

	props: {
		archived: {
			type: Boolean,
			default: false,
		},

		sidebarOpened: {
			type: Boolean,
			default: false,
		},

		permissions: {
			type: Array,
			default: () => [],
		},
	},

	setup() {
		return {
			t,

			isMobile: useIsMobile(),
		}
	},

	computed: {
		currentView() {
			return this.availableViews.filter((v) => v.id === this.$route.name)[0]
		},
		availableViews() {
			const views = []
			if (this.canSubmit) {
				views.push(submitView)
			}
			if (this.canEdit) {
				views.push(editView)
			}
			if (this.canSeeResults) {
				views.push(resultsView)
			}
			return views
		},
		canSubmit() {
			return this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_SUBMIT)
		},
		canEdit() {
			return (
				this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_EDIT)
				&& !this.archived
			)
		},
		canSeeResults() {
			return this.permissions.includes(
				this.PERMISSION_TYPES.PERMISSION_RESULTS,
			)
		},
		canShare() {
			// This probably can get a permission of itself
			return this.canEdit
		},
		canOnlySubmit() {
			return (
				this.permissions.length === 1
				&& this.permissions.includes(this.PERMISSION_TYPES.PERMISSION_SUBMIT)
			)
		},
	},

	methods: {
		/**
		 * Router methods
		 *
		 * @param {object} option The selected pill menu option
		 */
		async onChangeView(option) {
			if (this.$route.name === option.id) {
				return
			}

			try {
				await this.$router.push({
					name: option.id,
					params: {
						hash: this.$route.params.hash,
					},
				})
			} catch (error) {
				logger.debug('Navigation cancelled', { error })
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
	display: flex;
	align-items: center;
	align-self: flex-end;
	// allow to wrap on small screens
	flex-wrap: wrap;
	justify-content: flex-end;

	// align with navigation and sidebar toggle, but ensure it is not overlayed
	padding: var(--app-navigation-padding);
	margin-inline: var(--default-clickable-area);

	position: sticky;
	top: 0;
	z-index: 100;

	&--has-sidebar {
		// Remove margin as the toggle button does not exist when open
		margin-inline-end: 0;
	}
}
</style>
