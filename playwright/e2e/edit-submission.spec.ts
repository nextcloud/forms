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
import { GridSubtype, QuestionType } from '../support/sections/QuestionType.ts'
import { FormsView } from '../support/sections/TopBarSection.ts'

const test = mergeTests(
	randomUserTest,
	appNavigationTest,
	formTest,
	topBarTest,
	submitTest,
	resultsTest,
)

test.describe('Edit submission', () => {
	// Setup: create a form with grid questions and submit a response
	test.beforeEach(async ({ page, appNavigation, form, topBar, submitView }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Edit submission test form')

		// Add a checkbox grid question
		await form.addQuestion(QuestionType.Grid, GridSubtype.Checkboxes)
		const questions1 = await form.getQuestions()
		await questions1[0].fillTitle('Skills matrix')
		await questions1[0].addColumn('Python')
		await questions1[0].addColumn('PHP')
		await questions1[0].addRow('Beginner')
		await questions1[0].addRow('Expert')

		// Add a radio grid question
		await form.addQuestion(QuestionType.Grid, GridSubtype.RadioButtons)
		const questions2 = await form.getQuestions()
		await questions2[1].fillTitle('Satisfaction')
		await questions2[1].addColumn('Good')
		await questions2[1].addColumn('Bad')
		await questions2[1].addRow('Service')
		await questions2[1].addRow('Price')

		// Switch to View mode and submit a response
		await topBar.toggleView(FormsView.View)
		await submitView.checkGridCell('Skills matrix', 'Beginner', 'Python')
		await submitView.checkGridCell('Skills matrix', 'Expert', 'PHP')
		await submitView.checkGridCell('Satisfaction', 'Service', 'Good')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})

	test('Grid answers are restored when editing a submission', async ({
		page,
		resultsView,
		submitView,
	}) => {
		// Open the submission for editing from the responses tab
		await page.goto(page.url().replace(/\/submit.*$/, '/results'))
		await resultsView.switchToResponses()
		await resultsView.editSubmission()

		// Previously submitted grid answers are restored
		await expect(
			await submitView.getGridCellInput('Skills matrix', 'Beginner', 'Python'),
		).toBeChecked()
		await expect(
			await submitView.getGridCellInput('Skills matrix', 'Expert', 'PHP'),
		).toBeChecked()
		await expect(
			await submitView.getGridCellInput('Skills matrix', 'Beginner', 'PHP'),
		).not.toBeChecked()
		await expect(
			await submitView.getGridCellInput('Satisfaction', 'Service', 'Good'),
		).toBeChecked()

		// The restored submission can be edited and submitted again
		await submitView.checkGridCell('Skills matrix', 'Beginner', 'PHP')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})
})
