/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

/**
 * Set the Window-Title to current FormTitle including suffix.
 *
 * @param formTitle Title of current form to set on window.
 */
export default function SetWindowTitle(formTitle: string): void {
	const themeTitle = window.OC?.theme?.title ?? ''
	if (formTitle === '') {
		window.document.title =
			themeTitle !== ''
				? `${t('forms', 'Forms')} - ${themeTitle}`
				: t('forms', 'Forms')
	} else {
		window.document.title =
			themeTitle !== ''
				? `${formTitle} - ${t('forms', 'Forms')} - ${themeTitle}`
				: `${formTitle} - ${t('forms', 'Forms')}`
	}
}
