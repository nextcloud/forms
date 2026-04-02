/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { SubmitSection } from '../sections/SubmitSection.ts'

interface SubmitFixture {
	submitView: SubmitSection
}

export const test = baseTest.extend<SubmitFixture>({
	submitView: async ({ page }, use) => {
		const submitView = new SubmitSection(page)
		await use(submitView)
	},
})
