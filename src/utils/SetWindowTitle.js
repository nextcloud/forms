/**
 * @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
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

/**
 * Set the Window-Title to current FormTitle including suffix.
 *
 * @param {string} formTitle Title of current form to set on window.
 */
const SetWindowTitle = function(formTitle) {
	if (formTitle === '') {
		window.document.title = t('forms', 'Forms') + ' - ' + OC.theme.title
	} else {
		window.document.title = formTitle + ' - ' + t('forms', 'Forms') + ' - ' + OC.theme.title
	}
}

export default SetWindowTitle
