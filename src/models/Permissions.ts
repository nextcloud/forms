/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// !!! Keep in Sync with lib/Constants.php !!!
const PERMISSION_EDIT = 'edit'
const PERMISSION_RESULTS = 'results'
const PERMISSION_RESULTS_DELETE = 'results_delete'
const PERMISSION_SUBMIT = 'submit'
/** Internal permission to mark public link shares as embeddable */
const PERMISSION_EMBED = 'embed'
const PERMISSION_ALL = [
	PERMISSION_EDIT,
	PERMISSION_RESULTS,
	PERMISSION_RESULTS_DELETE,
	PERMISSION_SUBMIT,
] as const

export const PERMISSION_TYPES = {
	PERMISSION_EDIT,
	PERMISSION_RESULTS,
	PERMISSION_RESULTS_DELETE,
	PERMISSION_SUBMIT,
	PERMISSION_EMBED,
	PERMISSION_ALL: [...PERMISSION_ALL],
}
