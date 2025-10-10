/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'

/**
 * Creates a cancelable axios 'request object'.
 *
 * @param {Function} request the axios promise request
 * @return {object}
 */
function CancelableRequest(request) {
	/**
	 * Generate an axios cancel token
	 */
	const CancelToken = axios.CancelToken
	const source = CancelToken.source()

	/**
	 * Execute the request
	 *
	 * @param {string} url the url to send the request to
	 * @param {object} [options] optional config for the request
	 */
	const fetch = async function (url, options) {
		return request(url, { cancelToken: source.token, ...options })
	}
	return {
		request: fetch,
		cancel: source.cancel,
	}
}

export default CancelableRequest
