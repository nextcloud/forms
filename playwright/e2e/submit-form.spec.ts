/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext, Page } from '@playwright/test'

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
		const dialog = page.getByRole('dialog', { name: 'Clear form' })
		await expect(dialog.getByRole('listitem')).toHaveText(['Favorite fruits'])
		await dialog.getByRole('button', { name: 'Abort' }).click()
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
				999999: { value: ['Friday'], type: 'QuestionShort' },
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

	const dialog = page.getByRole('dialog', { name: 'Clear form' })
	await expect(dialog).toContainText(
		'Your answers to questions that no longer exist were removed.',
	)
	await expect(dialog).toContainText(
		'Some of your answers are no longer offered and were removed from these questions:',
	)
	await expect(dialog.getByRole('listitem')).toHaveText([
		'Rating',
		'Order',
		'Skills',
	])
	await dialog.getByRole('button', { name: 'Abort' }).click()

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

test('A restored draft sends a linear scale answer as its plain number', async ({
	page,
	submitView,
}) => {
	const { form, scale } = await createOfferedValuesForm(page.request)
	await page.goto(`apps/forms/${form.hash}/submit`)
	await page.evaluate(
		([key, draft]) => localStorage.setItem(key, draft),
		[
			`nextcloud_forms_${form.hash}`,
			JSON.stringify({
				[scale.id]: { value: ['05'], type: 'QuestionLinearScale' },
			}),
		],
	)
	await page.reload()
	await expect(submitView.getQuestion('Rating')).toBeVisible()
	await expect(page.getByRole('dialog', { name: 'Clear form' })).toHaveCount(0)

	const response = await submitView.submit()
	expect(response.status()).toBe(201)
	expect(response.request().postDataJSON().answers[scale.id]).toEqual(['5'])
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
	const dialog = page.getByRole('dialog', { name: 'Clear form' })
	await expect(dialog.getByRole('listitem')).toHaveText(['Rating', 'Order'])
	await expect(
		dialog.getByText(
			'Your answers to questions that no longer exist were removed.',
		),
	).toHaveCount(0)
	await dialog.getByRole('button', { name: 'Abort' }).click()

	const response = await submitView.submit()
	expect(response.status()).toBe(200)
	const { answers } = response.request().postDataJSON()
	expect(answers[scale.id] ?? []).toEqual([])
	expect(answers[ranking.id].map(String)).toEqual([
		String(options[0].id),
		String(options[1].id),
	])
})

test('Enter moves on past a radio group and from the last answer to the submit button', async ({
	page,
	submitView,
}) => {
	const form = await api(page.request, 'POST', '/forms')
	await api(page.request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { title: 'Enter form' },
	})
	await api(page.request, 'POST', `/forms/${form.id}/questions`, {
		type: 'multiple_unique',
		text: 'Favourite color',
	}).then((question) =>
		api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${question.id}/options`,
			{
				optionTexts: ['Red', 'Blue'],
			},
		),
	)
	await api(page.request, 'POST', `/forms/${form.id}/questions`, {
		type: 'short',
		text: 'Your name',
	})
	await page.goto(`apps/forms/${form.hash}/submit`)

	await submitView.checkRadio('Favourite color', 'Red')
	await submitView
		.getQuestion('Favourite color')
		.getByRole('radio', { name: 'Red' })
		.press('Enter')
	const name = submitView.getQuestion('Your name').getByRole('textbox')
	await expect(name).toBeFocused()

	await submitView.fillText('Your name', 'Alice')
	await name.press('Enter')
	await expect(submitView.submitButton).toBeFocused()
})

test('Shift+Enter moves on and Ctrl+Enter or Cmd+Enter submits the form', async ({
	page,
	submitView,
}) => {
	const form = await api(page.request, 'POST', '/forms')
	await api(page.request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { title: 'Ctrl+Enter form' },
	})
	await api(page.request, 'POST', `/forms/${form.id}/questions`, {
		type: 'multiple_unique',
		text: 'Favourite color',
	}).then((question) =>
		api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${question.id}/options`,
			{
				optionTexts: ['Red', 'Blue'],
			},
		),
	)
	await api(page.request, 'POST', `/forms/${form.id}/questions`, {
		type: 'short',
		text: 'Your name',
	})
	await page.goto(`apps/forms/${form.hash}/submit`)

	await submitView.checkRadio('Favourite color', 'Red')
	await submitView.fillText('Your name', 'Alice')
	const name = submitView.getQuestion('Your name').getByRole('textbox')
	await name.press('Shift+Enter')
	await expect(submitView.submitButton).toBeFocused()
	await expect(submitView.successMessage).toBeHidden()
	await name.press('Control+Enter')
	await expect(submitView.successMessage).toBeVisible()

	await page.goto(`apps/forms/${form.hash}/submit`)
	await submitView.checkRadio('Favourite color', 'Blue')
	await submitView
		.getQuestion('Favourite color')
		.getByRole('radio', { name: 'Blue' })
		.press('Control+Enter')
	await expect(submitView.successMessage).toBeVisible()

	await page.goto(`apps/forms/${form.hash}/submit`)
	await submitView.fillText('Your name', 'Bob')
	await submitView
		.getQuestion('Your name')
		.getByRole('textbox')
		.press('Meta+Enter')
	await expect(submitView.successMessage).toBeVisible()
})

/**
 * Create a form with the short answers "City" and "Country" and open it for submission
 *
 * @param page the page to open the form in
 */
async function openImeForm(page: Page) {
	const form = await api(page.request, 'POST', '/forms')
	for (const text of ['City', 'Country']) {
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text,
		})
	}
	await page.goto(`apps/forms/${form.hash}/submit`)
}

test('Enter that confirms IME input neither moves on nor submits', async ({
	page,
	submitView,
}) => {
	await openImeForm(page)
	const submissions: string[] = []
	page.on('request', (request) => {
		if (request.url().includes('/submissions')) {
			submissions.push(request.method())
		}
	})

	const city = submitView.getQuestion('City').getByRole('textbox')
	await city.fill('東京')
	for (const composition of [{ isComposing: true }, { keyCode: 229 }]) {
		for (const ctrlKey of [false, true]) {
			await city.evaluate(
				(input, { composition, ctrlKey }) => {
					const event = new KeyboardEvent('keydown', {
						key: 'Enter',
						code: 'Enter',
						ctrlKey,
						isComposing: composition.isComposing ?? false,
						bubbles: true,
						cancelable: true,
					})
					if (composition.keyCode) {
						Object.defineProperty(event, 'keyCode', {
							value: composition.keyCode,
						})
					}
					input.dispatchEvent(event)
				},
				{ composition, ctrlKey },
			)
			await expect(city).toBeFocused()
		}
	}

	await city.press('Enter')
	await expect(
		submitView.getQuestion('Country').getByRole('textbox'),
	).toBeFocused()
	await expect(submitView.successMessage).toBeHidden()
	expect(submissions).toEqual([])
})

test('Enter with the IME key code 229 keeps the focus in the answer', async ({
	browserName,
	page,
	submitView,
}) => {
	test.skip(
		browserName !== 'chromium',
		'Trusted IME key events need the Chromium DevTools protocol',
	)

	await openImeForm(page)
	const city = submitView.getQuestion('City').getByRole('textbox')
	await city.fill('東京')
	const client = await page.context().newCDPSession(page)
	await client.send('Input.dispatchKeyEvent', {
		type: 'rawKeyDown',
		key: 'Enter',
		code: 'Enter',
		windowsVirtualKeyCode: 229,
	})
	await expect(city).toBeFocused()

	await city.press('Enter')
	await expect(
		submitView.getQuestion('Country').getByRole('textbox'),
	).toBeFocused()
})
