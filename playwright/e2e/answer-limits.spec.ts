/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

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
		key: 'adults',
		type: 'short',
		text: 'Age',
		extraSettings: { validationType: 'number', numberMin: 18 },
		hint: 'Enter a number of at least 18',
		invalid: '17',
		error: 'Enter a number of at least 18',
		valid: '18',
	},
	{
		key: 'discount',
		type: 'short',
		text: 'Discount in percent',
		extraSettings: { validationType: 'number', numberMax: 50 },
		hint: 'Enter a number of at most 50',
		invalid: '50.5',
		error: 'Enter a number of at most 50',
		valid: '50',
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
		key: 'price',
		type: 'short',
		text: 'Price',
		extraSettings: {
			validationType: 'number',
			numberMax: 0,
			numberExclude: true,
		},
		hint: 'Enter a number greater than 0',
		invalid: '0',
		error: 'Enter a number greater than 0',
		valid: '9.99',
	},
	{
		key: 'temperature',
		type: 'short',
		text: 'Temperature',
		extraSettings: {
			validationType: 'number',
			numberMin: 100,
			numberExclude: true,
		},
		hint: 'Enter a number less than 100',
		invalid: '100',
		error: 'Enter a number less than 100',
		valid: '99.5',
	},
	{
		key: 'guests',
		type: 'short',
		text: 'Guests',
		extraSettings: {
			validationType: 'number',
			numberMin: 0,
			numberMax: 10,
			numberInteger: true,
		},
		hint: 'Enter a number from 0 to 10 · Enter a whole number',
		invalid: '2.5',
		error: 'Enter a whole number',
		valid: '2',
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
		key: 'reference',
		type: 'short',
		text: 'Reference',
		extraSettings: { textContains: 'REF-' },
		hint: 'Include “REF-”',
		invalid: 'ACME 42',
		error: 'Include “REF-”',
		valid: 'ref-42',
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
		await page
			.getByRole('checkbox', { name: 'Answer rules' })
			.dispatchEvent('click')
		await page.keyboard.press('Escape')
		const rules = page.getByRole('group', { name: 'Answer rules' })
		await rules.getByRole('button', { name: 'Add rule' }).click()
		await expect(rules).toContainText('Length')
		const updated = waitForApiResponse(page, 'PATCH')
		await rules
			.getByRole('spinbutton', { name: 'Maximum characters' })
			.fill('40')
		await updated

		const formId = page.url().match(/apps\/forms\/(\w+)/)![1]
		const forms = await api(page.request, 'GET', '/forms')
		const { id } = forms.find((f: { hash: string }) => f.hash === formId)
		const { questions } = await api(page.request, 'GET', `/forms/${id}`)
		expect(questions[0].extraSettings.lengthMax).toBe(40)
	})

	test('The owner sets each rule in the rules row of the question', async ({
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
		const saving = (action: () => Promise<unknown>) =>
			Promise.all([waitForApiResponse(page, 'PATCH'), action()])

		await page.goto(`apps/forms/${form.hash}/edit`)
		const items = page.getByRole('listitem', { name: /Question number \d+/ })
		const showRules = async (index: number) => {
			await items.nth(index).getByRole('button', { name: 'Actions' }).click()
			await page
				.getByRole('checkbox', { name: 'Answer rules' })
				.dispatchEvent('click')
			await page.keyboard.press('Escape')
			return items.nth(index).getByRole('group', { name: 'Answer rules' })
		}
		const choose = async (combobox: Locator, option: string) => {
			await combobox.click()
			await page.getByRole('option', { name: option, exact: true }).click()
		}

		const amount = await showRules(0)
		await saving(() => amount.getByRole('button', { name: 'Add rule' }).click())
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 0,
			numberMax: 100,
		})
		const condition = amount.getByRole('combobox', { name: 'Condition' })
		await saving(() => choose(condition, 'Not between'))
		await saving(() =>
			amount.getByRole('spinbutton', { name: 'Lowest number' }).fill('1'),
		)
		await saving(() =>
			amount.getByRole('spinbutton', { name: 'Highest number' }).fill('10'),
		)
		expect(await settings(0)).toMatchObject({
			numberMin: 1,
			numberMax: 10,
			numberExclude: true,
		})
		const number = amount.getByRole('spinbutton', {
			name: 'Number',
			exact: true,
		})
		await saving(() => choose(condition, 'Equal to'))
		await saving(() => number.fill('7'))
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 7,
			numberMax: 7,
		})
		await saving(() => choose(condition, 'Not equal to'))
		expect(await settings(0)).toMatchObject({ numberExclude: true })
		await saving(() => choose(condition, 'Greater than'))
		await saving(() => number.fill('0'))
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMax: 0,
			numberExclude: true,
		})
		await saving(() => choose(condition, 'Less than'))
		await saving(() => number.fill('100'))
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 100,
			numberExclude: true,
		})
		await saving(() => choose(condition, 'At least'))
		await saving(() => number.fill('18'))
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 18,
		})
		await saving(() => choose(condition, 'At most'))
		await saving(() => number.fill('50'))
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMax: 50,
		})
		await saving(() => choose(condition, 'Between'))
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 0,
			numberMax: 50,
		})
		await saving(() =>
			amount.getByRole('spinbutton', { name: 'Lowest number' }).fill('1'),
		)
		await saving(() => amount.getByRole('button', { name: 'Add rule' }).click())
		expect(await settings(0)).toEqual({
			validationType: 'number',
			numberMin: 1,
			numberMax: 50,
			numberInteger: true,
		})
		await saving(() =>
			amount.getByRole('spinbutton', { name: 'Lowest number' }).fill('1.2'),
		)
		const highest = amount.getByRole('spinbutton', { name: 'Highest number' })
		await highest.fill('1.8')
		await highest.blur()
		await expect(amount).toContainText('No whole number is in this range')
		expect(await settings(0)).toMatchObject({ numberMax: 50 })

		const company = await showRules(1)
		await company.getByRole('button', { name: 'Add rule' }).click()
		await saving(() =>
			company
				.getByRole('spinbutton', { name: 'Maximum characters' })
				.fill('12'),
		)
		await choose(company.getByRole('combobox', { name: 'Condition' }), 'Between')
		await saving(() =>
			company
				.getByRole('spinbutton', { name: 'Minimum characters' })
				.fill('3'),
		)
		await company.getByRole('button', { name: 'Add rule' }).click()
		await saving(() =>
			company.getByRole('textbox', { name: 'Required text' }).fill('GmbH'),
		)
		await company.getByRole('button', { name: 'Add rule' }).click()
		await saving(() =>
			company.getByRole('textbox', { name: 'Excluded text' }).fill('spam'),
		)
		expect(await settings(1)).toEqual({
			lengthMin: 3,
			lengthMax: 12,
			textContains: 'GmbH',
			textNotContains: 'spam',
		})
		await expect(company.getByRole('button', { name: 'Add rule' })).toBeHidden()
		const maximum = company.getByRole('spinbutton', {
			name: 'Maximum characters',
		})
		await maximum.fill('3')
		await maximum.blur()
		await expect(company).toContainText(
			'The required text is longer than the maximum length',
		)
		expect(await settings(1)).toMatchObject({ lengthMax: 12 })
		await saving(() =>
			company
				.getByRole('button', { name: 'Remove rule Must not contain' })
				.click(),
		)
		expect(await settings(1)).not.toHaveProperty('textNotContains')

		await items
			.nth(2)
			.getByRole('button', { name: 'Input types (currently: Text)' })
			.click()
		await saving(() =>
			page
				.getByRole('radio', { name: 'URL', exact: true })
				.dispatchEvent('click'),
		)
		expect(await settings(2)).toEqual({ validationType: 'url' })
	})

	test('The owner changes and removes a saved rule with the keyboard', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const created = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{ type: 'short', text: 'Company' },
		)
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${created.id}`,
			{ keyValuePairs: { extraSettings: { textContains: 'GmbH' } } },
		)

		await page.goto(`apps/forms/${form.hash}/edit`)
		const rules = page.getByRole('group', { name: 'Answer rules' })
		const kind = rules.getByRole('combobox', { name: 'Rule' })
		await kind.focus()
		const last = page.getByRole('option', { name: 'Must not contain' })
		await expect(async () => {
			await page.keyboard.press('ArrowDown')
			await expect(last).toHaveClass(/vs__dropdown-option--highlight/, {
				timeout: 200,
			})
		}).toPass()
		await Promise.all([
			waitForApiResponse(page, 'PATCH'),
			page.keyboard.press('Enter'),
		])
		await expect(rules).toContainText('Must not contain')
		await expect(kind).toBeFocused()

		await rules
			.getByRole('button', { name: 'Remove rule Must not contain' })
			.press('Enter')
		await expect(rules.getByRole('button', { name: 'Add rule' })).toBeFocused()
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
			['adults', '17.99'],
			['discount', '50.01'],
			['outside', '1'],
			['outside', '10'],
			['lucky', '7.5'],
			['unlucky', '13.0'],
			['price', '0'],
			['price', '-1'],
			['temperature', '100'],
			['temperature', '1e3'],
			['guests', '2.5'],
			['guests', '11'],
			['company', 'ACME'],
			['company', 'GmbH SPAM'],
			['reference', 'RE-42'],
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
			[short.id, { validationType: 'number', numberExclude: true }],
			[short.id, { validationType: 'number', numberInteger: false }],
			[short.id, { numberInteger: true }],
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
			[short.id, { textContains: 'GmbH', textNotContains: 'mb' }],
			[short.id, { textContains: 'GmbH', lengthMax: 3 }],
			[
				short.id,
				{
					validationType: 'number',
					numberInteger: true,
					numberMin: 1.2,
					numberMax: 1.8,
				},
			],
			[long.id, { textContains: 'Nextcloud' }],
			[long.id, { validationType: 'url' }],
		]
		for (const [id, extraSettings] of illegal) {
			const response = await patch(id, extraSettings)
			expect(response.status(), JSON.stringify(extraSettings)).toBe(400)
		}
	})
})
