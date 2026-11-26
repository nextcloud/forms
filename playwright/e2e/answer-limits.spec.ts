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
import { api, waitForApiResponse } from '../support/helpers.ts'
import { QuestionType } from '../support/sections/QuestionType.ts'

const test = mergeTests(randomUserTest, appNavigationTest, formTest, submitTest)

/**
 * Create a form with a limited short text, number and long answer question
 *
 * @param request the request context of the page
 */
async function createLimitedForm(request: APIRequestContext) {
	const form = await api(request, 'POST', '/forms')
	const question = async (type: string, text: string, extraSettings: object) => {
		const created = await api(request, 'POST', `/forms/${form.id}/questions`, {
			type,
			text,
		})
		await api(request, 'PATCH', `/forms/${form.id}/questions/${created.id}`, {
			keyValuePairs: { extraSettings },
		})
		return created.id
	}
	return {
		form,
		nickname: await question('short', 'Nickname', {
			lengthMin: 3,
			lengthMax: 12,
		}),
		rating: await question('short', 'Rating', {
			validationType: 'number',
			numberMin: 1,
			numberMax: 10,
		}),
		bio: await question('long', 'Bio', { lengthMax: 40 }),
	}
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

	test('The respondent sees the limits and cannot submit an answer outside them', async ({
		page,
		submitView,
	}) => {
		const { form } = await createLimitedForm(page.request)
		await page.goto(`apps/forms/${form.hash}/submit`)

		const nickname = submitView.getQuestion('Nickname')
		const rating = submitView.getQuestion('Rating')
		const bio = submitView.getQuestion('Bio')
		await expect(nickname.getByRole('textbox')).toHaveAccessibleDescription(
			'Enter between 3 and 12 characters',
		)
		await expect(rating.getByRole('spinbutton')).toHaveAccessibleDescription(
			'Enter a number from 1 to 10',
		)
		await expect(bio.getByRole('textbox')).toHaveAccessibleDescription(
			'Enter at most 40 characters',
		)

		await nickname.getByRole('textbox').fill('abcdefghijklmnopqrstuvwxyz')
		await expect(nickname.getByRole('textbox')).toHaveValue('abcdefghijkl')
		await expect(nickname.getByText('12 / 12')).toBeVisible()
		await bio.getByRole('textbox').fill('Hello there')
		await expect(bio.getByText('11 / 40')).toBeVisible()

		await nickname.getByRole('textbox').fill('ab')
		await rating.getByRole('spinbutton').fill('11')
		await submitView.submitButton.click()
		await expect(
			nickname
				.locator('.notecard--error')
				.getByText('Enter between 3 and 12 characters'),
		).toBeVisible()
		await expect(submitView.successMessage).not.toBeVisible()

		await nickname.getByRole('textbox').fill('abc')
		await submitView.submitButton.click()
		await expect(
			rating
				.locator('.notecard--error')
				.getByText('Enter a number from 1 to 10'),
		).toBeVisible()
		await expect(submitView.successMessage).not.toBeVisible()

		await rating.getByRole('spinbutton').fill('7')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
	})

	test('The server rejects answers outside the limits', async ({ page }) => {
		const { form, nickname, rating, bio } = await createLimitedForm(page.request)
		const submit = (answers: Record<number, string[]>) =>
			page.request.post(
				`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/submissions`,
				{
					data: { answers },
					headers: {
						'OCS-APIRequest': 'true',
						Accept: 'application/json',
					},
				},
			)

		expect((await submit({ [nickname]: ['ab'] })).status()).toBe(400)
		expect((await submit({ [rating]: ['11'] })).status()).toBe(400)
		expect((await submit({ [bio]: ['x'.repeat(41)] })).status()).toBe(400)
		expect(
			(
				await submit({
					[nickname]: ['äöü'],
					[rating]: ['2.5'],
					[bio]: ['ok'],
				})
			).ok(),
		).toBeTruthy()
	})

	test('A public link shows the limits', async ({ page, browser, baseURL }) => {
		const { form } = await createLimitedForm(page.request)
		const share = await api(page.request, 'POST', `/forms/${form.id}/shares`, {
			shareType: 3,
		})

		const anonymous = await browser.newPage({ storageState: undefined, baseURL })
		await anonymous.goto(`apps/forms/s/${share.shareWith}`)
		await expect(
			anonymous.getByRole('textbox', { name: 'Nickname' }),
		).toHaveAccessibleDescription('Enter between 3 and 12 characters')
		await anonymous.close()
	})
})
