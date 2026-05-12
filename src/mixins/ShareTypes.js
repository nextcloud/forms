/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import IconChatSvg from '@material-symbols/svg-400/outlined/chat_bubble.svg?raw'
import IconCircleSvg from '@material-symbols/svg-400/outlined/circle.svg?raw'
import IconGroupSvg from '@material-symbols/svg-400/outlined/group.svg?raw'
import IconMailSvg from '@material-symbols/svg-400/outlined/mail.svg?raw'
import IconUserSvg from '@material-symbols/svg-400/outlined/person.svg?raw'

export default {
	data() {
		return {
			SHARE_TYPES: {
				SHARE_TYPE_USER: OC.Share.SHARE_TYPE_USER,
				SHARE_TYPE_GROUP: OC.Share.SHARE_TYPE_GROUP,
				SHARE_TYPE_LINK: OC.Share.SHARE_TYPE_LINK,
				SHARE_TYPE_EMAIL: OC.Share.SHARE_TYPE_EMAIL,
				SHARE_TYPE_REMOTE: OC.Share.SHARE_TYPE_REMOTE,
				SHARE_TYPE_CIRCLE: OC.Share.SHARE_TYPE_CIRCLE,
				SHARE_TYPE_GUEST: OC.Share.SHARE_TYPE_GUEST,
				SHARE_TYPE_REMOTE_GROUP: OC.Share.SHARE_TYPE_REMOTE_GROUP,
				SHARE_TYPE_ROOM: OC.Share.SHARE_TYPE_ROOM,
			},

			/**
			 * !!! Keep in Sync with lib/Constants.php !!
			 */
			SHARE_TYPES_USED: [
				OC.Share.SHARE_TYPE_USER,
				OC.Share.SHARE_TYPE_GROUP,
				OC.Share.SHARE_TYPE_LINK,
				OC.Share.SHARE_TYPE_CIRCLE,
			],
		}
	},

	methods: {
		/**
		 * Get the icon based on the share type
		 * Default share is a user, other icons are here to differenciate from it, so let's not display the user icon.
		 *
		 * @param {number} type the share type
		 * @return {string} the icon as raw svg
		 */
		shareTypeToIcon(type) {
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
}
