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
import Clipboard from 'v-clipboard'
import Vue from 'vue'

Vue.use(Clipboard)

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
			this.copyShareLink(event, shareLink)
		},
		/**
		 * Copy share link for public share
		 *
		 * @param {object} event Event of origin, calling the function
		 * @param {string} publicHash Hash of public link-share
		 */
		async copyPublicShareLink(event, publicHash) {
			const shareLink = window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/s/${publicHash}`)
			this.copyShareLink(event, shareLink)
		},

		/**
		 * Copy the share-link to clipboard.
		 *
		 * @param {object} event Origin event of function call.
		 * @param {string} shareLink Link to copy
		 */
		async copyShareLink(event, shareLink) {
			// Copy link, boolean return indicates success or fail.
			if (this.$clipboard(shareLink)) {
				showSuccess(t('forms', 'Form link copied'))
			} else {
				showError(t('forms', 'Cannot copy, please copy the link manually'))
			}
			// Set back focus as clipboard removes focus
			event.target.focus()
		},
	},
}
