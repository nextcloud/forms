/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import IconChatSvg from '@material-symbols/svg-400/outlined/chat_bubble.svg?raw'
import IconCircleSvg from '@material-symbols/svg-400/outlined/circle.svg?raw'
import IconGroupSvg from '@material-symbols/svg-400/outlined/group.svg?raw'
import IconMailSvg from '@material-symbols/svg-400/outlined/mail.svg?raw'
import IconUserSvg from '@material-symbols/svg-400/outlined/person.svg?raw'

const FALLBACK_SHARE_TYPES = {
	SHARE_TYPE_USER: 0,
	SHARE_TYPE_GROUP: 1,
	SHARE_TYPE_LINK: 3,
	SHARE_TYPE_EMAIL: 4,
	SHARE_TYPE_REMOTE: 6,
	SHARE_TYPE_CIRCLE: 7,
	SHARE_TYPE_GUEST: 8,
	SHARE_TYPE_REMOTE_GROUP: 9,
	SHARE_TYPE_ROOM: 10,
}

/**
 * Resolve share types from Nextcloud globals and fall back to stable defaults
 * when sharing globals are not initialized on the current page.
 */
function resolveShareTypes() {
	// const share = typeof window !== 'undefined' ? window.OC?.Share : undefined
	const share = window.OC?.Share

	return {
		SHARE_TYPE_USER:
			share?.SHARE_TYPE_USER ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_USER,
		SHARE_TYPE_GROUP:
			share?.SHARE_TYPE_GROUP ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_GROUP,
		SHARE_TYPE_LINK:
			share?.SHARE_TYPE_LINK ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_LINK,
		SHARE_TYPE_EMAIL:
			share?.SHARE_TYPE_EMAIL ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_EMAIL,
		SHARE_TYPE_REMOTE:
			share?.SHARE_TYPE_REMOTE ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_REMOTE,
		SHARE_TYPE_CIRCLE:
			share?.SHARE_TYPE_CIRCLE ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_CIRCLE,
		SHARE_TYPE_GUEST:
			share?.SHARE_TYPE_GUEST ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_GUEST,
		SHARE_TYPE_REMOTE_GROUP:
			share?.SHARE_TYPE_REMOTE_GROUP
			?? FALLBACK_SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP,
		SHARE_TYPE_ROOM:
			share?.SHARE_TYPE_ROOM ?? FALLBACK_SHARE_TYPES.SHARE_TYPE_ROOM,
	}
}

export const SHARE_TYPES = {
	...resolveShareTypes(),
}

/**
 * !!! Keep in Sync with lib/Constants.php !!
 */
export const SHARE_TYPES_USED = [
	SHARE_TYPES.SHARE_TYPE_USER,
	SHARE_TYPES.SHARE_TYPE_GROUP,
	SHARE_TYPES.SHARE_TYPE_LINK,
	SHARE_TYPES.SHARE_TYPE_CIRCLE,
]

/**
 * Get the icon based on the share type.
 *
 * @param type The share type id.
 */
export function shareTypeToIcon(type: number): string {
	switch (type) {
		case SHARE_TYPES.SHARE_TYPE_GUEST:
			// case SHARE_TYPES.SHARE_TYPE_REMOTE:
			// case SHARE_TYPES.SHARE_TYPE_USER:
			return IconUserSvg
		case SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP:
		case SHARE_TYPES.SHARE_TYPE_GROUP:
			return IconGroupSvg
		case SHARE_TYPES.SHARE_TYPE_EMAIL:
			return IconMailSvg
		case SHARE_TYPES.SHARE_TYPE_CIRCLE:
			return IconCircleSvg
		case SHARE_TYPES.SHARE_TYPE_ROOM:
			return IconChatSvg

		default:
			return ''
	}
}

export interface UseShareTypesResult {
	SHARE_TYPES: typeof SHARE_TYPES
	SHARE_TYPES_USED: number[]
	shareTypeToIcon: (type: number) => string
}

/**
 * Composition API access to share type constants and helpers.
 */
export function useShareTypes(): UseShareTypesResult {
	return {
		SHARE_TYPES,
		SHARE_TYPES_USED: [...SHARE_TYPES_USED],
		shareTypeToIcon,
	}
}
