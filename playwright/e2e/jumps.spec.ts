/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as submitTest } from '../support/fixtures/submit.ts'
import { api } from '../support/helpers.ts'

const test = mergeTests(randomUserTest, submitTest)

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

/**
 * Submit "No" and the comment "Nice" to the pet form with the extra settings of "Do you have a pet?", then rename the option "No" to "Nope"
 *
 * @param request the request context of the page
 * @param extraSettings builds the extra settings of "Do you have a pet?" from its options and the question "Comments"
 */
async function createEditedSubmission(
	request: APIRequestContext,
	extraSettings: (
		options: { id: number }[],
		comments: { id: number },
	) => Record<string, unknown>,
) {
	const created = await createPetForm(request)
	const { form, pet, options, comments } = created
	await api(request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { allowEditSubmissions: true },
	})
	await api(request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
		keyValuePairs: { extraSettings: extraSettings(options, comments) },
	})
	await api(request, 'POST', `/forms/${form.id}/submissions`, {
		answers: {
			[pet.id]: [String(options[1].id)],
			[comments.id]: ['Nice'],
		},
	})
	const { submissions } = await api(
		request,
		'GET',
		`/forms/${form.id}/submissions`,
	)
	await api(
		request,
		'PATCH',
		`/forms/${form.id}/questions/${pet.id}/options/${options[1].id}`,
		{ keyValuePairs: { text: 'Nope' } },
	)
	return { ...created, submissionId: submissions[0].id }
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

		await expect(
			question.getByText('Go to', { exact: true }).first(),
		).toBeVisible()
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

	test('Moving a question back after a second move keeps the jumps the first move kept', async ({
		page,
	}) => {
		const { form, pet, options, comments } = await createPetForm(page.request)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: comments.id }],
				},
			},
		})
		let releaseFirstOrder = (): void => {}
		const firstOrderReleased = new Promise<void>((resolve) => {
			releaseFirstOrder = resolve
		})
		await page.route('**/api/v3/forms/*/questions', async (route) => {
			if (route.request().method() === 'PATCH') {
				await firstOrderReleased
			}
			await route.continue()
		})
		await page.goto(`apps/forms/${form.hash}/edit`)

		await page
			.getByRole('listitem', { name: 'Question number 3' })
			.getByRole('button', { name: 'Move question up' })
			.focus()
		await page.keyboard.press('Enter')
		const moveUp = page
			.getByRole('listitem', { name: 'Question number 2' })
			.getByRole('button', { name: 'Move question up' })
		await expect(moveUp).toBeFocused()
		await page.keyboard.press('Enter')
		const dialog = page.getByRole('dialog', { name: 'Jumps cannot go back' })
		await expect(dialog).toBeVisible()
		const firstOrder = page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.url().endsWith(`/forms/${form.id}/questions`),
		)
		releaseFirstOrder()
		await firstOrder
		await dialog.getByRole('button', { name: 'Move back' }).click()

		await expect(
			page
				.getByRole('listitem', { name: 'Question number 1' })
				.locator('.jump-target-select')
				.filter({
					has: page.getByRole('combobox', { name: 'After "No" go to' }),
				})
				.locator('.jump-target-select__label'),
		).toHaveText('Question 2: Comments')
		const stored = await api(
			page.request,
			'GET',
			`/forms/${form.id}/questions/${pet.id}`,
		)
		expect(stored.extraSettings.goTo).toEqual([
			{ optionId: options[1].id, target: comments.id },
		])
	})

	test('Deleting an option and moving a question wait until a jump of the question is saved', async ({
		page,
	}) => {
		const { form, pet, options, comments } = await createPetForm(page.request)
		const requests: string[] = []
		let releaseJump = (): void => {}
		const jumpReleased = new Promise<void>((resolve) => {
			releaseJump = resolve
		})
		await page.route(
			`**/api/v3/forms/${form.id}/questions/${pet.id}`,
			async (route) => {
				if (route.request().method() === 'PATCH') {
					await jumpReleased
					requests.push('jump')
				}
				await route.continue()
			},
		)
		await page.route(
			`**/api/v3/forms/${form.id}/questions/${pet.id}/options/*`,
			async (route) => {
				if (route.request().method() === 'DELETE') {
					requests.push('delete')
				}
				await route.continue()
			},
		)
		await page.route(`**/api/v3/forms/${form.id}/questions`, async (route) => {
			if (route.request().method() === 'PATCH') {
				requests.push('order')
			}
			await route.continue()
		})
		await page.goto(`apps/forms/${form.hash}/edit`)

		const question = page.getByRole('listitem', { name: 'Question number 1' })
		await question.getByRole('button', { name: 'Actions', exact: true }).click()
		await page
			.getByRole('checkbox', { name: 'Go to based on answer' })
			.click({ force: true })
		await page.keyboard.press('Escape')
		await question.getByRole('combobox', { name: 'After "Yes" go to' }).click()
		await page
			.getByRole('option')
			.filter({ hasText: 'Question 3: Comments' })
			.click()
		await question.getByRole('button', { name: 'Delete answer' }).nth(1).click()
		await page
			.getByRole('listitem', { name: 'Question number 3' })
			.getByRole('button', { name: 'Move question up' })
			.focus()
		await page.keyboard.press('Enter')
		await expect(
			page.getByRole('textbox', { name: 'Title of question number 2' }),
		).toHaveValue('Comments')
		await page.waitForTimeout(1000)
		expect(requests).toEqual([])

		const order = page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.url().endsWith(`/forms/${form.id}/questions`),
		)
		releaseJump()
		await order
		expect(requests).toEqual(['jump', 'delete', 'order'])
		const stored = await api(
			page.request,
			'GET',
			`/forms/${form.id}/questions/${pet.id}`,
		)
		expect(stored.extraSettings.goTo).toEqual([
			{ optionId: options[0].id, target: comments.id },
		])
		expect(stored.options.map(({ id }) => id)).toEqual([options[0].id])
	})

	test('Two moves while a save waits remove the backward jumps in the editor as on the server', async ({
		page,
	}) => {
		const { form, pet, options, which, comments } = await createPetForm(
			page.request,
		)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [
						{ optionId: options[0].id, target: comments.id },
						{ optionId: options[1].id, target: which.id },
					],
				},
			},
		})
		let releaseTitle = (): void => {}
		const titleReleased = new Promise<void>((resolve) => {
			releaseTitle = resolve
		})
		await page.route(
			`**/api/v3/forms/${form.id}/questions/${which.id}`,
			async (route) => {
				if (route.request().method() === 'PATCH') {
					await titleReleased
				}
				await route.continue()
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const titleSaved = page.waitForRequest(
			(request) =>
				request.method() === 'PATCH'
				&& request.url().endsWith(`/questions/${which.id}`),
		)
		await page
			.getByRole('textbox', { name: 'Title of question number 2' })
			.fill('Which pet exactly?')
		await titleSaved
		const dialog = page.getByRole('dialog', { name: 'Jumps cannot go back' })
		for (const number of [1, 2]) {
			await page
				.getByRole('listitem', { name: `Question number ${number}` })
				.getByRole('button', { name: 'Move question down' })
				.focus()
			await page.keyboard.press('Enter')
			await dialog.getByRole('button', { name: 'Remove jumps' }).click()
			await expect(dialog).toBeHidden()
		}
		releaseTitle()
		await expect(async () => {
			const stored = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions/${pet.id}`,
			)
			expect(stored.order).toBe(3)
			expect(stored.extraSettings.goTo).toEqual([])
		}).toPass()

		const shuffleSaved = page.waitForRequest(
			(request) =>
				request.method() === 'PATCH'
				&& request.url().endsWith(`/questions/${pet.id}`),
		)
		await page
			.getByRole('listitem', { name: 'Question number 3' })
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page
			.getByRole('checkbox', { name: 'Shuffle options' })
			.click({ force: true })
		const body = (await shuffleSaved).postDataJSON()
		expect(body.keyValuePairs.extraSettings.goTo ?? []).toEqual([])
	})

	test('Deleting a question while a move waits keeps the order of the editor', async ({
		page,
	}) => {
		const { form, which } = await createPetForm(page.request)
		let releaseTitle = (): void => {}
		const titleReleased = new Promise<void>((resolve) => {
			releaseTitle = resolve
		})
		await page.route(
			`**/api/v3/forms/${form.id}/questions/${which.id}`,
			async (route) => {
				if (route.request().method() === 'PATCH') {
					await titleReleased
				}
				await route.continue()
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const titleSaved = page.waitForRequest(
			(request) =>
				request.method() === 'PATCH'
				&& request.url().endsWith(`/questions/${which.id}`),
		)
		await page
			.getByRole('textbox', { name: 'Title of question number 2' })
			.fill('Which pet exactly?')
		await titleSaved
		await page
			.getByRole('listitem', { name: 'Question number 3' })
			.getByRole('button', { name: 'Move question up' })
			.focus()
		await page.keyboard.press('Enter')
		await expect(
			page.getByRole('textbox', { name: 'Title of question number 2' }),
		).toHaveValue('Comments')
		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page.getByRole('button', { name: 'Delete question' }).click()
		releaseTitle()
		await expect(
			page.getByRole('textbox', { name: /^Title of question number/ }),
		).toHaveCount(2)
		await expect(
			page.getByRole('textbox', { name: 'Title of question number 1' }),
		).toHaveValue('Comments')

		await expect(async () => {
			const questions = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions`,
			)
			expect(questions.map(({ text }) => text)).toEqual([
				'Comments',
				'Which pet exactly?',
			])
		}).toPass()
		await expect(page.getByText('Error while saving form')).toHaveCount(0)
	})

	test('Two questions deleted while a save waits are both deleted', async ({
		page,
	}) => {
		const { form, which } = await createPetForm(page.request)
		let releaseTitle = (): void => {}
		const titleReleased = new Promise<void>((resolve) => {
			releaseTitle = resolve
		})
		await page.route(
			`**/api/v3/forms/${form.id}/questions/${which.id}`,
			async (route) => {
				if (route.request().method() === 'PATCH') {
					await titleReleased
				}
				await route.continue()
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const titleSaved = page.waitForRequest(
			(request) =>
				request.method() === 'PATCH'
				&& request.url().endsWith(`/questions/${which.id}`),
		)
		await page
			.getByRole('textbox', { name: 'Title of question number 2' })
			.fill('Which pet exactly?')
		await titleSaved
		for (const number of [3, 1]) {
			await page
				.getByRole('listitem', { name: `Question number ${number}` })
				.getByRole('button', { name: 'Actions', exact: true })
				.click()
			await page.getByRole('button', { name: 'Delete question' }).click()
			await expect(
				page.getByRole('button', { name: 'Delete question' }),
			).toHaveCount(0)
		}
		releaseTitle()

		await expect(
			page.getByRole('textbox', { name: /^Title of question number/ }),
		).toHaveCount(1)
		await expect(async () => {
			const questions = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions`,
			)
			expect(questions.map(({ text }) => text)).toEqual(['Which pet exactly?'])
		}).toPass()
	})

	test('Questions cannot be moved while a deleted question is not gone yet', async ({
		page,
	}) => {
		const { form, which } = await createPetForm(page.request)
		let releaseTitle = (): void => {}
		const titleReleased = new Promise<void>((resolve) => {
			releaseTitle = resolve
		})
		await page.route(
			`**/api/v3/forms/${form.id}/questions/${which.id}`,
			async (route) => {
				if (route.request().method() === 'PATCH') {
					await titleReleased
				}
				await route.continue()
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const titleSaved = page.waitForRequest(
			(request) =>
				request.method() === 'PATCH'
				&& request.url().endsWith(`/questions/${which.id}`),
		)
		await page
			.getByRole('textbox', { name: 'Title of question number 2' })
			.fill('Which pet exactly?')
		await titleSaved
		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page.getByRole('button', { name: 'Delete question' }).click()
		await expect(
			page
				.getByRole('listitem', { name: 'Question number 3' })
				.getByRole('button', { name: 'Move question up' }),
		).toBeDisabled()
		releaseTitle()

		await expect(
			page.getByRole('textbox', { name: /^Title of question number/ }),
		).toHaveCount(2)
		const moveUp = page
			.getByRole('listitem', { name: 'Question number 2' })
			.getByRole('button', { name: 'Move question up' })
		await expect(moveUp).toBeEnabled()
		await moveUp.focus()
		await page.keyboard.press('Enter')
		await expect(async () => {
			const questions = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions`,
			)
			expect(questions.map(({ text }) => text)).toEqual([
				'Comments',
				'Which pet exactly?',
			])
		}).toPass()
		await expect(page.getByText('Error while saving form')).toHaveCount(0)
	})

	test('An edited submission asks again for an answer of a question with jumps that the form no longer offers', async ({
		page,
		submitView,
	}) => {
		const { form, submissionId } = await createEditedSubmission(
			page.request,
			(options, comments) => ({
				goTo: [{ optionId: options[1].id, target: comments.id }],
			}),
		)
		await page.goto(`apps/forms/${form.hash}/submit/${submissionId}`)

		const dialog = page.getByRole('dialog', { name: 'Clear form' })
		await dialog.getByRole('button', { name: 'Abort' }).click()
		await expect(dialog).toBeHidden()
		const pet = submitView.getQuestion('Do you have a pet?')
		const error = pet.getByText(
			'Your earlier answer is no longer available. Choose again.',
		)
		await expect(error).toBeVisible()
		await expect(pet.getByRole('radio').first()).toBeFocused()

		await submitView.submitButton.click()
		await expect(error).toBeVisible()
		await expect(submitView.getQuestion('Comments')).toBeHidden()

		await submitView.checkRadio('Do you have a pet?', 'Nope')
		await expect(error).toBeHidden()
		await expect(pet.getByText('You must answer this question')).toBeHidden()
		await expect(
			submitView.getQuestion('Comments').getByRole('textbox'),
		).toHaveValue('Nice')
		await submitView.submit()
		await expect(submitView.successMessage).toBeVisible()
		const { submissions } = await api(
			page.request,
			'GET',
			`/forms/${form.id}/submissions`,
		)
		expect(submissions[0].answers.map((answer) => answer.text).sort()).toEqual([
			'Nice',
			'Nope',
		])
	})

	test('An edited submission asks again for an answer "Other" of a question with jumps that leaves restored answers off the path', async ({
		page,
		submitView,
	}) => {
		const { form, submissionId } = await createEditedSubmission(
			page.request,
			(options, comments) => ({
				allowOtherAnswer: true,
				goTo: [
					{ optionId: options[1].id, target: comments.id },
					{ other: true, target: 'submit' },
				],
			}),
		)
		await page.goto(`apps/forms/${form.hash}/submit/${submissionId}`)

		const dialog = page.getByRole('dialog', { name: 'Clear form' })
		await dialog.getByRole('button', { name: 'Abort' }).click()
		const pet = submitView.getQuestion('Do you have a pet?')
		await expect(
			pet.getByText(
				'Your earlier answer is no longer available. Choose again.',
			),
		).toBeVisible()
		await expect(pet.getByRole('radio', { checked: true })).toHaveCount(0)
		await expect(pet.getByRole('radio').first()).toBeFocused()
	})

	test('Skipped questions are hidden, not required and not submitted', async ({
		page,
		submitView,
	}) => {
		const { form, pet, options, which, comments } = await createPetForm(
			page.request,
		)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: comments.id }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await submitView.fillText(/Which pet\?/, 'Rex')
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await submitView.fillText('Comments', 'None')

		const response = await submitView.submit()
		expect(response.request().postDataJSON().answers).not.toHaveProperty(
			String(which.id),
		)
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
		await expect(colourQuestion).toBeHidden()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
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

		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(submitView.getQuestion(/Which pet\?/)).toBeVisible()
		await expect(submitView.getQuestion('Comments')).toBeVisible()
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await expect(submitView.getQuestion('Comments')).toBeHidden()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(submitView.getQuestion('Comments')).toBeVisible()
	})

	test('The questions after a question with jumps per answer appear once it is answered', async ({
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

		await expect(submitView.getQuestion('Do you have a pet?')).toBeVisible()
		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await expect(submitView.getQuestion('Comments')).toBeHidden()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(submitView.getQuestion(/Which pet\?/)).toBeVisible()
		await expect(submitView.getQuestion('Comments')).toBeVisible()
		await expect(
			page.locator('[aria-live="polite"]', { hasText: '2 questions added' }),
		).toHaveCount(1)
	})

	test('An optional question with jumps on every answer can be left empty and the form submitted', async ({
		page,
		submitView,
	}) => {
		const { form, pet, options, which, comments } = await createPetForm(
			page.request,
		)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [
						{ optionId: options[0].id, target: which.id },
						{ optionId: options[1].id, target: comments.id },
					],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.getQuestion('Do you have a pet?')).toBeVisible()
		await expect(submitView.getQuestion(/Which pet\?/)).toBeHidden()
		await expect(submitView.getQuestion('Comments')).toBeHidden()
		await submitView.submitButton.click()
		await page
			.getByRole('dialog', { name: 'Confirm submit' })
			.getByRole('button', { name: 'Submit' })
			.click()
		await expect(submitView.successMessage).toBeVisible()

		const { submissions } = await api(
			page.request,
			'GET',
			`/forms/${form.id}/submissions`,
		)
		expect(submissions).toHaveLength(1)
		expect(submissions[0].answers).toEqual([])
	})

	test('The editor hints to make a question with jumps per answer required', async ({
		page,
	}) => {
		const { form, pet, options, comments } = await createPetForm(page.request)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: comments.id }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/edit`)

		const question = page.getByRole('listitem', { name: 'Question number 1' })
		const hint = question.getByText(
			'Respondents see the questions after this one only once they answer it.',
			{ exact: false },
		)
		await expect(hint).toBeVisible()
		const announcement = question.locator(
			'.question__header__warnings > [aria-live="polite"]',
		)
		await expect(announcement).toHaveText('')
		await question.getByRole('button', { name: 'Actions', exact: true }).click()
		await page.getByRole('checkbox', { name: 'Required' }).click({ force: true })
		await page.keyboard.press('Escape')
		await expect(hint).toBeHidden()
		await question.getByRole('button', { name: 'Actions', exact: true }).click()
		await page.getByRole('checkbox', { name: 'Required' }).click({ force: true })
		await page.keyboard.press('Escape')
		await expect(announcement).toHaveText(
			/^Question 1: Respondents see the questions after this one/,
		)
	})

	test('A value of a linear scale jumps past a question, set in the editor', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		await api(page.request, 'PATCH', `/forms/${form.id}`, {
			keyValuePairs: { title: 'Scale form' },
		})
		const scale = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'linearscale',
				text: 'How do you like it?',
			},
		)
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'What can we improve?',
		})
		const comments = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'short',
				text: 'Comments',
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const question = page.getByRole('listitem', { name: 'Question number 1' })
		await question.getByRole('button', { name: 'Actions', exact: true }).click()
		await page
			.getByRole('checkbox', { name: 'Go to based on answer' })
			.click({ force: true })
		await page.keyboard.press('Escape')

		await question
			.getByRole('combobox', {
				name: 'Option 5 - Strongly agree go to',
			})
			.click()
		await page
			.getByRole('option')
			.filter({ hasText: 'Question 3: Comments' })
			.click()

		await expect(async () => {
			const stored = await api(
				page.request,
				'GET',
				`/forms/${form.id}/questions/${scale.id}`,
			)
			expect(stored.extraSettings.goTo).toEqual([
				{ value: 5, target: comments.id },
			])
		}).toPass()

		await page.goto(`apps/forms/${form.hash}/submit`)
		await expect(submitView.getQuestion('What can we improve?')).toBeHidden()
		await submitView.checkRadio('How do you like it?', '5')
		await expect(submitView.getQuestion('What can we improve?')).toBeHidden()
		await submitView.checkRadio('How do you like it?', '4')
		await expect(submitView.getQuestion('What can we improve?')).toBeVisible()
	})
})
