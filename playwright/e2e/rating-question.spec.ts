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
import { waitForApiResponse } from '../support/helpers.ts'
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

test.describe('Rating question', () => {
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Rating test form')

		await form.addQuestion(QuestionType.Rating)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Rate the service')
		await questions[0].fillDescription('One star is poor')
	})

	test('Names the rating by its question and each option by its value', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)

		const group = submitView.getQuestion('Rate the service').getByRole('group')
		await expect(group).toHaveAccessibleName('Rate the service')
		await expect(group).toHaveAccessibleDescription('One star is poor')
		const options = group.getByRole('radio')
		await expect(options).toHaveCount(5)
		for (let value = 1; value <= 5; value++) {
			await expect(options.nth(value - 1)).toHaveAccessibleName(
				`${value} of 5`,
			)
		}
	})

	test('Restores the rating from local storage on reload', async ({
		topBar,
		submitView,
		page,
	}) => {
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '4 of 5')

		await page.reload()

		await expect(
			submitView
				.getQuestion('Rate the service')
				.getByRole('radio', { name: '4 of 5' }),
		).toBeChecked()
	})

	test('Restores the rating when editing a submission', async ({
		topBar,
		submitView,
		resultsView,
		page,
	}) => {
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '4 of 5')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()

		await page.goto(page.url().replace(/\/submit.*$/, '/results'))
		await resultsView.switchToResponses()
		await resultsView.editSubmission()

		await expect(
			submitView
				.getQuestion('Rate the service')
				.getByRole('radio', { name: '4 of 5' }),
		).toBeChecked()
	})

	test('Moves on from a rating on Enter instead of submitting', async ({
		page,
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '4 of 5')

		const question = submitView.getQuestion('Rate the service')
		const option = question.getByRole('radio', { name: '4 of 5' })
		for (const key of ['Enter', 'Shift+Enter']) {
			await option.press(key)
			await expect(submitView.submitButton).toBeFocused()
		}

		await option.focus()
		await page.keyboard.down('Enter')
		await page.keyboard.down('Enter')
		await page.keyboard.up('Enter')
		await expect(submitView.submitButton).toBeFocused()
		await expect(submitView.successMessage).toHaveCount(0)
	})

	test('Moves on with Enter to the chosen option of the next rating', async ({
		form,
		topBar,
		submitView,
	}) => {
		await form.addQuestion(QuestionType.Rating)
		const questions = await form.getQuestions()
		await questions[1].fillTitle('Rate the food')
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '4 of 5')
		await submitView.checkRadio('Rate the food', '3 of 5')

		await submitView
			.getQuestion('Rate the service')
			.getByRole('radio', { name: '4 of 5' })
			.press('Enter')

		await expect(
			submitView
				.getQuestion('Rate the food')
				.getByRole('radio', { name: '3 of 5' }),
		).toBeFocused()
	})

	test('Shows an error when a required rating is left empty', async ({
		form,
		topBar,
		submitView,
	}) => {
		const questions = await form.getQuestions()
		await questions[0].toggleRequired()
		await topBar.toggleView(FormsView.View)

		await submitView.submitButton.click()

		const question = submitView.getQuestion('Rate the service')
		await expect(
			question.getByText('You must answer this question'),
		).toBeVisible()
		await expect(question.getByRole('radio').first()).toHaveAttribute(
			'aria-invalid',
			'true',
		)
	})

	test('Focuses a new rating question and offers to insert a question after it', async ({
		form,
		page,
	}) => {
		await form.addQuestion(QuestionType.Rating)

		const questions = await form.getQuestions()
		await expect(questions[1].titleInput).toBeFocused()
		await expect(
			page.getByRole('button', {
				name: 'Insert question after question 1',
			}),
		).toHaveCount(1)
	})

	test('Offers as many options as the highest rating chosen in the editor', async ({
		form,
		page,
		topBar,
		submitView,
	}) => {
		const questions = await form.getQuestions()
		await questions[0].openActionsMenu()
		const saved = waitForApiResponse(page, 'PATCH')
		await page.getByRole('combobox', { name: 'Highest rating' }).click()
		await page.getByRole('option', { name: '7', exact: true }).click()
		await saved
		await page.keyboard.press('Escape')
		await topBar.toggleView(FormsView.View)

		const options = submitView.getQuestion('Rate the service').getByRole('radio')
		await expect(options).toHaveCount(7)
		await expect(options.last()).toHaveAccessibleName('7 of 7')
	})

	test('Saves the icon chosen last in the editor', async ({ form, page }) => {
		const questions = await form.getQuestions()
		await questions[0].openActionsMenu()
		const savedStars = page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.request().postDataJSON()?.keyValuePairs?.extraSettings
					?.ratingIcon === null,
		)
		await page.getByRole('radio', { name: 'Hearts' }).click({ force: true })
		await page.getByRole('radio', { name: 'Stars' }).click({ force: true })
		await savedStars
		await expect(page.getByRole('radio', { name: 'Stars' })).toBeChecked()
	})
})
