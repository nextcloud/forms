<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppSidebar
		:open="sidebarOpened"
		:active="active"
		:name="sidebarTitle"
		@update:active="onUpdateActive"
		@update:open="$emit('update:sidebarOpened', $event)">
		<NcAppSidebarTab
			v-if="canEdit"
			id="forms-sharing"
			:order="0"
			:name="t('forms', 'Sharing')">
			<template #icon>
				<NcIconSvgWrapper :svg="IconShareVariant" />
			</template>
			<SharingSidebarTab
				:form="sidebarForm"
				:locked="isSidebarLocked"
				:lockedUntil="lockedUntilFormatted"
				@update:formProp="onPropertyChange"
				@addShare="onAddShare"
				@removeShare="onRemoveShare"
				@updateShare="onUpdateShare" />
		</NcAppSidebarTab>

		<NcAppSidebarTab
			v-if="canEdit"
			id="forms-settings"
			:order="1"
			:name="t('forms', 'Settings')">
			<template #icon>
				<NcIconSvgWrapper :svg="IconSettings" />
			</template>
			<SettingsSidebarTab
				:form="sidebarForm"
				:locked="isSidebarLocked"
				:lockedUntil="lockedUntilFormatted"
				@update:formProp="onPropertyChange" />
		</NcAppSidebarTab>

		<NcAppSidebarTab
			v-if="sidebarAllowComments"
			id="forms-comments"
			:order="2"
			:name="t('forms', 'Comments')">
			<template #icon>
				<NcIconSvgWrapper :svg="IconComment" />
			</template>
			<!-- Comments view will be mounted here by setupComments -->
			<div ref="commentsEl" class="forms-comments-root"></div>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { FormsForm, FormsShare } from '../types/Entities.d.ts'

import IconComment from '@material-symbols/svg-400/outlined/comment.svg?raw'
import IconSettings from '@material-symbols/svg-400/outlined/settings.svg?raw'
import IconShareVariant from '@material-symbols/svg-400/outlined/share.svg?raw'
import { getCurrentUser } from '@nextcloud/auth'
import { emit as emitEvent } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { defineComponent } from 'vue'
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import SettingsSidebarTab from '../components/SidebarTabs/SettingsSidebarTab.vue'
import SharingSidebarTab from '../components/SidebarTabs/SharingSidebarTab.vue'
import { useViewForm } from '../composables/useViewForm.ts'
import { PERMISSION_TYPES } from '../models/Permissions.ts'
import logger from '../utils/Logger.ts'

interface CommentsViewLike {
	update: (id: number) => Promise<void> | void
	$mount: (el: Element) => void
}

type ShareWithId = FormsShare & { id: number }

export default defineComponent({
	// eslint-disable-next-line vue/multi-word-component-names
	name: 'Sidebar',

	components: {
		NcIconSvgWrapper,
		NcAppSidebar,
		NcAppSidebarTab,
		SharingSidebarTab,
		SettingsSidebarTab,
	},

	props: {
		active: {
			type: String,
			default: 'forms-sharing',
		},

		form: {
			type: Object as PropType<FormsForm>,
			required: true,
		},

		sidebarOpened: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:sidebarOpened', 'update:active', 'update:form', 'open-sharing'],

	setup(props, { emit }) {
		const viewForm = useViewForm({ form: () => props.form, emit })
		const commentsEl = ref<Element | null>(null)
		const commentsView = ref<CommentsViewLike | null>(null)
		const localShares = ref<FormsShare[]>([...props.form.shares])
		const localFormOverrides = ref<Record<string, unknown>>({})

		const canEdit = computed<boolean>(() =>
			props.form?.permissions?.includes(PERMISSION_TYPES.PERMISSION_EDIT),
		)

		const sidebarForm = computed<FormsForm>(() => ({
			...props.form,
			...localFormOverrides.value,
			shares: localShares.value,
		}))

		const sidebarAllowComments = computed<boolean>(() =>
			Boolean((sidebarForm.value as FormsForm).allowComments),
		)

		const isSidebarLocked = computed<boolean>(() => {
			const lockedUntil = (sidebarForm.value as FormsForm).lockedUntil
			const lockedBy = (sidebarForm.value as FormsForm).lockedBy
			return (
				lockedUntil === 0
				|| (Number(lockedUntil || 0) > moment().unix()
					&& lockedBy !== getCurrentUser()?.uid)
			)
		})

		const lockedUntilFormatted = computed<string>(() => {
			const lockedUntil = (sidebarForm.value as FormsForm).lockedUntil
			if (lockedUntil === 0 || lockedUntil === null) {
				return ''
			}
			return moment(lockedUntil, 'X')
				.locale(window.OC?.getLanguage())
				.fromNow()
		})

		const sidebarTitle = computed<string>(() =>
			props.active === 'forms-comments'
				? t('forms', 'Form comments')
				: t('forms', 'Form settings'),
		)

		const onUpdateActive = (active: string): void => {
			emit('update:active', active)
		}

		// Mount or update the Comments view inside the sidebar
		const setupComments = async (): Promise<void> => {
			// comments disabled for this form
			if (!(sidebarForm.value as FormsForm).allowComments) {
				return
			}

			// comments element missing
			const el = commentsEl.value
			if (!el) {
				logger.debug('setupComments: no comments element found')
				return
			}

			if (!commentsView.value) {
				const commentsCtor = (
					window as unknown as {
						OCA?: {
							Comments?: {
								View?: new (
									scope: string,
									options: { propsData: { resourceId: number } },
								) => CommentsViewLike
							}
						}
					}
				).OCA?.Comments?.View

				if (!commentsCtor) {
					logger.debug('setupComments: comments constructor not available')
					return
				}

				commentsView.value = new commentsCtor('forms', {
					propsData: { resourceId: (sidebarForm.value as FormsForm).id },
				})
			}
			await commentsView.value.update((sidebarForm.value as FormsForm).id)
			commentsView.value.$mount(el)
		}

		const teardownComments = (): void => {
			commentsView.value = null
		}

		/**
		 * Save Form-Properties
		 *
		 * @param property The Name of the Property to update
		 * @param newVal The new Property value
		 */
		const onPropertyChange = (property: string, newVal: unknown): void => {
			localFormOverrides.value = {
				...localFormOverrides.value,
				[property]: newVal,
			}

			emit('update:form', {
				...props.form,
				...localFormOverrides.value,
				shares: localShares.value,
			})

			void viewForm.saveFormPropertyValue(property, newVal)
		}

		/**
		 * Adding/Removing Share from the reactive object. API-Request is done in sharing-tab.
		 *
		 * @param share The respective share object
		 */
		const onAddShare = (share: FormsShare): void => {
			const newShares = [...localShares.value, share]
			localShares.value = newShares
			emit('update:form', { ...props.form, shares: newShares })
			emitEvent('forms:last-updated:set', props.form.id)
		}

		const onRemoveShare = (share: ShareWithId): void => {
			const newShares = localShares.value.filter(
				(search): boolean =>
					!('id' in (search as unknown as Record<string, unknown>))
					|| (search as ShareWithId).id !== share.id,
			)
			localShares.value = newShares
			emit('update:form', { ...props.form, shares: newShares })
			emitEvent('forms:last-updated:set', props.form.id)
		}

		const onUpdateShare = (share: ShareWithId): void => {
			const newShares = localShares.value.map((currentShare) =>
				'id' in (currentShare as unknown as Record<string, unknown>)
				&& (currentShare as ShareWithId).id === share.id
					? share
					: currentShare,
			)
			localShares.value = newShares
			emit('update:form', { ...props.form, shares: newShares })
			emitEvent('forms:last-updated:set', props.form.id)
		}

		watch(
			() => props.form,
			(nextForm) => {
				const nextOverrides = Object.fromEntries(
					Object.entries(localFormOverrides.value).filter(
						([key, value]) =>
							(nextForm as unknown as Record<string, unknown>)[key]
							!== value,
					),
				)
				localFormOverrides.value = nextOverrides
			},
		)

		watch(
			() => props.form.shares,
			(shares) => {
				localShares.value = [...shares]
			},
		)

		watch(
			() => props.form.id,
			(newId) => {
				localShares.value = [...props.form.shares]
				localFormOverrides.value = {}

				// Only update comments when the Comments tab is active
				if (props.active !== 'forms-comments') {
					return
				}

				// Only update comments when commentsView is instantiated, else setup commentsView
				if (commentsView.value) {
					void commentsView.value.update(newId)
				} else {
					void setupComments()
				}
			},
		)

		watch(
			() => props.active,
			(active) => {
				if (active === 'forms-comments') {
					void setupComments()
				} else {
					teardownComments()
				}
			},
		)

		onMounted(() => {
			void nextTick(() => {
				if (props.active === 'forms-comments') {
					void setupComments()
				}

				// If the user cannot edit, prefer the comments tab when available
				// Use the mounted comments element as the availability check rather than
				// consulting `form.allowComments` (the tab is rendered with v-if).
				if (
					!canEdit.value
					&& commentsEl.value
					&& props.active !== 'forms-comments'
				) {
					onUpdateActive('forms-comments')
				}
			})
		})

		onUnmounted(teardownComments)

		return {
			...viewForm,
			t,
			IconComment,
			IconSettings,
			IconShareVariant,
			commentsEl,
			canEdit,
			sidebarForm,
			sidebarAllowComments,
			isSidebarLocked,
			lockedUntilFormatted,
			sidebarTitle,
			onUpdateActive,
			onPropertyChange,
			onAddShare,
			onRemoveShare,
			onUpdateShare,
		}
	},
})
</script>

<style lang="scss" scoped>
.app-sidebar__tab:focus {
	box-shadow: none;
}

h3 {
	font-weight: bold;
	margin-inline-start: 8px;
	margin-block-end: 8px;
}
</style>
