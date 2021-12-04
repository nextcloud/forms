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
		 * Copy the share-link to clipboard.
		 * Where imported, this method requires the property 'form' to be set!
		 *
		 * @param {object} event Origin event of function call.
		 */
		async copyShareLink(event) {
			const shareLink = window.location.protocol + '//' + window.location.host + generateUrl(`/apps/forms/${this.form.hash}`)
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
