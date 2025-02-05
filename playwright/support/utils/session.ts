/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runExec, addUser } from '@nextcloud/e2e-test-server/docker'
import { expect, type APIRequestContext } from '@playwright/test'

/**
 * Restore database and data folder for tests
 */
export function restoreDatabase() {
	runExec('rm -rf data && tar -xf backup.tar')
}

/**
 * Helper to login on the Nextcloud instance
 * @param request API request object
 * @param user The username to login
 * @param password The password to login
 */
export async function login(
	request: APIRequestContext,
	user: string,
	password: string,
) {
	const tokenResponse = await request.get('./csrftoken')
	expect(tokenResponse.status()).toBe(200)
	const requesttoken = (await tokenResponse.json()).token

	const loginResponse = await request.post('./login', {
		form: {
			user,
			password,
			requesttoken,
		},
		headers: {
			Origin: tokenResponse.url().replace(/index.php.*/, ''),
		},
	})
	expect(loginResponse.status()).toBe(200)

	const response = await request.get('apps/files')
	expect(response.status()).toBe(200)
}

/**
 * Create a new random user (password is set to the UID)
 * @return The UID of the new user
 */
export async function createRandomUser(): Promise<string> {
	const uid = (Math.random() + 1).toString(36).substring(7)
	await addUser(uid)
	return uid
}
