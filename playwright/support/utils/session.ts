/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getContainer } from '@nextcloud/cypress/docker'
import { expect, type APIRequestContext } from '@playwright/test'

/**
 * Run a shell command on the docker container
 * @param command The command to run on the docker container
 * @param options Options to pass
 * @param options.env Process environment to pass
 * @param options.user User to use for executing the command
 * @param options.rejectOnError Reject the returned promise in case of non-zero exit code
 */
export async function runShell(
	command: string,
	options?: {
		user?: string
		rejectOnError?: boolean
		env?: Record<string, string | number>
	},
) {
	const container = getContainer()

	const exec = await container.exec({
		Cmd: ['sh', '-c', command],
		Env: Object.entries(options?.env ?? {}).map(
			([name, value]) => `${name}=${value}`,
		),
		User: options?.user,
		AttachStderr: true,
		AttachStdout: true,
	})

	const stream = await exec.start({})
	return new Promise((resolve, reject) => {
		let data = ''
		stream.on('data', (chunk: string) => {
			data += chunk
		})
		stream.on('error', (error: unknown) => reject(error))
		stream.on('end', async () => {
			const inspect = await exec.inspect({})
			if (options?.rejectOnError !== false && inspect.ExitCode) {
				reject(data)
			} else {
				resolve(data)
			}
		})
	})
}

/**
 * Run an OCC command
 * @param command OCC command to run
 * @param options Options to pass
 * @param options.env Process environment to pass
 * @param options.rejectOnError Reject the returned promise in case of non-zero exit code
 */
export async function runOCC(
	command: string,
	options?: {
		env?: Record<string, string | number>
		rejectOnError?: boolean
	},
) {
	return await runShell(`php ./occ ${command}`, {
		...options,
		user: 'www-data',
	})
}

/**
 * Restore database and data folder for tests
 */
export function restoreDatabase() {
	runShell('rm -rf data && tar -xf backup.tar')
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
	await runOCC(`user:add --password-from-env ${uid}`, {
		env: { OC_PASS: uid },
	})
	return uid
}
