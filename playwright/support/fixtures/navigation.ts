/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { AppNavigationSection } from '../sections/AppNavigationSection'

interface AppNavigationFixture {
	appNavigation: AppNavigationSection
}

export const test = baseTest.extend<AppNavigationFixture>({
	appNavigation: async ({ page }, use) => {
		const appNavigation = new AppNavigationSection(page)
		await use(appNavigation)
	},
})
