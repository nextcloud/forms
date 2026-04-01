/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as formTest } from '../support/fixtures/form'
import { QuestionType } from '../support/sections/QuestionType'
import { waitForApiResponse } from '../support/helpers'

const test = mergeTests(
	randomUserTest,
	appNavigationTest,
	formTest,
)

test.describe('Form sharing', () => {
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Sharing test form')

		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Test question')

		// Open the sidebar via the Share button in the TopBar
		await page.getByRole('button', { name: /Share/ }).click()
		// Sidebar opens on the Sharing tab by default — wait for it
		await expect(
			page.getByRole('complementary').getByText('Share link'),
		).toBeVisible()
	})

	test('Add a public share link', async ({ page }) => {
		// New forms start without a public link share.
		// NcActions with a single child renders it as an inline button.
		const shareLinkRow = page.locator('.share-div--link')
		const addLinkButton = shareLinkRow.getByRole('button', {
			name: /Add link/,
		})
		await expect(addLinkButton).toBeVisible()

		const linkCreated = waitForApiResponse(page, 'POST')
		await addLinkButton.click()
		await linkCreated

		// After adding, the share link entry renders NcActions with :inline="1",
		// so the first action ("Copy to clipboard") appears as an inline button.
		await expect(
			shareLinkRow.getByRole('link', { name: /Copy to clipboard/ }),
		).toBeVisible()
	})

	test('Remove a public share link', async ({ page }) => {
		// First, add a link
		const shareLinkRow = page.locator('.share-div--link')

		const linkCreated = waitForApiResponse(page, 'POST')
		await shareLinkRow.getByRole('button', { name: /Add link/ }).click()
		await linkCreated

		// The inline "Copy to clipboard" action should now be visible
		await expect(
			shareLinkRow.getByRole('link', { name: /Copy to clipboard/ }),
		).toBeVisible()

		// Open the overflow menu (the "Actions" toggle) to find "Remove link".
		// NcActions :inline="1" renders the first action inline and puts the
		// rest behind an overflow toggle button.
		await shareLinkRow.getByRole('button', { name: /Actions/ }).click()

		const linkDeleted = waitForApiResponse(page, 'DELETE')
		await page.getByRole('menuitem', { name: /Remove link/ }).click()
		await linkDeleted

		// After removal, the share link row reverts to the "no link" state.
		// NcActions collapses a single action to an inline button.
		await expect(
			shareLinkRow.getByRole('button', { name: /Add link/ }),
		).toBeVisible()
	})
})
