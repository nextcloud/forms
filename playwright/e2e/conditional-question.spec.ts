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

test.describe('Conditional questions', () => {
	let formId: number
	let hash: string

	// A dropdown conditional with one subquestion per answer, followed by a regular question
	test.beforeEach(async ({ page }) => {
		const request = page.request
		;({ id: formId, hash } = await api(request, 'POST', '/forms'))
		await api(request, 'PATCH', `/forms/${formId}`, {
			keyValuePairs: { title: 'Conditional form' },
		})

		const conditional = await api(
			request,
			'POST',
			`/forms/${formId}/questions`,
			{
				type: 'conditional',
				text: 'Do you have dietary restrictions?',
			},
		)
		const [yes, no] = await api(
			request,
			'POST',
			`/forms/${formId}/questions/${conditional.id}/options`,
			{ optionTexts: ['Yes', 'No'] },
		)
		await api(request, 'PATCH', `/forms/${formId}/questions/${conditional.id}`, {
			keyValuePairs: {
				extraSettings: {
					triggerType: 'dropdown',
					branches: [
						{ id: 'branch-yes', conditions: [{ optionId: yes.id }] },
						{ id: 'branch-no', conditions: [{ optionId: no.id }] },
					],
				},
			},
		})
		for (const [branchId, text] of [
			['branch-yes', 'Which ones?'],
			['branch-no', 'Why not?'],
		]) {
			await api(request, 'POST', `/forms/${formId}/questions`, {
				type: 'short',
				text,
				parentQuestionId: conditional.id,
				branchId,
			})
		}
		await api(request, 'POST', `/forms/${formId}/questions`, {
			type: 'short',
			text: 'Favourite word?',
		})

		await page.goto(`apps/forms/${hash}/submit`)
	})

	test('Only the subquestions of the chosen answer are shown and stored', async ({
		page,
		submitView,
	}) => {
		await expect(page.getByText('Which ones?')).toBeHidden()

		await submitView.selectDropdown('Do you have dietary restrictions?', 'Yes')
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

		// Changing the answer replaces the subquestions and drops the answer of the other branch
		await submitView.selectDropdown('Do you have dietary restrictions?', 'No')
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
		expect(submissions[0].answers.map((answer) => answer.text).sort()).toEqual([
			'No',
			'no reason',
		])
	})

	test('Element ids stay unique when subquestions are shown', async ({
		page,
		submitView,
	}) => {
		await submitView.selectDropdown('Do you have dietary restrictions?', 'Yes')
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
