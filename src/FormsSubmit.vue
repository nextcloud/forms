<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="forms" :class="{ 'app-forms-embedded': isEmbedded }">
		<Submit
			:form="form"
			public-view
			:share-hash="shareHash"
			:is-logged-in="isLoggedIn" />
	</NcContent>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import NcContent from '@nextcloud/vue/components/NcContent'
import Submit from './views/Submit.vue'

export default {
	name: 'FormsSubmit',

	components: {
		NcContent,
		Submit,
	},

	data() {
		return {
			form: loadState('forms', 'form'),
			isLoggedIn: loadState('forms', 'isLoggedIn'),
			isEmbedded: loadState('forms', 'isEmbedded', false),
			shareHash: loadState('forms', 'shareHash'),
		}
	},

	destroyed() {
		unsubscribe('forms:last-updated:set', this.emitSubmitMessage)
	},

	mounted() {
		if (this.isEmbedded) {
			subscribe('forms:last-updated:set', this.emitSubmitMessage)

			// Communicate window size to parent window in iframes
			const resizeObserver = new ResizeObserver((entries) => {
				this.emitResizeMessage(entries[0].target)
			})
			this.$nextTick(() =>
				resizeObserver.observe(
					document.querySelector('.app-forms-embedded form'),
				),
			)
		}
	},

	methods: {
		emitSubmitMessage(id) {
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
		 * @param {HTMLElement} target Target of which the size should be communicated
		 */
		emitResizeMessage(target) {
			const rect = target.getBoundingClientRect()
			let height = rect.top + target.scrollHeight
			let width = target.scrollWidth

			// When submitted the height and width is 0
			if (height === 0) {
				target = document.querySelector(
					'.app-forms-embedded main .empty-content',
				)
				height = target.getBoundingClientRect().top + target.scrollHeight
				width = Math.max(
					target.scrollWidth,
					document.querySelector('.app-forms-embedded main header')
						.scrollWidth,
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
}
</script>
