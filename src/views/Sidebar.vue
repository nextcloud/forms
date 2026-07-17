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
				:form="form"
				:locked="isFormLocked"
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
				:form="form"
				:locked="isFormLocked"
				:lockedUntil="lockedUntilFormatted"
				@update:formProp="onPropertyChange" />
		</NcAppSidebarTab>

		<NcAppSidebarTab
			v-if="form.allowComments"
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
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { defineComponent } from 'vue'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import SettingsSidebarTab from '../components/SidebarTabs/SettingsSidebarTab.vue'
import SharingSidebarTab from '../components/SidebarTabs/SharingSidebarTab.vue'
import PermissionTypes from '../mixins/PermissionTypes.ts'
import ViewsMixin from '../mixins/ViewsMixin.ts'
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

	mixins: [ViewsMixin, PermissionTypes],

	props: {
		active: {
			type: String,
			default: 'forms-sharing',
		},
	},

	emits: ['update:sidebarOpened', 'update:active'],

	setup() {
		return {
			t,
			IconComment,
			IconSettings,
			IconShareVariant,
		}
	},

	data(): {
		commentsView: CommentsViewLike | null
	} {
		return {
			commentsView: null,
		}
	},

	computed: {
		canEdit(): boolean {
			return this.form?.permissions?.includes(
				this.PERMISSION_TYPES.PERMISSION_EDIT,
			)
		},

		lockedUntilFormatted(): string {
			if (this.form.lockedUntil === 0 || this.form.lockedUntil === null) {
				return ''
			}
			return moment(this.form.lockedUntil, 'X')
				.locale(window.OC.getLanguage())
				.fromNow()
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
		'form.id': function (newId: number) {
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
			if (!this.form.allowComments) {
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
					propsData: { resourceId: this.form.id },
				})
			}
			await this.commentsView.update(this.form.id)
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
			;(this.form as unknown as Record<string, unknown>)[property] = newVal
			this.saveFormProperty(property)
		},

		/**
		 * Adding/Removing Share from the reactive object. API-Request is done in sharing-tab.
		 *
		 * @param share The respective share object
		 */
		onAddShare(share: FormsShare): void {
			this.form.shares.push(share)
			emit('forms:last-updated:set', this.form.id)
		},

		onRemoveShare(share: ShareWithId): void {
			const index = this.form.shares.findIndex(
				(search): boolean =>
					'id' in (search as unknown as Record<string, unknown>)
					&& (search as ShareWithId).id === share.id,
			)
			this.form.shares.splice(index, 1)
			emit('forms:last-updated:set', this.form.id)
		},

		onUpdateShare(share: ShareWithId): void {
			const index = this.form.shares.findIndex(
				(search): boolean =>
					'id' in (search as unknown as Record<string, unknown>)
					&& (search as ShareWithId).id === share.id,
			)
			this.form.shares.splice(index, 1, share)
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
