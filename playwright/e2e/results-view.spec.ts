/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as resultsTest } from '../support/fixtures/results.ts'
import { test as submitTest } from '../support/fixtures/submit.ts'
import { test as topBarTest } from '../support/fixtures/topBar.ts'
import { QuestionType } from '../support/sections/QuestionType.ts'
import { FormsView } from '../support/sections/TopBarSection.ts'

const test = mergeTests(
	randomUserTest,
	appNavigationTest,
	formTest,
	topBarTest,
	submitTest,
	resultsTest,
)

test.describe('Results view', () => {
	// Setup: create form, add questions, submit a response, go to results
	test.beforeEach(async ({ page, appNavigation, form, topBar, submitView }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Results test form')

		// Add a short answer question
		await form.addQuestion(QuestionType.ShortAnswer)
		const questions1 = await form.getQuestions()
		await questions1[0].fillTitle('Your name')

		// Add a checkboxes question
		await form.addQuestion(QuestionType.Checkboxes)
		const questions2 = await form.getQuestions()
		await questions2[1].fillTitle('Pick colors')
		await questions2[1].addAnswer('Red')
		await questions2[1].addAnswer('Green')
		await questions2[1].addAnswer('Blue')

		// Switch to View mode and submit a response
		await topBar.toggleView(FormsView.View)
		await submitView.fillText('Your name', 'Alice')
		await submitView.checkOption('Pick colors', 'Red')
		await submitView.checkOption('Pick colors', 'Blue')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()

		// Navigate to Results view. The router will redirect to the summary tab.
		await page.goto(page.url().replace(/\/submit.*$/, '/results'))
		await page.waitForURL(/\/results\/summary$/)
	})

	test('Summary tab shows submitted data', async ({ resultsView }) => {
		// Summary is the default active tab
		await expect(resultsView.summaryTab).toBeChecked()

		// Verify the response count shows 1
		await expect(resultsView.responseCount).toBeVisible()

		// The summary for each question should be visible
		const nameSummary = resultsView.getSummaryForQuestion('Your name')
		await expect(nameSummary).toBeVisible()
		await expect(nameSummary).toContainText('Alice')

		const colorSummary = resultsView.getSummaryForQuestion('Pick colors')
		await expect(colorSummary).toBeVisible()
	})

	test('Responses tab shows individual submission', async ({ resultsView }) => {
		await resultsView.switchToResponses()

		// Should show the individual submission with the answers
		await expect(resultsView.responsesTab).toBeChecked()
		await expect(resultsView.responseCount).toBeVisible()
		await expect(resultsView.page).toHaveURL(/\/results\/responses$/)
	})

	test('Tab switching between Summary and Responses updates the URL', async ({
		resultsView,
	}) => {
		// Start on Summary
		await expect(resultsView.summaryTab).toBeChecked()
		await expect(resultsView.page).toHaveURL(/\/results\/summary$/)

		// Switch to Responses
		await resultsView.switchToResponses()
		await expect(resultsView.responsesTab).toBeChecked()
		await expect(resultsView.summaryTab).not.toBeChecked()
		await expect(resultsView.page).toHaveURL(/\/results\/responses$/)

		// Switch back to Summary
		await resultsView.switchToSummary()
		await expect(resultsView.summaryTab).toBeChecked()
		await expect(resultsView.responsesTab).not.toBeChecked()
		await expect(resultsView.page).toHaveURL(/\/results\/summary$/)
	})

	test('Navigating to /results redirects to last viewed subview', async ({ page }) => {
		// Start on the responses tab
		await page.goto(page.url().replace(/\/summary$/, '/responses'))
		await page.waitForURL(/\/results\/responses$/)

		// Navigate to the parent results route
		await page.goto(page.url().replace(/\/responses$/, ''))
		await page.waitForURL(/\/results\/responses$/)
		await expect(page).toHaveURL(/\/results\/responses$/)
	})
})

