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
import { api, waitForApiResponse } from '../support/helpers.ts'
import {
	LinearScaleSubtype,
	QuestionType,
} from '../support/sections/QuestionType.ts'
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

		await form.addQuestion(QuestionType.LinearScale, LinearScaleSubtype.Rating)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Rate the service')
		await questions[0].fillDescription('One star is poor')
	})

	test('Names the rating by its question and each option by its value', async ({
		page,
		topBar,
		submitView,
	}) => {
		await expect(
			page.getByRole('group', { name: 'Rate the service' }),
		).toBeVisible()
		await topBar.toggleView(FormsView.View)

		const group = submitView.getQuestion('Rate the service').getByRole('group')
		await expect(group).toHaveAccessibleName('Rate the service')
		await expect(group).toHaveAccessibleDescription('One star is poor')
		const options = group.getByRole('radio')
		await expect(options).toHaveCount(5)
		for (let value = 1; value <= 5; value++) {
			await expect(options.nth(value - 1)).toHaveAccessibleName(`${value}`)
		}
	})

	test('Restores the rating from local storage on reload', async ({
		topBar,
		submitView,
		page,
	}) => {
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '4')

		await page.reload()

		await expect(
			submitView
				.getQuestion('Rate the service')
				.getByRole('radio', { name: '4', exact: true }),
		).toBeChecked()
	})

	test('Restores the rating when editing a submission', async ({
		topBar,
		submitView,
		resultsView,
		page,
	}) => {
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '4')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()

		await page.goto(page.url().replace(/\/submit.*$/, '/results'))
		await expect(page.getByText('Linear scale (Rating)')).toBeVisible()
		await resultsView.switchToResponses()
		await resultsView.editSubmission()

		await expect(
			submitView
				.getQuestion('Rate the service')
				.getByRole('radio', { name: '4', exact: true }),
		).toBeChecked()
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

		await submitView.checkRadio('Rate the service', '3')
		await expect(
			question.getByText('You must answer this question'),
		).toBeHidden()
		await expect(question.getByRole('radio').first()).not.toHaveAttribute(
			'aria-invalid',
		)
	})

	test('Moves on to the submit button on Enter instead of submitting', async ({
		topBar,
		submitView,
	}) => {
		await topBar.toggleView(FormsView.View)
		await submitView.checkRadio('Rate the service', '5')
		await submitView
			.getQuestion('Rate the service')
			.getByRole('radio', { name: '5', exact: true })
			.press('Enter')

		await expect(submitView.submitButton).toBeFocused()
		await expect(submitView.successMessage).toBeHidden()
	})

	test('Focuses a new rating question and offers to insert a question after it', async ({
		form,
		page,
	}) => {
		await form.addQuestion(QuestionType.LinearScale, LinearScaleSubtype.Rating)

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
		await expect(options.last()).toHaveAccessibleName('7')
	})

	test('Saves the icon chosen last in the editor', async ({ form, page }) => {
		const questions = await form.getQuestions()
		await questions[0].openActionsMenu()
		const savedIcon = (icon: string | null) =>
			page.waitForResponse(
				(response) =>
					response.request().method() === 'PATCH'
					&& response.request().postDataJSON()?.keyValuePairs
						?.extraSettings?.ratingIcon === icon,
			)
		const iconInput = page.getByRole('combobox', { name: 'Icon' })

		const savedHearts = savedIcon('heart')
		await iconInput.click()
		await page.getByRole('option', { name: 'Hearts' }).click()
		expect((await savedHearts).ok()).toBeTruthy()
		const savedStars = savedIcon(null)
		await iconInput.click()
		await page.getByRole('option', { name: 'Stars' }).click()
		expect((await savedStars).ok()).toBeTruthy()
		await expect(
			page.getByRole('listitem').filter({ has: iconInput }),
		).toContainText('Stars')
	})
})

test('The results show the average rating', async ({ page, resultsView }) => {
	const created = await api(page.request, 'POST', '/forms')
	await api(page.request, 'PATCH', `/forms/${created.id}`, {
		keyValuePairs: { submitMultiple: true },
	})
	const rating = await api(
		page.request,
		'POST',
		`/forms/${created.id}/questions`,
		{
			type: 'linearscale',
			subtype: 'rating',
			text: 'Rate the service',
		},
	)
	for (const answer of ['5', '4', '4']) {
		await api(page.request, 'POST', `/forms/${created.id}/submissions`, {
			answers: { [rating.id]: [answer] },
		})
	}
	await api(page.request, 'POST', `/forms/${created.id}/submissions`, {
		answers: {},
	})
	const ratingUrl = `/forms/${created.id}/questions/${rating.id}`
	await api(page.request, 'PATCH', ratingUrl, {
		keyValuePairs: {
			extraSettings: { questionType: 'rating', optionsHighest: 7 },
		},
	})
	await api(page.request, 'POST', `/forms/${created.id}/submissions`, {
		answers: { [rating.id]: ['7'] },
	})
	await api(page.request, 'PATCH', ratingUrl, {
		keyValuePairs: { extraSettings: { questionType: 'rating' } },
	})
	await page.goto(`apps/forms/${created.hash}/results`)
	await page.waitForURL(/\/results\/summary$/)

	const summary = resultsView.getSummaryForQuestion('Rate the service')
	await expect(summary.getByText('4.3 of 5', { exact: true })).toBeVisible()
	await expect(summary.getByText('3 ratings', { exact: true })).toBeVisible()
	await expect(
		summary.getByText('1 rating outside the scale is not counted'),
	).toBeVisible()
	await expect(
		summary.getByText('Average rating 4.3 of 5, from 3 ratings'),
	).toHaveCount(1)
	const bars = summary.getByRole('meter')
	await expect(bars).toHaveCount(7)
	await expect(bars.nth(0)).toHaveAccessibleName('0 (0%): 1')
	await expect(bars.nth(3)).toHaveAccessibleName('2 (40%): 4')
	await expect(bars.nth(4)).toHaveAccessibleName('1 (20%): 5')
	await expect(bars.nth(5)).toHaveAccessibleName('1 (20%): 7')
	await expect(bars.nth(6)).toHaveAccessibleName('1 (20%): No response')
})
