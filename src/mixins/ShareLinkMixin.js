/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	methods: {
		/**
		 * Copy internal share link
		 *
		 * @param {object} event Event of origin, calling the function
		 * @param {string} formHash Internal form-hash for link
		 */
		async copyInternalShareLink(event, formHash) {
			const shareLink = window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/${formHash}`)
			this.copyLink(event, shareLink)
		},
		/**
		 * Copy share link for public share
		 *
		 * @param {object} event Event of origin, calling the function
		 * @param {string} publicHash Hash of public link-share
		 */
		async copyPublicShareLink(event, publicHash) {
			const shareLink = window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/s/${publicHash}`)
			this.copyLink(event, shareLink)
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
			}
			// Set back focus as clipboard removes focus
			event.target.focus()
		},
	},
}
