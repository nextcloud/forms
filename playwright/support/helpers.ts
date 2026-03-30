/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page, Response } from '@playwright/test'

const FORMS_API_PATH = '/api/v3/forms/'

/**
 * Wait for a Forms API response matching the given HTTP method.
 * Must be called BEFORE the action that triggers the request.
 */
export function waitForApiResponse(
	page: Page,
	method: string,
): Promise<Response> {
	return page.waitForResponse(
		(response) =>
			response.request().method() === method
			&& response.request().url().includes(FORMS_API_PATH),
	)
}
