/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Extract actual data from Axios OCS response
 * Just a small wrapper for nice code.
 *
 * @param {object} response response returned by axios
 * @return {object} The actual data out of the ocs response
 */
function OcsResponse2Data(response) {
	return response.data.ocs.data
}

export default OcsResponse2Data
