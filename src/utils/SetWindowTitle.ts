/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'

declare global {
	interface Window {
		OC: {
			theme: {
				title: string
			}
		}
	}
}

/**
 * Set the Window-Title to current FormTitle including suffix.
 *
 * @param formTitle Title of current form to set on window.
 */
export default function SetWindowTitle(formTitle: string): void {
	if (formTitle === '') {
		window.document.title = t('forms', 'Forms') + ' - ' + window.OC.theme.title
	} else {
		window.document.title =
			formTitle + ' - ' + t('forms', 'Forms') + ' - ' + window.OC.theme.title
	}
}
