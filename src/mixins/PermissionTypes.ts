/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineComponent } from 'vue'

// !!! Keep in Sync with lib/Constants.php !!!
export const PERMISSION_EDIT = 'edit'
export const PERMISSION_RESULTS = 'results'
export const PERMISSION_RESULTS_DELETE = 'results_delete'
export const PERMISSION_SUBMIT = 'submit'
/** Internal permission to mark public link shares as embeddable */
export const PERMISSION_EMBED = 'embed'
export const PERMISSION_ALL = [
	PERMISSION_EDIT,
	PERMISSION_RESULTS,
	PERMISSION_RESULTS_DELETE,
	PERMISSION_SUBMIT,
]

export interface PermissionTypesData {
	PERMISSION_TYPES: {
		PERMISSION_EDIT: string
		PERMISSION_RESULTS: string
		PERMISSION_RESULTS_DELETE: string
		PERMISSION_SUBMIT: string
		PERMISSION_EMBED: string
		PERMISSION_ALL: string[]
	}
}

export default defineComponent({
	name: 'PermissionTypes',

	data(): PermissionTypesData {
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
