/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Extract actual data from Axios OCS response
 * Just a small wrapper for nice code.
 *
 * @param response response returned by axios
 * @return The actual data out of the ocs response
 */
export default function OcsResponse2Data(response: unknown): unknown {
	return response.data.ocs.data
}
