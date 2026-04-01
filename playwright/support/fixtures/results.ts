/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { ResultsSection } from '../sections/ResultsSection'

interface ResultsFixture {
	resultsView: ResultsSection
}

export const test = baseTest.extend<ResultsFixture>({
	resultsView: async ({ page }, use) => {
		const resultsView = new ResultsSection(page)
		await use(resultsView)
	},
})
