/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext, Page, Response } from '@playwright/test'

import { expect } from '@playwright/test'

const FORMS_API_PATH = '/api/v3/forms/'

/**
 * Wait for a Forms API response matching the given HTTP method.
 * Must be called BEFORE the action that triggers the request.
 *
 * @param page the current page
 * @param method the request method
 */
export function waitForApiResponse(page: Page, method: string): Promise<Response> {
	return page.waitForResponse(
		(response) =>
			response.request().method() === method
			&& response.request().url().includes(FORMS_API_PATH),
	)
}

/**
 * Call the Forms OCS API as the logged in user
 *
 * @param request the request context of the page
 * @param method the HTTP method
 * @param path the path below `api/v3`
 * @param data the JSON body
 */
export async function api(
	request: APIRequestContext,
	method: string,
	path: string,
	data?: unknown,
) {
	const response = await request.fetch(`../ocs/v2.php/apps/forms/api/v3${path}`, {
		method,
		data,
		headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	expect(response.ok()).toBeTruthy()
	return (await response.json()).ocs.data
}
