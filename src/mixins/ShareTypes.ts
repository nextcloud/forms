/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable @typescript-eslint/no-explicit-any */

import IconChatSvg from '@material-symbols/svg-400/outlined/chat_bubble.svg?raw'
import IconCircleSvg from '@material-symbols/svg-400/outlined/circle.svg?raw'
import IconGroupSvg from '@material-symbols/svg-400/outlined/group.svg?raw'
import IconMailSvg from '@material-symbols/svg-400/outlined/mail.svg?raw'
import IconUserSvg from '@material-symbols/svg-400/outlined/person.svg?raw'
import { defineComponent } from 'vue'

export interface ShareTypesData {
	SHARE_TYPES: {
		SHARE_TYPE_USER: number
		SHARE_TYPE_GROUP: number
		SHARE_TYPE_LINK: number
		SHARE_TYPE_EMAIL: number
		SHARE_TYPE_REMOTE: number
		SHARE_TYPE_CIRCLE: number
		SHARE_TYPE_GUEST: number
		SHARE_TYPE_REMOTE_GROUP: number
		SHARE_TYPE_ROOM: number
	}
	SHARE_TYPES_USED: number[]
}

export default defineComponent({
	name: 'ShareTypes',

	data(): ShareTypesData {
		return {
			SHARE_TYPES: {
				SHARE_TYPE_USER: (window as any).OC.Share.SHARE_TYPE_USER,
				SHARE_TYPE_GROUP: (window as any).OC.Share.SHARE_TYPE_GROUP,
				SHARE_TYPE_LINK: (window as any).OC.Share.SHARE_TYPE_LINK,
				SHARE_TYPE_EMAIL: (window as any).OC.Share.SHARE_TYPE_EMAIL,
				SHARE_TYPE_REMOTE: (window as any).OC.Share.SHARE_TYPE_REMOTE,
				SHARE_TYPE_CIRCLE: (window as any).OC.Share.SHARE_TYPE_CIRCLE,
				SHARE_TYPE_GUEST: (window as any).OC.Share.SHARE_TYPE_GUEST,
				SHARE_TYPE_REMOTE_GROUP: (window as any).OC.Share
					.SHARE_TYPE_REMOTE_GROUP,
				SHARE_TYPE_ROOM: (window as any).OC.Share.SHARE_TYPE_ROOM,
			},

			/**
			 * !!! Keep in Sync with lib/Constants.php !!
			 */
			SHARE_TYPES_USED: [
				(window as any).OC.Share.SHARE_TYPE_USER,
				(window as any).OC.Share.SHARE_TYPE_GROUP,
				(window as any).OC.Share.SHARE_TYPE_LINK,
				(window as any).OC.Share.SHARE_TYPE_CIRCLE,
			],
		}
	},

	methods: {
		/**
		 * Get the icon based on the share type
		 * Default share is a user, other icons are here to differenciate from it, so let's not display the user icon.
		 *
		 * @param type the share type
		 * @return the icon as raw svg
		 */
		shareTypeToIcon(type: number): string {
			switch (type) {
				case this.SHARE_TYPES.SHARE_TYPE_GUEST:
					// case this.SHARE_TYPES.SHARE_TYPE_REMOTE:
					// case this.SHARE_TYPES.SHARE_TYPE_USER:
					return IconUserSvg
				case this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP:
				case this.SHARE_TYPES.SHARE_TYPE_GROUP:
					return IconGroupSvg
				case this.SHARE_TYPES.SHARE_TYPE_EMAIL:
					return IconMailSvg
				case this.SHARE_TYPES.SHARE_TYPE_CIRCLE:
					return IconCircleSvg
				case this.SHARE_TYPES.SHARE_TYPE_ROOM:
					return IconChatSvg

				default:
					return ''
			}
		},
	},
})
