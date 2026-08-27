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
			v-if="!canOnlySubmit && currentView"
			:active="currentView"
			:options="availableViews"
			:groupLabel="t('forms', 'View mode')"
			@update:active="onChangeView" />
		<NcButton
			v-if="canShare && !sidebarOpened"
			:aria-label="isMobile ? t('forms', 'Share form') : null"
			variant="tertiary"
			@click="onShareForm">
			<template #icon>
				<NcIconSvgWrapper :svg="IconShareVariant" />
			</template>
			<template v-if="!isMobile" #default>
				{{ t('forms', 'Share') }}
			</template>
		</NcButton>
	</div>
</template>

<script lang="ts">
import IconBarChart from '@material-symbols/svg-400/outlined/bar_chart.svg?raw'
import IconEdit from '@material-symbols/svg-400/outlined/edit.svg?raw'
import IconShareVariant from '@material-symbols/svg-400/outlined/share.svg?raw'
import IconVisibility from '@material-symbols/svg-400/outlined/visibility.svg?raw'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue'
import { computed, defineComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import PillMenu from './PillMenu.vue'
import { PERMISSION_TYPES } from '../models/Permissions.ts'
import logger from '../utils/Logger.ts'

type TopBarViewId = 'submit' | 'edit' | 'results'

type TopBarViewOption = {
	ariaLabel: string
	icon: string
	title: string
	id: TopBarViewId
	disabled?: boolean
}

const submitView: TopBarViewOption = {
	ariaLabel: t('forms', 'View form'),
	icon: IconVisibility,
	title: t('forms', 'View'),
	id: 'submit',
}
const editView: TopBarViewOption = {
	ariaLabel: t('forms', 'Edit form'),
	icon: IconEdit,
	title: t('forms', 'Edit'),
	id: 'edit',
	disabled: false,
}
const resultsView: TopBarViewOption = {
	ariaLabel: t('forms', 'Show responses'),
	icon: IconBarChart,
	title: t('forms', 'Responses'),
	id: 'results',
}

export default defineComponent({
	name: 'TopBar',

	components: {
		NcIconSvgWrapper,
		NcButton,
		PillMenu,
	},

	props: {
		archived: {
			type: Boolean,
			default: false,
		},

		locked: {
			type: Boolean,
			required: true,
		},

		sidebarOpened: {
			type: Boolean,
			default: false,
		},

		permissions: {
			type: Array as () => string[],
			default: () => [],
		},

		submissionCount: {
			type: Number,
			default: 0,
		},
	},

	emits: ['shareForm'],

	setup(props, { emit }) {
		const route = useRoute()
		const router = useRouter()
		const isMobile = useIsMobile()

		const canSubmit = computed(() =>
			props.permissions.includes(PERMISSION_TYPES.PERMISSION_SUBMIT),
		)

		const canEdit = computed(
			() =>
				props.permissions.includes(PERMISSION_TYPES.PERMISSION_EDIT)
				&& !props.archived,
		)

		const canSeeResults = computed(
			() =>
				props.permissions.includes(PERMISSION_TYPES.PERMISSION_RESULTS)
				|| props.submissionCount > 0,
		)

		// This probably can get a permission of itself
		const canShare = computed(() => canEdit.value)

		const canOnlySubmit = computed(
			() =>
				props.permissions.length === 1
				&& props.permissions.includes(PERMISSION_TYPES.PERMISSION_SUBMIT)
				&& props.submissionCount === 0,
		)

		const availableViews = computed<TopBarViewOption[]>(() => {
			const views: TopBarViewOption[] = []
			if (canSubmit.value) {
				views.push(submitView)
			}
			if (canEdit.value) {
				views.push({
					...editView,
					disabled: props.locked,
				})
			}
			if (canSeeResults.value) {
				views.push(resultsView)
			}
			return views
		})

		const currentView = computed<TopBarViewOption | undefined>(() => {
			const routeName = route.name
			return availableViews.value.find(
				(v) =>
					routeName === v.id
					|| (typeof routeName === 'string'
						&& routeName.startsWith(v.id + '.')),
			)
		})

		/**
		 * Router methods
		 *
		 * @param option The selected pill menu option
		 */
		const onChangeView = async (option: TopBarViewOption): Promise<void> => {
			if (route.name === option.id) {
				return
			}

			try {
				const hash = Array.isArray(route.params.hash)
					? route.params.hash[0]
					: route.params.hash
				await router.push({
					name: option.id,
					params: {
						hash,
					},
				})
			} catch (error) {
				logger.debug('Navigation cancelled', { error })
			}
		}

		const onShareForm = (): void => {
			emit('shareForm')
		}

		return {
			t,
			isMobile,
			IconShareVariant,
			currentView,
			availableViews,
			canShare,
			canOnlySubmit,
			onChangeView,
			onShareForm,
		}
	},
})
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
