/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'

type RequestFn = (url: string, options?: unknown) => Promise<unknown>

/**
 * Creates a cancelable axios 'request object'.
 *
 * @param request the axios promise request
 * @return
 */
export default function CancelableRequest(request: RequestFn) {
	/**
	 * Generate an axios cancel token
	 */
	const CancelToken = axios.CancelToken
	const source = CancelToken.source()

	/**
	 * Execute the request
	 *
	 * @param url the url to send the request to
	 * @param [options] optional config for the request
	 */
	const fetch = async function (url: string, options?: Array<unknown>) {
		return request(url, { cancelToken: source.token, ...options })
	}
	return {
		request: fetch,
		cancel: source.cancel,
	}
}
