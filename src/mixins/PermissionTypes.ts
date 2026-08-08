/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineComponent } from 'vue'

// !!! Keep in Sync with lib/Constants.php !!!
export const PERMISSION_EDIT = 'edit' as string
export const PERMISSION_RESULTS = 'results' as string
export const PERMISSION_RESULTS_DELETE = 'results_delete' as string
export const PERMISSION_SUBMIT = 'submit' as string
/** Internal permission to mark public link shares as embeddable */
export const PERMISSION_EMBED = 'embed' as string
export const PERMISSION_ALL = [
	PERMISSION_EDIT,
	PERMISSION_RESULTS,
	PERMISSION_RESULTS_DELETE,
	PERMISSION_SUBMIT,
] as string[]

export const PERMISSION_TYPES = {
	PERMISSION_EDIT,
	PERMISSION_RESULTS,
	PERMISSION_RESULTS_DELETE,
	PERMISSION_SUBMIT,
	PERMISSION_EMBED,
	PERMISSION_ALL,
}

export default defineComponent({
	name: 'PermissionTypes',

	data() {
		return {
			PERMISSION_TYPES: {
				PERMISSION_EDIT,
				PERMISSION_RESULTS,
				PERMISSION_RESULTS_DELETE,
				PERMISSION_SUBMIT,
				PERMISSION_EMBED,
				PERMISSION_ALL,
			},
		}
	},
})
