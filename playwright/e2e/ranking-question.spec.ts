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

test.describe('Ranking question', () => {
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Ranking test form')

		await form.addQuestion(QuestionType.Ranking)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Rank snacks')
		await questions[0].addAnswer('Pretzels')
		await questions[0].addAnswer('Popcorn')
		await questions[0].addAnswer('Nuts')
	})

	test('Restores unsubmitted ranking from local storage on reload', async ({
		topBar,
		submitView,
		page,
	}) => {
		await topBar.toggleView(FormsView.View)

		await submitView.rankOption('Rank snacks', 'Pretzels')
		await submitView.rankOption('Rank snacks', 'Popcorn')

		await page.reload()

		const question = submitView.getQuestion('Rank snacks')
		await expect(
			question.getByRole('button', { name: 'Remove from ranking' }),
		).toHaveCount(2)
	})

	test('Clear form resets ranked options', async ({ topBar, submitView }) => {
		await topBar.toggleView(FormsView.View)

		await submitView.rankOption('Rank snacks', 'Pretzels')
		await submitView.rankOption('Rank snacks', 'Popcorn')
		await submitView.clearForm()

		const question = submitView.getQuestion('Rank snacks')
		await expect(
			question.getByRole('button', { name: 'Remove from ranking' }),
		).toHaveCount(0)
		await expect(
			question.getByRole('button', { name: 'Pretzels' }),
		).toBeVisible()
		await expect(question.getByRole('button', { name: 'Popcorn' })).toBeVisible()
	})

	test('Required ranking blocks submit until all options are ranked', async ({
		topBar,
		submitView,
		form,
	}) => {
		const questions = await form.getQuestions()
		await questions[0].toggleRequired()

		await topBar.toggleView(FormsView.View)

		await submitView.submitButton.click()
		await expect(submitView.successMessage).not.toBeVisible()

		await submitView.rankOption('Rank snacks', 'Pretzels')
		await submitView.submitButton.click()
		await expect(submitView.successMessage).not.toBeVisible()

		await submitView.rankOption('Rank snacks', 'Popcorn')
		await submitView.rankOption('Rank snacks', 'Nuts')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})

	test('Partial ranking submission is blocked by required validation', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)

		// Rank only 2 out of 3 items
		await submitView.rankOption('Rank snacks', 'Pretzels')
		await submitView.rankOption('Rank snacks', 'Popcorn')

		// Try to submit — should fail
		await submitView.submitButton.click()

		// Verify error prevents submission (success message hidden)
		await expect(submitView.successMessage).not.toBeVisible()
	})

	test('Complete ranking submission succeeds after partial attempt', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)

		// Rank first 2 items
		await submitView.rankOption('Rank snacks', 'Pretzels')
		await submitView.rankOption('Rank snacks', 'Popcorn')

		// Submit attempt fails (partial ranking)
		await submitView.submitButton.click()
		await expect(submitView.successMessage).not.toBeVisible()

		// Complete the ranking
		await submitView.rankOption('Rank snacks', 'Nuts')

		// Now submit should succeed
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})

	test('Multiple ranking questions maintain separate drag contexts', async ({
		form,
		topBar,
		submitView,
	}) => {
		// Add a second ranking question
		await form.addQuestion(QuestionType.Ranking)
		const questions = await form.getQuestions()
		await questions[1].fillTitle('Rank preferences')
		await questions[1].addAnswer('Option X')
		await questions[1].addAnswer('Option Y')
		await questions[1].addAnswer('Option Z')

		await topBar.toggleView(FormsView.View)

		// Rank first question completely
		await submitView.rankOption('Rank snacks', 'Pretzels')
		await submitView.rankOption('Rank snacks', 'Popcorn')
		await submitView.rankOption('Rank snacks', 'Nuts')

		// Rank second question partially
		await submitView.rankOption('Rank preferences', 'Option X')
		await submitView.rankOption('Rank preferences', 'Option Z')

		// Verify both rankings are correct
		const q1 = submitView.getQuestion('Rank snacks')
		const q2 = submitView.getQuestion('Rank preferences')

		await expect(
			q1.getByRole('button', { name: 'Remove from ranking' }),
		).toHaveCount(3)
		await expect(
			q2.getByRole('button', { name: 'Remove from ranking' }),
		).toHaveCount(2)

		// Submit should require q2 to be complete
		await submitView.submitButton.click()
		await expect(submitView.successMessage).not.toBeVisible()

		// Complete q2
		await submitView.rankOption('Rank preferences', 'Option Y')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})
})
