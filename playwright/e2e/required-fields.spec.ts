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

test.describe('Required field validation', () => {
	// Setup: create form with 2 questions, mark the first as required
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Required fields test')

		// Add a required short answer
		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Required field')

		// Toggle required via the actions menu.
		// Question.vue has an NcActionCheckbox with label "Required"
		// inside the NcActions menu.
		await questions[0].section
			.getByRole('button', { name: 'Actions' })
			.click()
		await page.getByRole('menuitemcheckbox', { name: 'Required' }).click()
		// Close the menu by pressing Escape
		await page.keyboard.press('Escape')

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
