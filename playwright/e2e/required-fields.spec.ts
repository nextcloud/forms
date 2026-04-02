/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
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
)

test.describe('Required field validation', () => {
	// Setup: create form with 2 questions, mark the first as required
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Required fields test')

		// Add a required short answer
		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Required field')

		await questions[0].toggleRequired()

		// Add a non-required question
		await form.addQuestion(QuestionType.ShortAnswer)
		const questions2 = await form.getQuestions()
		await questions2[1].fillTitle('Optional field')
	})

	test('Submit with empty required field shows validation error', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)

		// Fill only the optional field
		await submitView.fillText('Optional field', 'some text')

		// Try to submit — should fail due to required field
		await submitView.submitButton.click()

		// The form should NOT show success message (submission blocked by HTML5 validation)
		await expect(submitView.successMessage).not.toBeVisible()
	})

	test('Submit succeeds after filling required field', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)

		await submitView.fillText('Required field', 'my answer')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})
})
