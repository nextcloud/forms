/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as submitTest } from '../support/fixtures/submit.ts'
import { test as topBarTest } from '../support/fixtures/topBar.ts'
import { api, waitForApiResponse } from '../support/helpers.ts'
import { QuestionType } from '../support/sections/QuestionType.ts'
import { FormsView } from '../support/sections/TopBarSection.ts'

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
		await page.waitForURL(/apps\/forms\/?$/)
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

	test('The draft of a form opened from the navigation is restored', async ({
		page,
		appNavigation,
		form,
		topBar,
		submitView,
	}) => {
		const submitUrl = page.url().replace(/\/edit$/, '/submit')
		await appNavigation.clickNewForm()
		await expect(form.titleField).toHaveValue('')
		await form.fillTitle('Draft form')
		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Pet name')
		await topBar.toggleView(FormsView.View)
		await submitView.fillText('Pet name', 'Rex')

		page.once('dialog', (dialog) => dialog.accept())
		await page.goto(submitUrl)
		await expect(submitView.getQuestion('Your name')).toBeVisible()
		await appNavigation.getOwnForm('Draft form').click()

		await expect(
			submitView.getQuestion('Pet name').getByRole('textbox'),
		).toHaveValue('Rex')
		await expect(page.getByRole('dialog', { name: 'Clear form' })).toHaveCount(0)
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

	test('A restored draft leaves out deleted options', async ({
		page,
		form,
		topBar,
		submitView,
	}) => {
		const editUrl = page.url()
		await topBar.toggleView(FormsView.View)
		await submitView.checkOption('Favorite fruits', 'Apple')
		await submitView.checkOption('Favorite fruits', 'Cherry')

		page.once('dialog', (dialog) => dialog.accept())
		await page.goto(editUrl)
		await expect(
			page.getByRole('listitem', { name: /Question number \d+/ }),
		).toHaveCount(4)
		const questions = await form.getQuestions()
		const deleted = waitForApiResponse(page, 'DELETE')
		await questions[1].section
			.getByRole('button', { name: 'Delete answer' })
			.nth(2)
			.click()
		await deleted

		await topBar.toggleView(FormsView.View)
		const response = await submitView.submit()
		expect(response.status()).toBe(201)
		await expect(submitView.successMessage).toBeVisible()
	})
})

/**
 * Create a form with a linear scale "Rating", a ranking question "Order" (First, Second, Third) and a checkbox grid "Skills" (rows Beginner, Expert; column Python)
 *
 * @param request the request context of the page
 */
async function createOfferedValuesForm(request: APIRequestContext) {
	const form = await api(request, 'POST', '/forms')
	const scale = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'linearscale',
		text: 'Rating',
	})
	const ranking = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'ranking',
		text: 'Order',
	})
	const options = await api(
		request,
		'POST',
		`/forms/${form.id}/questions/${ranking.id}/options`,
		{ optionTexts: ['First', 'Second', 'Third'] },
	)
	const grid = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'grid',
		subtype: 'checkbox',
		text: 'Skills',
	})
	const rows = await api(
		request,
		'POST',
		`/forms/${form.id}/questions/${grid.id}/options`,
		{ optionTexts: ['Beginner', 'Expert'], optionType: 'row' },
	)
	const columns = await api(
		request,
		'POST',
		`/forms/${form.id}/questions/${grid.id}/options`,
		{ optionTexts: ['Python'], optionType: 'column' },
	)
	return { form, scale, ranking, options, grid, rows, columns }
}

test('A restored draft leaves out values the questions no longer offer', async ({
	page,
	submitView,
}) => {
	const { form, scale, ranking, options, grid, rows, columns } =
		await createOfferedValuesForm(page.request)
	await page.goto(`apps/forms/${form.hash}/submit`)
	await page.evaluate(
		([key, draft]) => localStorage.setItem(key, draft),
		[
			`nextcloud_forms_${form.hash}`,
			JSON.stringify({
				[scale.id]: { value: ['8'], type: 'QuestionLinearScale' },
				[ranking.id]: {
					value: options.map((option: { id: number }) =>
						String(option.id),
					),
					type: 'QuestionRanking',
				},
				[grid.id]: {
					value: {
						[rows[0].id]: [columns[0].id],
						[rows[1].id]: [columns[0].id],
					},
					type: 'QuestionGrid',
				},
			}),
		],
	)
	await api(
		page.request,
		'DELETE',
		`/forms/${form.id}/questions/${ranking.id}/options/${options[2].id}`,
	)
	await api(
		page.request,
		'DELETE',
		`/forms/${form.id}/questions/${grid.id}/options/${rows[1].id}`,
	)
	await page.reload()

	const response = await submitView.submit()
	expect(response.status()).toBe(201)
	const { answers } = response.request().postDataJSON()
	expect(answers[scale.id] ?? []).toEqual([])
	expect(answers[ranking.id].map(String)).toEqual([
		String(options[0].id),
		String(options[1].id),
	])
	expect(answers[grid.id]).toEqual({ [rows[0].id]: [columns[0].id] })
})

test('An edited submission leaves out values the questions no longer offer', async ({
	page,
	submitView,
}) => {
	const { form, scale, ranking, options } = await createOfferedValuesForm(
		page.request,
	)
	await api(page.request, 'PATCH', `/forms/${form.id}/questions/${scale.id}`, {
		keyValuePairs: { extraSettings: { optionsHighest: 10 } },
	})
	await api(page.request, 'POST', `/forms/${form.id}/submissions`, {
		answers: {
			[scale.id]: ['8'],
			[ranking.id]: options.map((option: { id: number }) => String(option.id)),
		},
	})
	const { submissions } = await api(
		page.request,
		'GET',
		`/forms/${form.id}/submissions`,
	)
	await api(page.request, 'PATCH', `/forms/${form.id}/questions/${scale.id}`, {
		keyValuePairs: { extraSettings: { optionsHighest: 5 } },
	})
	await api(
		page.request,
		'DELETE',
		`/forms/${form.id}/questions/${ranking.id}/options/${options[2].id}`,
	)
	await page.goto(`apps/forms/${form.hash}/submit/${submissions[0].id}`)
	await expect(submitView.getQuestion('Order')).toBeVisible()

	const response = await submitView.submit()
	expect(response.status()).toBe(200)
	const { answers } = response.request().postDataJSON()
	expect(answers[scale.id] ?? []).toEqual([])
	expect(answers[ranking.id].map(String)).toEqual([
		String(options[0].id),
		String(options[1].id),
	])
})
