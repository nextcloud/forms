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
 * Create a form with a question of the given type with the options "Yes" and "No"
 *
 * @param request the request context of the page
 * @param type the question type
 */
async function createFormWithQuestion(request: APIRequestContext, type: string) {
	const form = await api(request, 'POST', '/forms')
	await api(request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { title: 'Conditional form' },
	})
	const question = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type,
		text: 'Do you have dietary restrictions?',
	})
	const options = await api(
		request,
		'POST',
		`/forms/${form.id}/questions/${question.id}/options`,
		{ optionTexts: ['Yes', 'No'] },
	)
	return { form, question, options }
}

test.describe('Follow-up questions', () => {
	test('Follow-up questions are added in the editor', async ({ page }) => {
		const { form, question } = await createFormWithQuestion(
			page.request,
			'multiple_unique',
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const trigger = page.getByRole('listitem', { name: 'Question number 1' })
		await trigger
			.getByRole('button', { name: 'Add follow-up questions' })
			.click()
		await trigger.getByRole('combobox').click()
		await page.getByRole('option', { name: 'Yes' }).click()

		await trigger
			.getByRole('button', { name: 'Add subquestion to branch Yes' })
			.click()
		await page.getByRole('menuitem', { name: 'Short answer' }).click()
		await expect(
			page.getByRole('listitem', { name: 'Question number 1.1' }),
		).toBeVisible()

		// The branch is stored on the question itself
		await expect(async () => {
			const stored = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions/${question.id}`,
			)
			expect(stored.type).toBe('multiple_unique')
			expect(stored.extraSettings.branches).toHaveLength(1)
			expect(stored.extraSettings.branches[0].subQuestions).toHaveLength(1)
		}).toPass()
	})

	test.describe('Submitting', () => {
		let formId: number

		// A dropdown with one follow-up question per answer, followed by a regular question
		test.beforeEach(async ({ page }) => {
			const request = page.request
			const { form, question, options } = await createFormWithQuestion(
				request,
				'dropdown',
			)
			formId = form.id
			const [yes, no] = options
			await api(
				request,
				'PATCH',
				`/forms/${formId}/questions/${question.id}`,
				{
					keyValuePairs: {
						extraSettings: {
							branches: [
								{
									id: 'branch-yes',
									conditions: [{ optionId: yes.id }],
								},
								{
									id: 'branch-no',
									conditions: [{ optionId: no.id }],
								},
							],
						},
					},
				},
			)
			for (const [branchId, text] of [
				['branch-yes', 'Which ones?'],
				['branch-no', 'Why not?'],
			]) {
				await api(request, 'POST', `/forms/${formId}/questions`, {
					type: 'short',
					text,
					parentQuestionId: question.id,
					branchId,
				})
			}
			await api(request, 'POST', `/forms/${formId}/questions`, {
				type: 'short',
				text: 'Favourite word?',
			})

			await page.goto(`apps/forms/${form.hash}/submit`)
		})

		test('Only the follow-up questions of the chosen answer are shown and stored', async ({
			page,
			submitView,
		}) => {
			await expect(page.getByText('Which ones?')).toBeHidden()

			await submitView.selectDropdown(
				'Do you have dietary restrictions?',
				'Yes',
			)
			const subQuestion = page.getByRole('listitem', {
				name: 'Question number 1.1',
			})
			await expect(subQuestion.getByRole('textbox')).toHaveAccessibleName(
				'Which ones?',
			)
			await subQuestion.getByRole('textbox').fill('nuts')

			// The regular question keeps its own number and label
			await expect(
				page
					.getByRole('listitem', { name: 'Question number 2' })
					.getByRole('textbox'),
			).toHaveAccessibleName('Favourite word?')

			// Changing the answer replaces the follow-up questions and drops the answer of the other branch
			await submitView.selectDropdown(
				'Do you have dietary restrictions?',
				'No',
			)
			await expect(page.getByText('Which ones?')).toBeHidden()
			await page
				.getByRole('listitem', { name: 'Question number 1.1' })
				.getByRole('textbox')
				.fill('no reason')

			await submitView.submit()
			await expect(submitView.successMessage).toBeVisible()

			const { submissions } = await api(
				page.request,
				'GET',
				`/forms/${formId}/submissions`,
			)
			expect(
				submissions[0].answers.map((answer) => answer.text).sort(),
			).toEqual(['No', 'no reason'])
		})

		test('Element ids stay unique when follow-up questions are shown', async ({
			page,
			submitView,
		}) => {
			await submitView.selectDropdown(
				'Do you have dietary restrictions?',
				'Yes',
			)
			await expect(page.getByText('Which ones?')).toBeVisible()

			const duplicateIds = await page.evaluate(() => {
				const counts: Record<string, number> = {}
				document.querySelectorAll('main [id]').forEach((element) => {
					counts[element.id] = (counts[element.id] ?? 0) + 1
				})
				return Object.keys(counts).filter((id) => counts[id] > 1)
			})
			expect(duplicateIds).toEqual([])
		})
	})
})
