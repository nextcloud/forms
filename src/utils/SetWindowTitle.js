/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Set the Window-Title to current FormTitle including suffix.
 *
 * @param {string} formTitle Title of current form to set on window.
 */
function SetWindowTitle(formTitle) {
	if (formTitle === '') {
		window.document.title = t('forms', 'Forms') + ' - ' + OC.theme.title
	} else {
		window.document.title =
			formTitle + ' - ' + t('forms', 'Forms') + ' - ' + OC.theme.title
	}
}

export default SetWindowTitle
