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
import type { FormsShare } from '../models/Entities.d.ts'

import IconComment from '@material-symbols/svg-400/outlined/comment.svg?raw'
import IconSettings from '@material-symbols/svg-400/outlined/settings.svg?raw'
import IconShareVariant from '@material-symbols/svg-400/outlined/share.svg?raw'
import { getCurrentUser } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { defineComponent } from 'vue'
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
			type: Object,
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

		return {
			...viewForm,
			t,
			IconComment,
			IconSettings,
			IconShareVariant,
		}
	},

	data() {
		return {
			commentsView: null as CommentsViewLike | null,
			localShares: [...this.form.shares] as FormsShare[],
			localFormOverrides: {} as Record<string, unknown>,
		}
	},

	computed: {
		canEdit(): boolean {
			return this.form?.permissions?.includes(PERMISSION_TYPES.PERMISSION_EDIT)
		},

		sidebarForm(): Record<string, unknown> {
			return {
				...this.form,
				...this.localFormOverrides,
				shares: this.localShares,
			}
		},

		sidebarAllowComments(): boolean {
			return Boolean(this.sidebarForm.allowComments)
		},

		isSidebarLocked(): boolean {
			const lockedUntil = this.sidebarForm.lockedUntil
			const lockedBy = this.sidebarForm.lockedBy
			return (
				lockedUntil === 0
				|| (Number(lockedUntil || 0) > moment().unix()
					&& lockedBy !== getCurrentUser().uid)
			)
		},

		lockedUntilFormatted(): string {
			const lockedUntil = this.sidebarForm.lockedUntil
			if (lockedUntil === 0 || lockedUntil === null) {
				return ''
			}
			return moment(lockedUntil, 'X').locale(window.OC.getLanguage()).fromNow()
		},

		sidebarTitle(): string {
			if (this.active === 'forms-comments') {
				return t('forms', 'Form comments')
			} else {
				return t('forms', 'Form settings')
			}
		},
	},

	watch: {
		form: function (nextForm: Record<string, unknown>) {
			const nextOverrides = Object.fromEntries(
				Object.entries(this.localFormOverrides).filter(
					([key, value]) => nextForm[key] !== value,
				),
			)
			this.localFormOverrides = nextOverrides
		},

		'form.shares': function (shares: FormsShare[]) {
			this.localShares = [...shares]
		},

		'form.id': function (newId: number) {
			this.localShares = [...this.form.shares]
			this.localFormOverrides = {}

			// Only update comments when the Comments tab is active
			if (this.active !== 'forms-comments') {
				return
			}

			// Only update comments when commentsView is instantiated, else setup commentsView
			if (this.commentsView) {
				this.commentsView.update(newId)
			} else {
				this.setupComments()
			}
		},

		active(newVal: string) {
			if (newVal === 'forms-comments') {
				this.setupComments()
			} else {
				this.teardownComments()
			}
		},
	},

	mounted() {
		this.$nextTick(() => {
			if (this.active === 'forms-comments') {
				this.setupComments()
			}

			// If the user cannot edit, prefer the comments tab when available
			// Use the mounted comments element as the availability check rather than
			// consulting `form.allowComments` (the tab is rendered with v-if).
			if (
				!this.canEdit
				&& this.$refs.commentsEl
				&& this.active !== 'forms-comments'
			) {
				this.onUpdateActive('forms-comments')
			}
		})
	},

	beforeUnmount() {
		this.teardownComments()
	},

	methods: {
		onUpdateActive(active: string): void {
			this.$emit('update:active', active)
		},

		// Mount or update the Comments view inside the sidebar
		async setupComments(): Promise<void> {
			// comments disabled for this form
			if (!this.sidebarForm.allowComments) {
				return
			}

			// comments element missing
			const el = this.$refs.commentsEl as Element | undefined
			if (!el) {
				logger.debug('setupComments: no comments element found')
				return
			}

			if (!this.commentsView) {
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

				this.commentsView = new commentsCtor('forms', {
					propsData: { resourceId: this.sidebarForm.id },
				})
			}
			await this.commentsView.update(this.sidebarForm.id)
			this.commentsView.$mount(el)
		},

		teardownComments(): void {
			this.commentsView = null
		},

		/**
		 * Save Form-Properties
		 *
		 * @param property The Name of the Property to update
		 * @param newVal The new Property value
		 */
		onPropertyChange(property: string, newVal: unknown): void {
			this.localFormOverrides = {
				...this.localFormOverrides,
				[property]: newVal,
			}

			this.$emit('update:form', {
				...this.form,
				...this.localFormOverrides,
				shares: this.localShares,
			})

			this.saveFormPropertyValue(property, newVal)
		},

		/**
		 * Adding/Removing Share from the reactive object. API-Request is done in sharing-tab.
		 *
		 * @param share The respective share object
		 */
		onAddShare(share: FormsShare): void {
			const newShares = [...this.localShares, share]
			this.localShares = newShares
			this.$emit('update:form', { ...this.form, shares: newShares })
			emit('forms:last-updated:set', this.form.id)
		},

		onRemoveShare(share: ShareWithId): void {
			const newShares = this.localShares.filter(
				(search): boolean =>
					!('id' in (search as unknown as Record<string, unknown>))
					|| (search as ShareWithId).id !== share.id,
			)
			this.localShares = newShares
			this.$emit('update:form', { ...this.form, shares: newShares })
			emit('forms:last-updated:set', this.form.id)
		},

		onUpdateShare(share: ShareWithId): void {
			const newShares = this.localShares.map((s) =>
				'id' in (s as unknown as Record<string, unknown>)
				&& (s as ShareWithId).id === share.id
					? share
					: s,
			)
			this.localShares = newShares
			this.$emit('update:form', { ...this.form, shares: newShares })
			emit('forms:last-updated:set', this.form.id)
		},
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
