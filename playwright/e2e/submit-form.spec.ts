/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as formTest } from '../support/fixtures/form'
import { test as topBarTest } from '../support/fixtures/topBar'
import { test as submitTest } from '../support/fixtures/submit'
import { QuestionType } from '../support/sections/QuestionType'
import { FormsView } from '../support/sections/TopBarSection'

const test = mergeTests(
	randomUserTest,
	appNavigationTest,
	formTest,
	topBarTest,
	submitTest,
)

test.describe('Form submission', () => {
	// Setup: create a form with 4 question types
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Submission test form')

		// Add a short answer question
		await form.addQuestion(QuestionType.ShortAnswer)
		const questions1 = await form.getQuestions()
		await questions1[0].fillTitle('Your name')

		// Add a checkboxes question with options
		await form.addQuestion(QuestionType.Checkboxes)
		const questions2 = await form.getQuestions()
		await questions2[1].fillTitle('Favorite fruits')
		await questions2[1].addAnswer('Apple')
		await questions2[1].addAnswer('Banana')
		await questions2[1].addAnswer('Cherry')

		// Add a dropdown question with options
		await form.addQuestion(QuestionType.Dropdown)
		const questions3 = await form.getQuestions()
		await questions3[2].fillTitle('Your country')
		await questions3[2].addAnswer('Germany')
		await questions3[2].addAnswer('France')
		await questions3[2].addAnswer('Spain')

		// Add a date question
		await form.addQuestion(QuestionType.Date)
		const questions4 = await form.getQuestions()
		await questions4[3].fillTitle('Birth date')
	})

	test('Fill and submit a form', async ({ topBar, submitView }) => {
		await topBar.toggleView(FormsView.View)

		await submitView.fillText('Your name', 'Alice')
		await submitView.checkOption('Favorite fruits', 'Apple')
		await submitView.checkOption('Favorite fruits', 'Cherry')
		await submitView.selectDropdown('Your country', 'Germany')

		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})

	test('Partial submission succeeds when no fields are required', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)

		// Only fill the short answer, leave everything else empty
		await submitView.fillText('Your name', 'Bob')

		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})
})
