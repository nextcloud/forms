/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate as t } from '@nextcloud/l10n'
// !! Keep in SYNC with lib/Constants.php for supported file types \OCA\Forms\Constants::EXTRA_SETTINGS_ALLOWED_FILE_TYPES !!
export default {
	image: {
		label: t('forms', 'Image'),
	},
	'x-office/document': {
		label: t('forms', 'Document'),
	},
	'x-office/presentation': {
		label: t('forms', 'Presentation'),
	},
	'x-office/spreadsheet': {
		label: t('forms', 'Spreadsheet'),
	},
}
