/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import logger from '../utils/Logger.ts'

export interface Share {
	shareType: number
	permissions?: string[]
	shareWith?: string
}

export interface ShareLinkDependencies {
	SHARE_TYPES: {
		SHARE_TYPE_LINK: number
	}
	PERMISSION_TYPES: {
		PERMISSION_EMBED: string
	}
}

export interface UseShareLinkResult {
	getInternalShareLink: (formHash: string) => string
	getPublicShareLink: (share: Share) => string
	isEmbeddingAllowed: (share: Share) => boolean
	copyLink: (event: Event, link: string) => Promise<void>
	copyEmbeddingCode: (event: Event, share: Share) => Promise<void>
}

/**
 * Composition API access to share link helpers.
 *
 * @param dependencies Share type and permission constants.
 */
export function useShareLink(
	dependencies: ShareLinkDependencies,
): UseShareLinkResult {
	const isEmbeddingAllowed = (share: Share): boolean => {
		return (
			share.shareType === dependencies.SHARE_TYPES.SHARE_TYPE_LINK
			&& share.permissions?.includes(
				dependencies.PERMISSION_TYPES.PERMISSION_EMBED,
			)
		)
	}

	const getInternalShareLink = (formHash: string): string => {
		return (
			window.location.protocol
			+ '//'
			+ window.location.host
			+ generateUrl(`/apps/forms/${formHash}`)
		)
	}

	const getPublicShareLink = (share: Share): string => {
		const path = isEmbeddingAllowed(share)
			? `/apps/forms/embed/${share.shareWith}`
			: `/apps/forms/s/${share.shareWith}`
		return new URL(generateUrl(path), window.location).href
	}

	const copyLink = async (event: Event, link: string): Promise<void> => {
		try {
			await navigator.clipboard.writeText(link)
			showSuccess(t('forms', 'Form link copied'))
		} catch (error) {
			showError(t('forms', 'Cannot copy, please copy the link manually'))
			logger.error('Copy link failed', { error })
		}

		;(event.target as HTMLElement).focus()
	}

	const copyEmbeddingCode = async (event: Event, share: Share): Promise<void> => {
		const code = `<iframe src="${getPublicShareLink(share)}" width="750" height="900"></iframe>`

		try {
			await navigator.clipboard.writeText(code)
			showSuccess(t('forms', 'Embedding code copied'))
		} catch (error) {
			showError(t('forms', 'Cannot copy the code'))
			logger.error('Copy embedding code failed', { error })
		}

		;(event.target as HTMLElement).focus()
	}

	return {
		getInternalShareLink,
		getPublicShareLink,
		isEmbeddingAllowed,
		copyLink,
		copyEmbeddingCode,
	}
}
