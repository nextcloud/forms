/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { TopBarSection } from '../sections/TopBarSection'

interface TopBarFixture {
	topBar: TopBarSection
}

export const test = baseTest.extend<TopBarFixture>({
	topBar: async ({ page }, use) => {
		const form = new TopBarSection(page)
		await use(form)
	},
})
