/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'

const test = mergeTests(randomUserTest, appNavigationTest, formTest)

test.beforeEach(async ({ page, appNavigation, form }) => {
	await page.goto('apps/forms', { waitUntil: 'networkidle' })
	await page.waitForURL(/apps\/forms\/$/)

	await appNavigation.clickNewForm()
	await form.fillTitle('Team offsite')
	await appNavigation.clickNewForm()
	await expect(form.titleField).toHaveValue('')
	await form.fillTitle('Budget review')
	await expect(appNavigation.ownFormsLocator).toHaveCount(2)
})

test('The filter shows only the forms whose title matches', async ({
	appNavigation,
}) => {
	await expect(appNavigation.getOwnForm('Budget review')).toBeVisible()

	await appNavigation.filterLocator.fill('ÖFFSITE')

	await expect(appNavigation.getOwnForm('Team offsite')).toBeVisible()
	await expect(appNavigation.getOwnForm('Budget review')).toBeHidden()
})

test('The filter tells when no form matches and can be cleared', async ({
	appNavigation,
}) => {
	await appNavigation.filterLocator.fill('does not exist')

	await expect(appNavigation.navigationLocator.getByRole('status')).toHaveText(
		'No forms match your filter',
	)

	await appNavigation.navigationLocator
		.getByRole('button', { name: 'Clear search' })
		.click()

	await expect(appNavigation.filterLocator).toHaveValue('')
	await expect(appNavigation.ownFormsLocator).toHaveCount(2)
})

test('Creating a form clears the filter', async ({ appNavigation, form }) => {
	await appNavigation.filterLocator.fill('budget')
	await appNavigation.clickNewForm()
	await expect(form.titleField).toHaveValue('')

	await expect(appNavigation.filterLocator).toHaveValue('')
	await expect(appNavigation.getOwnForm('New form')).toBeVisible()
})
