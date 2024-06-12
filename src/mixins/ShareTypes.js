/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
/* eslint-disable import/no-unresolved */
import IconUserSvg from '@mdi/svg/svg/account.svg?raw'
import IconGroupSvg from '@mdi/svg/svg/account-group.svg?raw'
import IconMailSvg from '@mdi/svg/svg/email.svg?raw'
import IconChatSvg from '@mdi/svg/svg/chat.svg?raw'
import IconCircleSvg from '@mdi/svg/svg/circle-outline.svg?raw'
/* eslint-enable import/no-unresolved */

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
