import { showError, showSuccess } from '@nextcloud/dialogs'
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { generateUrl } from '@nextcloud/router'
import logger from '../utils/Logger.js'

export default {
	methods: {
		/**
		 * Get the internal link for sharing the form
		 *
		 * @param {string} formHash Internal form hash
		 * @return {string} link
		 */
		getInternalShareLink(formHash) {
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
		 * @param {object} share The share
		 * @return {string} link
		 */
		getPublicShareLink(share) {
			let url
			if (this.isEmbeddingAllowed(share)) {
				url = generateUrl(`/apps/forms/embed/${share.shareWith}`)
			} else {
				url = generateUrl(`/apps/forms/s/${share.shareWith}`)
			}
			return new URL(url, window.location).href
		},

		/**
		 * Check if a share can be used for embedding
		 *
		 * @param {{ shareType: number, permissions: string[] }} share The share to check
		 */
		isEmbeddingAllowed(share) {
			return (
				share.shareType === this.SHARE_TYPES.SHARE_TYPE_LINK
				&& share.permissions?.includes(
					this.PERMISSION_TYPES.PERMISSION_EMBED,
				)
			)
		},

		/**
		 * Copy link to clipboard.
		 *
		 * @param {object} event Origin event of function call.
		 * @param {string} link Link to copy
		 */
		async copyLink(event, link) {
			// Copy link, boolean return indicates success or fail.
			try {
				await navigator.clipboard.writeText(link)
				showSuccess(t('forms', 'Form link copied'))
			} catch (error) {
				showError(t('forms', 'Cannot copy, please copy the link manually'))
				logger.error('Copy link failed', { error })
			}
			// Set back focus as clipboard removes focus
			event.target.focus()
		},

		/**
		 * Copy code to embed public share inside external websites
		 *
		 * @param {object} share Public link-share
		 */
		async copyEmbeddingCode(share) {
			const code = `<iframe src="${this.getPublicShareLink(share)}" width="750" height="900"></iframe>`
			try {
				await navigator.clipboard.writeText(code)
				showSuccess(t('forms', 'Embedding code copied'))
			} catch (error) {
				showError(t('forms', 'Cannot copy the code'))
				logger.error('Copy embedding code failed', { error })
			}
			// Set back focus as clipboard removes focus
			event.target.focus()
		},
	},
}
