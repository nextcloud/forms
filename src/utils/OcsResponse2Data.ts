/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosResponse } from '@nextcloud/axios'
import type { OCSResponse } from '@nextcloud/typings/ocs'

/**
 * Extract actual data from Axios OCS response
 * Just a small wrapper for nice code.
 *
 * @param response response returned by axios
 * @return The actual data out of the ocs response
 */
export default function OcsResponse2Data<T>(
	response: AxiosResponse<OCSResponse<T>>,
): T {
	return response.data.ocs.data
}
