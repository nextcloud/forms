<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent appName="forms" :class="{ 'app-forms-embedded': isEmbedded }">
		<Submit
			:form="form"
			publicView
			:shareHash="shareHash"
			:isLoggedIn="isLoggedIn"
			:sidebarOpened="false" />
	</NcContent>
</template>

<script lang="ts">
import type { FormsForm } from './models/Entities.d.ts'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { defineComponent } from 'vue'
import NcContent from '@nextcloud/vue/components/NcContent'
import Submit from './views/Submit.vue'

const formsAppName = 'forms'

export default defineComponent({
	name: 'FormsSubmit',

	components: {
		NcContent,
		Submit,
	},

	data() {
		return {
			form: loadState(formsAppName, 'form') as FormsForm,
			isLoggedIn: loadState(formsAppName, 'isLoggedIn') as boolean,
			isEmbedded: loadState(formsAppName, 'isEmbedded', false) as boolean,
			shareHash: loadState(formsAppName, 'shareHash') as string,
		}
	},

	unmounted() {
		unsubscribe('forms:last-updated:set', this.onSubmitMessageEvent)
	},

	mounted() {
		if (this.isEmbedded) {
			subscribe('forms:last-updated:set', this.onSubmitMessageEvent)

			// Communicate window size to parent window in iframes
			const resizeObserver = new ResizeObserver((entries) => {
				this.emitResizeMessage(entries[0].target as HTMLElement)
			})
			this.$nextTick(() => {
				const formEl = document.querySelector('.app-forms-embedded form')
				if (formEl) {
					resizeObserver.observe(formEl)
				}
			})
		}
	},

	methods: {
		onSubmitMessageEvent(event: unknown): void {
			const id = Number(event)
			if (Number.isFinite(id)) {
				this.emitSubmitMessage(id)
			}
		},

		emitSubmitMessage(id: number): void {
			window.parent?.postMessage(
				{
					type: 'form-saved',
					payload: {
						id,
					},
				},
				'*',
			)
		},

		/**
		 * @param target Target of which the size should be communicated
		 */
		emitResizeMessage(target: HTMLElement): void {
			const rect = target.getBoundingClientRect()
			let height = rect.top + target.scrollHeight
			let width = target.scrollWidth

			// When submitted the height and width is 0
			if (height === 0) {
				target = document.querySelector(
					'.app-forms-embedded main .empty-content',
				) as HTMLElement
				height = target.getBoundingClientRect().top + target.scrollHeight
				width = Math.max(
					target.scrollWidth,
					(
						document.querySelector(
							'.app-forms-embedded main header',
						) as HTMLElement
					).scrollWidth,
				)
			}

			window.parent?.postMessage(
				{
					type: 'resize-iframe',
					payload: {
						width,
						height,
					},
				},
				'*',
			)
		},
	},
})
</script>
