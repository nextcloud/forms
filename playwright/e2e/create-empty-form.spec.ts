/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as formTest } from '../support/fixtures/form'

const test = mergeTests(randomUserTest, appNavigationTest, formTest)

test.beforeEach(async ({ page }) => {
	await page.goto('apps/forms')
	await page.waitForURL(/apps\/forms$/)
})

test.describe('No forms created - empty content', () => {
	test('It shows the empty content', async ({ page }) => {
		await expect(page.getByText('No forms created yet')).toBeVisible()
		await expect(
			page.getByRole('button', { name: 'Create a form' }),
		).toBeVisible()
	})

	test('Use button to create new form', async ({ page, appNavigation }) => {
		const oldNumber = (await appNavigation.ownFormsLocator.all()).length

		await page.getByRole('button', { name: 'Create a form' }).click()
		await page.waitForURL(/apps\/forms\/.+/)

		const newNumber = (await appNavigation.ownFormsLocator.all()).length
		expect(newNumber - oldNumber).toBe(1)
	})
})

test('Use app navigation to create new form', async ({ appNavigation, form }) => {
	await appNavigation.clickNewForm()

	// check we are in edit mode by default and the heading is focussed
	await expect(form.titleField).toBeFocused()
})

test('Form name updated in navigation', async ({ appNavigation, form }) => {
	await appNavigation.clickNewForm()

	// check we are in edit mode by default and the heading is focussed
	await expect(form.titleField).toBeFocused()

	// check the form exists in the navigation
	await expect(appNavigation.getOwnForm('New form')).toBeVisible()

	// Update the title
	await form.fillTitle('My example form')

	// See the title is updated
	await expect(appNavigation.getOwnForm('My example form')).toBeVisible()
})
