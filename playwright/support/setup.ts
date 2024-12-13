/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as setup } from '@playwright/test'
import { configureNextcloud, docker } from '@nextcloud/cypress/docker'

/**
 * We use this to ensure Nextcloud is configured correctly before running our tests
 *
 * This can not be done in the webserver startup process,
 * as that only checks for the URL to be accessible which happens already before everything is configured.
 */
setup('Configure Nextcloud', async () => {
	const containerName = 'nextcloud-cypress-tests_forms'
	const container = docker.getContainer(containerName)
	await configureNextcloud(['forms', 'viewer'], undefined, container)
})
