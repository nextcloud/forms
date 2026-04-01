/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as submitTest } from '../support/fixtures/submit'
import { test as topBarTest } from '../support/fixtures/topBar'
import { waitForApiResponse } from '../support/helpers'
import { QuestionType } from '../support/sections/QuestionType'
import { FormsView } from '../support/sections/TopBarSection'

const test = mergeTests(
	randomUserTest,
	appNavigationTest,
	formTest,
	topBarTest,
	submitTest,
)

test.describe('Form settings', () => {
	// Setup: create a form with one question, open the Settings sidebar tab
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Settings test form')

		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Your answer')

		// Open sidebar and switch to Settings tab
		await page.getByRole('button', { name: /Share/ }).click()
		const settingsTab = page.getByRole('tab', { name: /Settings/ })
		await settingsTab.click()
		await expect(
			page.getByRole('checkbox', { name: /Close form/ }),
		).toBeVisible()
	})

	test('Closing a form blocks submissions', async ({ page, topBar }) => {
		const saved = waitForApiResponse(page, 'PATCH')
		await page
			.getByRole('checkbox', { name: /Close form/ })
			.click({ force: true })
		await saved

		await topBar.toggleView(FormsView.View)

		// NcEmptyContent renders with role="note" — scope to main to avoid
		// matching the "Form closed" status text in the sidebar navigation.
		const main = page.getByRole('main')
		await expect(main.getByText('Form closed')).toBeVisible()
		await expect(
			main.getByText('This form was closed and is no longer taking responses'),
		).toBeVisible()
	})

	test('Reopening a closed form allows submissions', async ({
		page,
		topBar,
		submitView,
	}) => {
		// Close the form
		const closed = waitForApiResponse(page, 'PATCH')
		await page
			.getByRole('checkbox', { name: /Close form/ })
			.click({ force: true })
		await closed

		// Reopen the form
		const reopened = waitForApiResponse(page, 'PATCH')
		await page
			.getByRole('checkbox', { name: /Close form/ })
			.click({ force: true })
		await reopened

		await topBar.toggleView(FormsView.View)

		// Form should be accessible — questions visible and submit button present
		await expect(submitView.submitButton).toBeVisible()
	})

	test('Anonymous mode shows anonymous message on submit view', async ({
		page,
		topBar,
	}) => {
		const saved = waitForApiResponse(page, 'PATCH')
		await page
			.getByRole('checkbox', { name: /Store responses anonymously/ })
			.click({ force: true })
		await saved

		await topBar.toggleView(FormsView.View)

		await expect(page.getByText('Responses are anonymous.')).toBeVisible()
	})

	test('Non-anonymous mode shows account-connected message on edit view', async ({
		page,
	}) => {
		// The Create (edit) view always shows this message when anonymous
		// is off, because the editor is always in a logged-in context.
		// The Submit route doesn't receive isLoggedIn from the router, so
		// the message only appears on the edit view.
		await expect(
			page.getByText('Responses are connected to your account.'),
		).toBeVisible()
	})
})
