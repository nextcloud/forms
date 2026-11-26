/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { api, waitForApiResponse } from '../support/helpers.ts'
import { QuestionType } from '../support/sections/QuestionType.ts'

const test = mergeTests(randomUserTest, appNavigationTest, formTest)

const OCS_HEADERS = { 'OCS-APIRequest': 'true', Accept: 'application/json' }

/**
 * The questions of the limited form: title, type, extra settings, the hint
 * respondents see, an answer breaking the rule with its error, and a valid answer
 */
const QUESTIONS = [
	{
		key: 'nickname',
		type: 'short',
		text: 'Nickname',
		extraSettings: { lengthMin: 3, lengthMax: 12 },
		hint: 'Enter between 3 and 12 characters',
		invalid: 'ab',
		error: 'Enter between 3 and 12 characters',
		valid: 'abc',
	},
	{
		key: 'rating',
		type: 'short',
		text: 'Rating',
		extraSettings: { validationType: 'number', numberMin: 1, numberMax: 10 },
		hint: 'Enter a number from 1 to 10',
		invalid: '11',
		error: 'Enter a number from 1 to 10',
		valid: '7',
	},
	{
		key: 'outside',
		type: 'short',
		text: 'Outside the usual range',
		extraSettings: {
			validationType: 'number',
			numberMin: 1,
			numberMax: 10,
			numberExclude: true,
		},
		hint: 'Enter a number below 1 or above 10',
		invalid: '5',
		error: 'Enter a number below 1 or above 10',
		valid: '11',
	},
	{
		key: 'lucky',
		type: 'short',
		text: 'Lucky number',
		extraSettings: { validationType: 'number', numberMin: 7, numberMax: 7 },
		hint: 'Enter the number 7',
		invalid: '6',
		error: 'Enter the number 7',
		valid: '7',
	},
	{
		key: 'unlucky',
		type: 'short',
		text: 'Any number but 13',
		extraSettings: {
			validationType: 'number',
			numberMin: 13,
			numberMax: 13,
			numberExclude: true,
		},
		hint: 'Enter any number except 13',
		invalid: '13',
		error: 'Enter any number except 13',
		valid: '12',
	},
	{
		key: 'company',
		type: 'short',
		text: 'Company',
		extraSettings: { textContains: 'GmbH', textNotContains: 'spam' },
		hint: 'Include “GmbH” · Do not include “spam”',
		invalid: 'Spam GmbH',
		error: 'Do not include “spam”',
		valid: 'ACME gmbh',
	},
	{
		key: 'website',
		type: 'short',
		text: 'Website',
		extraSettings: { validationType: 'url' },
		hint: null,
		invalid: 'https:nextcloud.com',
		error: 'The input is not a valid URL',
		valid: 'https://nextcloud.com',
	},
	{
		key: 'bio',
		type: 'long',
		text: 'Bio',
		extraSettings: { lengthMin: 5, lengthMax: 40 },
		hint: 'Enter between 5 and 40 characters',
		invalid: 'Hi',
		error: 'Enter between 5 and 40 characters',
		valid: 'Hello there',
	},
] as const

/**
 * Create a form with one question per answer rule
 *
 * @param request the request context of the page
 */
async function createLimitedForm(request: APIRequestContext) {
	const form = await api(request, 'POST', '/forms')
	const ids: Record<string, number> = {}
	for (const { key, type, text, extraSettings } of QUESTIONS) {
		const created = await api(request, 'POST', `/forms/${form.id}/questions`, {
			type,
			text,
		})
		await api(request, 'PATCH', `/forms/${form.id}/questions/${created.id}`, {
			keyValuePairs: { extraSettings },
		})
		ids[key] = created.id
	}
	return { form, ids }
}

/**
 * Get the answer field of a question in the submit view
 *
 * @param page the page showing the form
 * @param text the title of the question
 */
function answerField(page: Page, text: string) {
	return page
		.getByRole('listitem')
		.filter({ has: page.getByRole('heading', { name: text, exact: true }) })
		.locator('input, textarea')
		.first()
}

/**
 * Check that a respondent sees every rule, cannot submit answers breaking
 * them and can submit answers keeping them
 *
 * @param page the page showing the form
 */
async function checkRespondent(page: Page) {
	for (const { text, hint } of QUESTIONS) {
		if (hint) {
			await expect(answerField(page, text)).toHaveAccessibleDescription(hint)
		}
	}

	const nickname = answerField(page, 'Nickname')
	await nickname.fill('abcdefghijklmnopqrstuvwxyz')
	await expect(nickname).toHaveValue('abcdefghijkl')
	await expect(page.getByText('12 / 12')).toBeVisible()
	await answerField(page, 'Bio').fill('Hello')
	await expect(page.getByText('5 / 40')).toBeVisible()

	for (const { text, valid } of QUESTIONS) {
		await answerField(page, text).fill(valid)
	}
	for (const { text, invalid, error, valid } of QUESTIONS) {
		await answerField(page, text).fill(invalid)
		await page.getByRole('button', { name: 'Submit' }).click()
		await expect(
			page
				.getByRole('listitem')
				.filter({
					has: page.getByRole('heading', { name: text, exact: true }),
				})
				.locator('.notecard--error'),
		).toHaveText(error)
		await expect(
			page.getByText('Thank you for completing the form!'),
		).toBeHidden()
		await answerField(page, text).fill(valid)
	}
	const submitted = page.waitForResponse(
		(response) =>
			response.request().method() === 'POST'
			&& response.url().includes('/submissions'),
	)
	await page.getByRole('button', { name: 'Submit' }).click()
	expect((await submitted).ok()).toBeTruthy()
	await expect(page.getByText('Thank you for completing the form!')).toBeVisible()
}

test.describe('Answer limits', () => {
	test('The owner limits the length of a long answer', async ({
		page,
		appNavigation,
		form,
	}) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Limits form')
		await form.addQuestion(QuestionType.LongAnswer)
		const [question] = await form.getQuestions()
		await question.fillTitle('Bio')

		await question.openActionsMenu()
		const saved = waitForApiResponse(page, 'PATCH')
		await page
			.getByRole('checkbox', { name: 'Limit the maximum length' })
			.click({ force: true })
		await saved
		const updated = waitForApiResponse(page, 'PATCH')
		await page.getByRole('spinbutton', { name: 'Maximum characters' }).fill('40')
		await updated

		const formId = page.url().match(/apps\/forms\/(\w+)/)![1]
		const forms = await api(page.request, 'GET', '/forms')
		const { id } = forms.find((f: { hash: string }) => f.hash === formId)
		const { questions } = await api(page.request, 'GET', `/forms/${id}`)
		expect(questions[0].extraSettings.lengthMax).toBe(40)
	})

	test('The owner sets number rules, required texts and the URL type', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const create = async (text: string, extraSettings: object) => {
			const created = await api(
				page.request,
				'POST',
				`/forms/${form.id}/questions`,
				{ type: 'short', text },
			)
			await api(
				page.request,
				'PATCH',
				`/forms/${form.id}/questions/${created.id}`,
				{ keyValuePairs: { extraSettings } },
			)
		}
		await create('Amount', { validationType: 'number' })
		await create('Company', {})
		await create('Website', {})
		const settings = async (index: number) =>
			(await api(page.request, 'GET', `/forms/${form.id}`)).questions[index]
				.extraSettings
		const saving = (action: () => Promise<void>) =>
			Promise.all([waitForApiResponse(page, 'PATCH'), action()])

		await page.goto(`apps/forms/${form.hash}/edit`)
		const items = page.getByRole('listitem', { name: /Question number \d+/ })
		const menu = (index: number, type: string) =>
			items
				.nth(index)
				.getByRole('button', {
					name: `Input types (currently: ${type})`,
				})
				.click()

		await menu(0, 'Number')
		await page.getByRole('menuitem', { name: /^Number rule/ }).click()
		await saving(() =>
			page
				.getByRole('radio', { name: 'Not between', exact: true })
				.check({ force: true }),
		)
		await saving(() =>
			page.getByRole('spinbutton', { name: 'Lowest number' }).fill('1'),
		)
		await saving(() =>
			page.getByRole('spinbutton', { name: 'Highest number' }).fill('10'),
		)
		expect(await settings(0)).toMatchObject({
			numberMin: 1,
			numberMax: 10,
			numberExclude: true,
		})
		await saving(() =>
			page
				.getByRole('radio', { name: 'Equal to', exact: true })
				.check({ force: true }),
		)
		await saving(() =>
			page.getByRole('spinbutton', { name: 'Number', exact: true }).fill('7'),
		)
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 7,
			numberMax: 7,
		})
		await saving(() =>
			page
				.getByRole('radio', { name: 'Not equal to', exact: true })
				.check({ force: true }),
		)
		expect(await settings(0)).toMatchObject({ numberExclude: true })
		await page.keyboard.press('Escape')

		await menu(1, 'Text')
		await saving(() =>
			page.getByRole('textbox', { name: 'Must contain' }).fill('GmbH'),
		)
		await saving(() =>
			page.getByRole('textbox', { name: 'Must not contain' }).fill('spam'),
		)
		expect(await settings(1)).toEqual({
			textContains: 'GmbH',
			textNotContains: 'spam',
		})
		await page.keyboard.press('Escape')

		await menu(2, 'Text')
		await saving(() =>
			page
				.getByRole('radio', { name: 'URL', exact: true })
				.check({ force: true }),
		)
		expect(await settings(2)).toEqual({ validationType: 'url' })
	})

	test('A respondent in the app sees the rules and cannot break them', async ({
		page,
	}) => {
		const { form } = await createLimitedForm(page.request)
		await page.goto(`apps/forms/${form.hash}/submit`)
		await checkRespondent(page)
	})

	for (const [name, path] of [
		['a public link', 's'],
		['an embedded form', 'embed'],
	]) {
		test(`A respondent of ${name} sees the rules and cannot break them`, async ({
			page,
			browser,
			baseURL,
		}) => {
			const { form } = await createLimitedForm(page.request)
			const share = await api(
				page.request,
				'POST',
				`/forms/${form.id}/shares`,
				{
					shareType: 3,
					permissions: ['submit', 'embed'],
				},
			)
			const anonymous = await browser.newPage({
				storageState: undefined,
				baseURL,
			})
			await anonymous.goto(`apps/forms/${path}/${share.shareWith}`)
			await checkRespondent(anonymous)
			await anonymous.close()
		})
	}

	test('The server rejects answers breaking a rule', async ({
		page,
		browser,
		baseURL,
	}) => {
		const { form, ids } = await createLimitedForm(page.request)
		const share = await api(page.request, 'POST', `/forms/${form.id}/shares`, {
			shareType: 3,
		})
		const anonymous = await browser.newPage({ storageState: undefined, baseURL })
		const submit = (answers: Record<number, string[]>) =>
			anonymous.request.post(
				`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/submissions`,
				{
					data: { answers, shareHash: share.shareWith },
					headers: OCS_HEADERS,
				},
			)
		const illegal: [string, string][] = [
			['nickname', 'ab'],
			['nickname', 'abcdefghijklm'],
			['rating', '0.99'],
			['rating', '10.01'],
			['outside', '1'],
			['outside', '10'],
			['lucky', '7.5'],
			['unlucky', '13.0'],
			['company', 'ACME'],
			['company', 'GmbH SPAM'],
			['website', 'nextcloud.com'],
			['website', 'ftp://nextcloud.com'],
			['website', 'javascript://alert(1)'],
			['bio', 'Hi'],
			['bio', 'x'.repeat(41)],
		]
		for (const [key, answer] of illegal) {
			const response = await submit({ [ids[key]]: [answer] })
			expect(response.status(), `${key}: ${answer}`).toBe(400)
		}

		const valid = Object.fromEntries(
			QUESTIONS.map(({ key, valid }) => [ids[key], [valid]]),
		)
		expect((await submit(valid)).ok()).toBeTruthy()
		await anonymous.close()
	})

	test('The server rejects rules that do not fit together', async ({ page }) => {
		const form = await api(page.request, 'POST', '/forms')
		const short = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'short',
				text: 'Short',
			},
		)
		const long = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'long',
			text: 'Long',
		})
		const patch = (id: number, extraSettings: object) =>
			page.request.patch(
				`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions/${id}`,
				{ data: { keyValuePairs: { extraSettings } }, headers: OCS_HEADERS },
			)
		const illegal: [number, object][] = [
			[
				short.id,
				{ validationType: 'number', numberMin: 1, numberExclude: true },
			],
			[
				short.id,
				{
					validationType: 'number',
					numberMin: 1,
					numberMax: 2,
					numberExclude: false,
				},
			],
			[short.id, { numberMin: 1, numberMax: 2, numberExclude: true }],
			[short.id, { validationType: 'url', textContains: 'nextcloud' }],
			[short.id, { textContains: '' }],
			[short.id, { textNotContains: 'x'.repeat(4097) }],
			[short.id, { validationType: 'url', lengthMax: 20 }],
			[short.id, { validationType: 'ftp' }],
			[long.id, { textContains: 'Nextcloud' }],
			[long.id, { validationType: 'url' }],
		]
		for (const [id, extraSettings] of illegal) {
			const response = await patch(id, extraSettings)
			expect(response.status(), JSON.stringify(extraSettings)).toBe(400)
		}
	})
})
