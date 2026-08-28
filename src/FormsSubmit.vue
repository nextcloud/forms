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
import { nextTick, onMounted, onUnmounted } from 'vue'
import NcContent from '@nextcloud/vue/components/NcContent'
import Submit from './views/Submit.vue'

const formsAppName = 'forms'

export default defineComponent({
	name: 'FormsSubmit',

	components: {
		NcContent,
		Submit,
	},

	setup() {
		const form = loadState(formsAppName, 'form') as FormsForm
		const isLoggedIn = loadState(formsAppName, 'isLoggedIn') as boolean
		const isEmbedded = loadState(formsAppName, 'isEmbedded', false) as boolean
		const shareHash = loadState(formsAppName, 'shareHash') as string
		let resizeObserver: ResizeObserver | undefined

		const emitSubmitMessage = (id: number): void => {
			window.parent?.postMessage(
				{
					type: 'form-saved',
					payload: {
						id,
					},
				},
				'*',
			)
		}

		const onSubmitMessageEvent = (event: unknown): void => {
			const id = Number(event)
			if (Number.isFinite(id)) {
				emitSubmitMessage(id)
			}
		}

		/**
		 * @param target Target of which the size should be communicated
		 */
		const emitResizeMessage = (target: HTMLElement): void => {
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
		}

		onMounted(() => {
			if (!isEmbedded) {
				return
			}

			subscribe('forms:last-updated:set', onSubmitMessageEvent)

			// Communicate window size to parent window in iframes
			resizeObserver = new ResizeObserver((entries) => {
				emitResizeMessage(entries[0].target as HTMLElement)
			})
			void nextTick(() => {
				const formEl = document.querySelector('.app-forms-embedded form')
				if (formEl) {
					resizeObserver?.observe(formEl)
				}
			})
		})

		onUnmounted(() => {
			unsubscribe('forms:last-updated:set', onSubmitMessageEvent)
			resizeObserver?.disconnect()
		})

		return {
			form,
			isLoggedIn,
			isEmbedded,
			shareHash,
		}
	},
})
</script>
