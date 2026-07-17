/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { defineComponent } from 'vue'
import logger from '../utils/Logger.ts'

export interface Share {
	shareType: number
	permissions?: string[]
	shareWith?: string
}

export default defineComponent({
	name: 'ShareLinkMixin',

	methods: {
		/**
		 * Get the internal link for sharing the form
		 *
		 * @param formHash Internal form hash
		 * @return link
		 */
		getInternalShareLink(formHash: string): string {
			return (
				window.location.protocol
				+ '//'
				+ window.location.host
				+ generateUrl(`/apps/forms/${formHash}`)
			)
		},

		/**
		 * Get the publish share link for a given share
		 *
		 * @param share The share
		 * @return link
		 */
		getPublicShareLink(share: Share): string {
			let url

			if ((this as any).isEmbeddingAllowed(share)) {
				url = generateUrl(`/apps/forms/embed/${share.shareWith}`)
			} else {
				url = generateUrl(`/apps/forms/s/${share.shareWith}`)
			}
			return new URL(url, window.location.href).href
		},

		/**
		 * Check if a share can be used for embedding
		 *
		 * @param share The share to check
		 */
		isEmbeddingAllowed(share: Share): boolean {
			const self = this as any
			return (
				share.shareType === self.SHARE_TYPES.SHARE_TYPE_LINK
				&& Boolean(
					share.permissions?.includes(
						self.PERMISSION_TYPES.PERMISSION_EMBED,
					),
				)
			)
		},

		/**
		 * Copy link to clipboard.
		 *
		 * @param event Origin event of function call.
		 * @param link Link to copy
		 */
		async copyLink(event: Event, link: string): Promise<void> {
			// Copy link, boolean return indicates success or fail.
			try {
				await navigator.clipboard.writeText(link)
				showSuccess(t('forms', 'Form link copied'))
			} catch (error) {
				showError(t('forms', 'Cannot copy, please copy the link manually'))
				logger.error('Copy link failed', { error })
			}
			// Set back focus as clipboard removes focus
			;(event.target as HTMLElement).focus()
		},

		/**
		 * Copy code to embed public share inside external websites
		 *
		 * @param event Origin event of function call.
		 * @param share Public link-share
		 */
		async copyEmbeddingCode(event: Event, share: Share): Promise<void> {
			const code = `<iframe src="${this.getPublicShareLink(share)}" width="750" height="900"></iframe>`
			try {
				await navigator.clipboard.writeText(code)
				showSuccess(t('forms', 'Embedding code copied'))
			} catch (error) {
				showError(t('forms', 'Cannot copy the code'))
				logger.error('Copy embedding code failed', { error })
			}
			// Set back focus as clipboard removes focus
			;(event.target as HTMLElement).focus()
		},
	},
})
