/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

export default {
	data() {
		return {
			/**
			 * !!! Keep in Sync with lib/Constants.php !!
			 */
			PERMISSION_TYPES: {
				PERMISSION_EDIT: 'edit',
				PERMISSION_RESULTS: 'results',
				PERMISSION_RESULTS_DELETE: 'results_delete',
				PERMISSION_SUBMIT: 'submit',
				/** Internal permission to mark public link shares as embeddable */
				PERMISSION_EMBED: 'embed',
				PERMISSION_ALL: [
					this.PERMISSION_EDIT,
					this.PERMISSION_RESULTS,
					this.PERMISSION_RESULTS_DELETE,
					this.PERMISSION_SUBMIT,
				],
			},
		}
	},
}
