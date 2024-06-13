/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { FormSection } from '../sections/FormSection'

interface FormFixture {
	form: FormSection
}

export const test = baseTest.extend<FormFixture>({
	form: async ({ page }, use) => {
		const form = new FormSection(page)
		await use(form)
	},
})
