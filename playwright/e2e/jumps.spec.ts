/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as submitTest } from '../support/fixtures/submit.ts'

const test = mergeTests(randomUserTest, submitTest)

/**
 * Call the Forms OCS API as the logged in user
 *
 * @param request the request context of the page
 * @param method the HTTP method
 * @param path the path below `api/v3`
 * @param data the JSON body
 */
async function api(
	request: APIRequestContext,
	method: string,
	path: string,
	data?: unknown,
) {
	const response = await request.fetch(`../ocs/v2.php/apps/forms/api/v3${path}`, {
		method,
		data,
		headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	expect(response.ok()).toBeTruthy()
	return (await response.json()).ocs.data
}

/**
 * Create a form with a radio question "Do you have a pet?" (Yes, No), a required short question "Which pet?" and a short question "Comments"
 *
 * @param request the request context of the page
 */
async function createPetForm(request: APIRequestContext) {
	const form = await api(request, 'POST', '/forms')
	await api(request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { title: 'Pet form' },
	})
	const pet = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'multiple_unique',
		text: 'Do you have a pet?',
	})
	const options = await api(
		request,
		'POST',
		`/forms/${form.id}/questions/${pet.id}/options`,
		{ optionTexts: ['Yes', 'No'] },
	)
	const which = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'short',
		text: 'Which pet?',
	})
	await api(request, 'PATCH', `/forms/${form.id}/questions/${which.id}`, {
		keyValuePairs: { isRequired: true },
	})
	const comments = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'short',
		text: 'Comments',
	})
	return { form, pet, options, which, comments }
}

test.describe('Jumps', () => {
	test('An answer jumps to a later question, set in the editor', async ({
		page,
	}) => {
		const { form, pet, options, comments } = await createPetForm(page.request)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const question = page.getByRole('listitem', { name: 'Question number 1' })
		await question.getByRole('button', { name: 'Actions', exact: true }).click()
		await page
			.getByRole('checkbox', { name: 'Go to based on answer' })
			.click({ force: true })
		await page.keyboard.press('Escape')

		await question.getByRole('combobox', { name: 'After "No" go to' }).click()
		await page
			.getByRole('option')
			.filter({ hasText: 'Question 3: Comments' })
			.click()

		await expect(async () => {
			const stored = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions/${pet.id}`,
			)
			expect(stored.extraSettings.goTo).toEqual([
				{ optionId: options[1].id, target: comments.id },
			])
		}).toPass()
	})

	test('Skipped questions are hidden, not required and not submitted', async ({
		page,
		submitView,
	}) => {
		const { form, pet, options, comments } = await createPetForm(page.request)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: comments.id }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.getQuestion(/Which pet\?/)).toBeVisible()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await submitView.fillText(/Which pet\?/, 'Rex')
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await submitView.fillText('Comments', 'None')

		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()

		const { submissions } = await api(
			page.request,
			'GET',
			`/forms/${form.id}/submissions`,
		)
		expect(submissions[0].answers.map((answer) => answer.text).sort()).toEqual([
			'No',
			'None',
		])
	})

	test('A skipped question keeps its shuffled options when it comes back', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const pet = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'multiple_unique',
			text: 'Do you have a pet?',
		})
		const options = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${pet.id}/options`,
			{ optionTexts: ['Yes', 'No'] },
		)
		const colour = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'multiple_unique',
				text: 'Favourite colour',
			},
		)
		await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${colour.id}/options`,
			{
				optionTexts: ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple'],
			},
		)
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${colour.id}`,
			{
				keyValuePairs: { extraSettings: { shuffleOptions: true } },
			},
		)
		const comments = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'short',
				text: 'Comments',
			},
		)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: comments.id }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		const colourQuestion = submitView.getQuestion('Favourite colour')
		await expect(colourQuestion.getByRole('radio')).toHaveCount(6)
		const order = await colourQuestion.locator('label').allTextContents()
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(colourQuestion).toBeHidden()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(colourQuestion.getByRole('radio')).toHaveCount(6)
		expect(await colourQuestion.locator('label').allTextContents()).toEqual(
			order,
		)
	})

	test('"End of the form" after a question hides the questions after it', async ({
		page,
		submitView,
	}) => {
		const { form, pet, options } = await createPetForm(page.request)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: 'submit' }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.getQuestion(/Which pet\?/)).toBeVisible()
		await expect(submitView.getQuestion('Comments')).toBeVisible()
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await expect(submitView.getQuestion('Comments')).toBeHidden()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(submitView.getQuestion('Comments')).toBeVisible()
	})
})
