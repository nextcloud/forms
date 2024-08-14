/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as topBarTest } from '../support/fixtures/topBar'
import { test as formTest } from '../support/fixtures/form'
import { FormsView } from '../support/sections/TopBarSection'

const test = mergeTests(randomUserTest, appNavigationTest, formTest, topBarTest)

test.beforeEach(async ({ page }) => {
	await page.goto('apps/forms')
	await page.waitForURL(/apps\/forms$/)
})

test.describe('Form description', () => {
	test('Can edit the description', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.fillDescription('Hello this is an example')

		await topBar.toggleView(FormsView.View)

		await expect(page.locator('.form-desc')).toContainText(
			'Hello this is an example',
		)
	})

	test('Can use Markdown in the description', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.fillDescription('Hello **this** is an example')

		await topBar.toggleView(FormsView.View)

		await expect(page.locator('.form-desc')).toContainText(
			'Hello this is an example',
		)
		await expect(page.locator('.form-desc').locator('strong')).toContainText(
			'this',
		)
	})

	test(
		'Markdown links are opened in a new tab',
		{
			annotation: {
				type: 'issue',
				description: 'https://github.com/nextcloud/forms/issues/1680',
			},
		},
		async ({ appNavigation, form, topBar, page }) => {
			await appNavigation.clickNewForm()
			await form.fillTitle('Test form')

			await form.fillDescription('The link: [link-name](http://example.com)')

			await topBar.toggleView(FormsView.View)

			await expect(page.locator('.form-desc')).toContainText(
				'The link: link-name',
			)
			const link = page.locator('.form-desc').getByRole('link')

			await expect(link).toContainText('link-name')
			await expect(link).toHaveAttribute('href', 'http://example.com')
			await expect(link).toHaveAttribute('target', '_blank')

			// check opening works
			// lets mock the response to not need to query that server for real
			page.context().route(/example\.com/, (route) =>
				route.fulfill({
					body: '<!doctype html><meta charset=utf-8><title>success</title>',
					status: 200,
					contentType: 'text/html; charset=utf-8',
				}),
			)
			const pagePromise = page.context().waitForEvent('page', {})
			await link.click()
			const newPage = await pagePromise
			await expect(newPage).toHaveTitle('success')
		},
	)
})
