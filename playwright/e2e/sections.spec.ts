/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import {
	getQuestionIdsInSections,
	isEmptySection,
	reorderSections,
} from '../../src/utils/Sections.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as submitTest } from '../support/fixtures/submit.ts'
import { api } from '../support/helpers.ts'

const test = mergeTests(randomUserTest, submitTest)

/**
 * Create a form with a section without title, a radio question "Do you have a pet?" (Yes, No), a section "About the pet", a short question "Which pet?" and a short question "Comments"
 *
 * @param request the request context of the page
 */
async function createSectionForm(request: APIRequestContext) {
	const form = await api(request, 'POST', '/forms')
	await api(request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { title: 'Section form' },
	})
	await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'section',
		text: '',
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
	const section = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'section',
		text: 'About the pet',
	})
	const which = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'short',
		text: 'Which pet?',
	})
	const comments = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'short',
		text: 'Comments',
	})
	return { form, pet, options, section, which, comments }
}

test.describe('Sections', () => {
	test('Sections are numbered apart from the questions in the editor', async ({
		page,
	}) => {
		const { form } = await createSectionForm(page.request)
		await page.goto(`apps/forms/${form.hash}/edit`)

		await expect(
			page.getByRole('listitem', { name: 'Section number 2' }),
		).toBeVisible()
		await expect(
			page
				.getByRole('listitem', { name: 'Question number 2' })
				.getByRole('textbox', { name: 'Title of question number 2' }),
		).toHaveValue('Which pet?')
		await expect(
			page.getByRole('listitem', { name: 'Question number 4' }),
		).toHaveCount(0)
	})

	test('A jump lists the later sections, not the questions inside them', async ({
		page,
	}) => {
		const { form } = await createSectionForm(page.request)
		await page.goto(`apps/forms/${form.hash}/edit`)

		const question = page.getByRole('listitem', { name: 'Question number 1' })
		await question.getByRole('button', { name: 'Actions', exact: true }).click()
		await page
			.getByRole('checkbox', { name: 'Go to based on answer' })
			.click({ force: true })
		await page.keyboard.press('Escape')
		await question.getByRole('combobox', { name: 'After "No" go to' }).click()

		await expect(page.getByRole('option')).toHaveText([
			'Next question',
			'Section 2: About the pet',
			'End of the form',
		])
	})

	test('A respondent goes through the sections page by page', async ({
		page,
		submitView,
	}) => {
		const { form, pet, options, section, which, comments } =
			await createSectionForm(page.request)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: 'submit' }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.submitButton).toBeVisible()
		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(submitView.getQuestion('About the pet')).toBeHidden()
		await submitView.nextButton.click()
		await expect(submitView.getQuestion('Do you have a pet?')).toBeHidden()
		await expect(submitView.getQuestion('About the pet')).toBeVisible()
		await expect(
			page.getByRole('listitem', { name: 'Question number 2' }),
		).toContainText('Which pet?')
		await submitView.fillText('Which pet?', 'Rex')

		await submitView.backButton.click()
		await expect(
			submitView
				.getQuestion('Do you have a pet?')
				.getByRole('radio', { name: 'Yes' }),
		).toBeChecked()
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(submitView.nextButton).toHaveCount(0)

		const response = await submitView.submit()
		const { answers } = response.request().postDataJSON()
		expect(answers).not.toHaveProperty(String(section.id))
		expect(answers).not.toHaveProperty(String(which.id))
		expect(answers).not.toHaveProperty(String(comments.id))
		await expect(submitView.successMessage).toBeVisible()
	})

	test('A section without title ends the section before it and is not a heading', () => {
		const items = [
			{ id: 1, type: 'short', text: 'Intro' },
			{ id: 2, type: 'section', text: 'Part one' },
			{ id: 3, type: 'short', text: 'First' },
			{ id: 4, type: 'section', text: ' ', description: 'A note' },
			{ id: 5, type: 'short', text: 'Second' },
		]
		expect([...getQuestionIdsInSections(items)]).toEqual([3])
		expect(items.map(isEmptySection)).toEqual([
			false,
			false,
			false,
			false,
			false,
		])
		expect(isEmptySection({ type: 'section', text: '', description: '' })).toBe(
			true,
		)
	})

	test('A respondent sees no heading for a section without title', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text, description] of [
			['section', ''],
			['short', 'Intro'],
			['section', 'Part one'],
			['short', 'First'],
			['section', '', 'Only a note'],
			['short', 'Second'],
			['section', ''],
			['short', 'Third'],
		]) {
			const question = await api(
				page.request,
				'POST',
				`/forms/${form.id}/questions`,
				{ type, text },
			)
			if (description) {
				await api(
					page.request,
					'PATCH',
					`/forms/${form.id}/questions/${question.id}`,
					{ keyValuePairs: { description } },
				)
			}
		}
		await page.goto(`apps/forms/${form.hash}/submit`)

		const questions = page.locator('form')
		const level3 = questions.getByRole('heading', { level: 3 })
		const level4 = questions.getByRole('heading', { level: 4 })
		await expect(level3).toHaveText(['Intro'])
		await submitView.nextButton.click()
		await expect(level3).toHaveText(['Part one'])
		await expect(level4).toHaveText(['First'])

		await submitView.nextButton.click()
		await expect(questions).toContainText('Only a note')
		await expect(level3).toHaveText(['Second'])
		await expect(level3).toBeFocused()
		await expect(level4).toHaveCount(0)

		await submitView.nextButton.click()
		await expect(level3).toHaveText(['Third'])
		await expect(level3).toBeFocused()
		await expect(questions.locator(':scope > ul > li')).toHaveCount(1)
	})

	test('Every page starts with the form title, questions of a section are headed below it', async ({
		page,
		submitView,
	}) => {
		const { form } = await createSectionForm(page.request)
		await page.goto(`apps/forms/${form.hash}/submit`)

		const main = page.getByRole('main')
		await expect(main.getByRole('heading', { level: 2 })).toHaveText(
			'Section form',
		)
		await expect(main.getByRole('heading', { level: 3 })).toHaveText([
			'Do you have a pet?',
		])
		await expect(main.getByRole('heading', { level: 4 })).toHaveCount(0)

		await submitView.nextButton.click()
		await expect(main.getByRole('heading', { level: 2 })).toHaveText(
			'Section form',
		)
		const section = main.getByRole('heading', { level: 3 })
		await expect(section).toHaveText(['About the pet'])
		await expect(section).toBeFocused()
		await expect(main.getByRole('heading', { level: 4 })).toHaveText([
			'Which pet?',
			'Comments',
		])
	})

	test('The server removes jumps into the middle of another section', async ({
		page,
	}) => {
		const headers = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
		const { form, pet, options, which } = await createSectionForm(page.request)
		const update = await page.request.patch(
			`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions/${pet.id}`,
			{
				headers,
				data: {
					keyValuePairs: {
						extraSettings: {
							goTo: [
								{ optionId: options[0].id, target: which.id },
								{ optionId: options[1].id, target: 'submit' },
							],
						},
					},
				},
			},
		)
		expect(update.headers()['x-forms-adjusted']).toBe(
			`removed-jumps=(${pet.id})`,
		)
		const saved = await api(page.request, 'GET', `/forms/${form.id}`)
		expect(saved.questions[1].extraSettings).toEqual({
			goTo: [{ optionId: options[1].id, target: 'submit' }],
		})
	})

	test('Inserting a section removes the jumps into its middle', async ({
		page,
	}) => {
		const headers = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
		const form = await api(page.request, 'POST', '/forms')
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'section',
			text: 'Start',
		})
		const question = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'multiple_unique',
				text: 'Continue?',
			},
		)
		const options = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${question.id}/options`,
			{ optionTexts: ['Yes', 'No'] },
		)
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'Why?',
		})
		const last = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'Anything else?',
		})
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${question.id}`,
			{
				keyValuePairs: {
					extraSettings: {
						goTo: [{ optionId: options[0].id, target: last.id }],
					},
				},
			},
		)

		const insert = await page.request.post(
			`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions`,
			{ headers, data: { type: 'section', text: 'More', position: 4 } },
		)
		expect(insert.headers()['x-forms-adjusted']).toBe(
			`removed-jumps=(${question.id})`,
		)
		const saved = await api(page.request, 'GET', `/forms/${form.id}`)
		expect(saved.questions[1].extraSettings).toEqual({ goTo: [] })
	})

	test('Cloning a section into the middle of a section removes the jumps into its middle', async ({
		page,
	}) => {
		const headers = { 'OCS-APIRequest': 'true', Accept: 'application/json' }
		const form = await api(page.request, 'POST', '/forms')
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'section',
			text: 'Start',
		})
		const question = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'multiple_unique',
				text: 'Continue?',
			},
		)
		const options = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${question.id}/options`,
			{ optionTexts: ['Yes', 'No'] },
		)
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'Why?',
		})
		const last = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'Anything else?',
		})
		const section = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'section',
				text: 'More',
			},
		)
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${question.id}`,
			{
				keyValuePairs: {
					extraSettings: {
						goTo: [{ optionId: options[0].id, target: last.id }],
					},
				},
			},
		)

		const clone = await page.request.post(
			`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions`,
			{ headers, data: { fromId: section.id, position: 4 } },
		)
		expect(clone.headers()['x-forms-adjusted']).toBe(
			`removed-jumps=(${question.id})`,
		)
		const saved = await api(page.request, 'GET', `/forms/${form.id}`)
		expect(saved.questions[1].extraSettings).toEqual({ goTo: [] })
	})

	test('Moving a question asks before it turns a jump into a jump into another section', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'section',
			text: 'Part one',
		})
		const question = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'multiple_unique',
				text: 'Continue?',
			},
		)
		const options = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${question.id}/options`,
			{ optionTexts: ['Yes', 'No'] },
		)
		const target = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'short',
				text: 'Anything else?',
			},
		)
		for (const [type, text] of [
			['section', 'Part two'],
			['short', 'Last'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${question.id}`,
			{
				keyValuePairs: {
					extraSettings: {
						goTo: [{ optionId: options[0].id, target: target.id }],
					},
				},
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		await page
			.getByRole('listitem', { name: 'Question number 2' })
			.getByRole('button', { name: 'Move question down' })
			.focus()
		await page.keyboard.press('Enter')
		const dialog = page.getByRole('dialog', {
			name: 'Jumps go to the start of a section',
		})
		await expect(dialog).toContainText(
			'After this move, question 1 would jump into the middle of another section. Move the question "Anything else?" back, or remove these jumps?',
		)
		const order = page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.url().endsWith(`/forms/${form.id}/questions`),
		)
		await dialog.getByRole('button', { name: 'Remove jumps' }).click()
		await order

		const stored = await api(
			page.request,
			'GET',
			`/forms/${form.id}/questions/${question.id}`,
		)
		expect(stored.extraSettings.goTo).toEqual([])
	})

	test('After a section, respondents go to the section it names', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['section', 'Part one'],
			['short', 'First'],
			['section', 'Part two'],
			['short', 'Second'],
			['section', 'Part three'],
			['short', 'Third'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await page.goto(`apps/forms/${form.hash}/edit`)

		const select = page
			.getByRole('listitem', { name: 'Section number 1' })
			.getByRole('combobox', { name: 'After section 1 go to' })
		await select.click()
		await expect(page.getByRole('option')).toHaveText([
			'Next section',
			'Section 2: Part two',
			'Section 3: Part three',
			'End of the form',
		])
		const saved = page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.url().includes(`/forms/${form.id}/questions/`),
		)
		await page.getByRole('option', { name: 'Section 3: Part three' }).click()
		expect((await saved).ok()).toBeTruthy()
		await expect(
			page
				.getByRole('listitem', { name: 'Section number 3' })
				.getByRole('combobox', { name: 'After section 3 go to' }),
		).toBeVisible()

		await page.goto(`apps/forms/${form.hash}/submit`)
		await submitView.fillText('First', 'one')
		await submitView.nextButton.click()
		await expect(submitView.getQuestion('Part three')).toBeVisible()
		await expect(submitView.nextButton).toHaveCount(0)
		await submitView.fillText('Third', 'three')
		const response = await submitView.submit()
		expect(Object.keys(response.request().postDataJSON().answers)).toHaveLength(
			2,
		)
		await expect(submitView.successMessage).toBeVisible()
	})

	test('A section that goes to a deleted section is named as a section and shows where respondents go', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const ids: number[] = []
		for (const [type, text] of [
			['section', 'Part one'],
			['short', 'First'],
			['section', 'Part two'],
			['short', 'Second'],
			['section', 'Part three'],
		]) {
			ids.push(
				(
					await api(page.request, 'POST', `/forms/${form.id}/questions`, {
						type,
						text,
					})
				).id,
			)
		}
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${ids[0]}`, {
			keyValuePairs: { extraSettings: { next: ids[4] } },
		})
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${ids[2]}`, {
			keyValuePairs: { extraSettings: { next: 'submit' } },
		})
		await page.goto(`apps/forms/${form.hash}/edit`)

		await page
			.getByRole('listitem', { name: 'Section number 3' })
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page
			.getByRole('menuitem', { name: /^Delete (question|section)$/ })
			.click()
		await expect(
			page
				.getByRole('status')
				.getByText(
					'Section 1 went to a section that was deleted, so respondents now go to the next section instead.',
				),
		).toBeVisible()
		const label = (number: number) =>
			page
				.getByRole('listitem', { name: `Section number ${number}` })
				.locator('.jump-target-select__label')
		await expect(label(1)).toHaveText(
			'Next section (the chosen section was deleted or moved up)',
		)
		await expect(label(2)).toHaveText('End of the form')
	})

	test('Deleting a section asks whether to keep its questions', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['section', 'Part one'],
			['short', 'First'],
			['section', 'Part two'],
			['short', 'Second'],
			['short', 'Third'],
			['section', 'Empty part'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await page.goto(`apps/forms/${form.hash}/edit`)
		const deleteSection = async (number: number) => {
			await page
				.getByRole('listitem', { name: `Section number ${number}` })
				.getByRole('button', { name: 'Actions', exact: true })
				.click()
			await page.getByRole('menuitem', { name: 'Delete section' }).click()
		}

		await deleteSection(3)
		await expect(
			page.getByRole('listitem', { name: 'Section number 3' }),
		).toHaveCount(0)

		await deleteSection(2)
		const dialog = page.getByRole('dialog', { name: 'Delete section' })
		await expect(dialog).toContainText(
			'Delete only the section, so its 2 questions join the part before it',
		)
		await dialog.getByRole('button', { name: 'Keep questions' }).click()
		await expect(
			page.getByRole('listitem', { name: 'Section number 2' }),
		).toHaveCount(0)
		await expect(
			page.getByRole('listitem', { name: 'Question number 3' }),
		).toBeVisible()

		await deleteSection(1)
		await expect(dialog).toContainText(
			'Delete only the section, so its 3 questions stay in a form without sections',
		)
		await dialog.getByRole('button', { name: 'Delete questions' }).click()
		await expect(
			page.getByRole('listitem', { name: /^(Question|Section) number/ }),
		).toHaveCount(0)
		const { questions } = await api(page.request, 'GET', `/forms/${form.id}`)
		expect(questions).toEqual([])
	})

	test('Sections are reordered with their questions', () => {
		const items = [
			{ id: 1, type: 'short' },
			{ id: 2, type: 'section' },
			{ id: 3, type: 'short' },
			{ id: 4, type: 'section' },
			{ id: 5, type: 'short' },
			{ id: 6, type: 'short' },
			{ id: 7, type: 'section' },
		]
		const ids = (questions: typeof items) => questions.map(({ id }) => id)
		expect(ids(reorderSections(items, [4, 7, 2]))).toEqual([1, 4, 5, 6, 7, 2, 3])
		expect(ids(reorderSections(items, [7, 2]))).toEqual([1, 7, 2, 3, 4, 5, 6])
		expect(ids(reorderSections(items.slice(0, 1), [2]))).toEqual([1])
	})

	test('The reorder sections dialog moves sections with their questions on save, by button or by dragging', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const ids: number[] = []
		for (const [type, text] of [
			['section', 'Part one'],
			['short', 'First'],
			['section', 'Part two'],
			['short', 'Second'],
			['short', 'Third'],
		]) {
			ids.push(
				(
					await api(page.request, 'POST', `/forms/${form.id}/questions`, {
						type,
						text,
					})
				).id,
			)
		}
		await page.goto(`apps/forms/${form.hash}/edit`)

		const firstSection = page.getByRole('listitem', { name: 'Section number 1' })
		await expect(
			firstSection.getByRole('button', { name: /^Move/ }),
		).toHaveCount(0)
		await firstSection
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page.getByRole('menuitem', { name: 'Move section' }).click()

		const dialog = page.getByRole('dialog', { name: 'Reorder sections' })
		const items = dialog.getByRole('listitem')
		await expect(items).toHaveText([
			/Part one\s*Section 1 of 2/,
			/Part two\s*Section 2 of 2/,
		])
		const save = dialog.getByRole('button', { name: 'Save' })
		await expect(save).toBeDisabled()
		await expect(
			dialog.getByRole('button', { name: 'Move section Part one up' }),
		).toBeDisabled()

		const down = dialog.getByRole('button', {
			name: 'Move section Part one down',
		})
		await down.click()
		await expect(items).toHaveText([/Part two/, /Part one/])
		await expect(
			dialog.getByRole('button', { name: 'Move section Part one up' }),
		).toBeFocused()
		await dialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(dialog).toHaveCount(0)
		await expect(
			firstSection.getByRole('textbox', { name: 'Title of section number 1' }),
		).toHaveValue('Part one')

		await firstSection
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page.getByRole('menuitem', { name: 'Move section' }).click()
		await expect(items).toHaveText([/Part one/, /Part two/])
		await items
			.nth(1)
			.locator('.reorder-sections__handle')
			.dragTo(items.nth(0), { targetPosition: { x: 20, y: 5 } })
		await expect(items).toHaveText([/Part two/, /Part one/])
		const order = page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.url().endsWith(`/forms/${form.id}/questions`),
		)
		await save.click()
		await order

		const { questions } = await api(page.request, 'GET', `/forms/${form.id}`)
		expect(questions.map(({ id }: { id: number }) => id)).toEqual([
			ids[2],
			ids[3],
			ids[4],
			ids[0],
			ids[1],
		])
		await expect(
			page.getByRole('textbox', { name: 'Title of section number 1' }),
		).toHaveValue('Part two')
	})

	test('Reordering sections names the moved section when a jump would go back', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const ids: Record<string, number> = {}
		for (const [type, text] of [
			['section', 'Part one'],
			['multiple_unique', 'Skip ahead?'],
			['section', 'Part two'],
			['short', 'Second'],
		]) {
			ids[text] = (
				await api(page.request, 'POST', `/forms/${form.id}/questions`, {
					type,
					text,
				})
			).id
		}
		const options = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions/${ids['Skip ahead?']}/options`,
			{ optionTexts: ['Yes', 'No'] },
		)
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${ids['Skip ahead?']}`,
			{
				keyValuePairs: {
					extraSettings: {
						goTo: [{ optionId: options[0].id, target: ids['Part two'] }],
					},
				},
			},
		)
		await page.goto(`apps/forms/${form.hash}/edit`)

		await page
			.getByRole('listitem', { name: 'Section number 1' })
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
		await page.getByRole('menuitem', { name: 'Move section' }).click()
		const reorder = page.getByRole('dialog', { name: 'Reorder sections' })
		await reorder
			.getByRole('button', { name: 'Move section Part two up' })
			.click()
		await reorder.getByRole('button', { name: 'Save' }).click()

		const dialog = page.getByRole('dialog', { name: 'Jumps cannot go back' })
		await expect(dialog).toContainText(
			'Move the section "Part two" back, or remove these jumps?',
		)
		await dialog.getByRole('button', { name: 'Move back' }).click()
		await expect(
			page.getByRole('textbox', { name: 'Title of section number 1' }),
		).toHaveValue('Part one')
	})

	test('Each page checks its required questions, an empty section is an info page', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'section',
			text: '',
		})
		const name = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'Your name',
		})
		const info = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'section',
			text: 'Before you continue',
		})
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'section',
			text: 'Details',
		})
		const city = await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'City',
		})
		for (const question of [name, city]) {
			await api(
				page.request,
				'PATCH',
				`/forms/${form.id}/questions/${question.id}`,
				{ keyValuePairs: { isRequired: true } },
			)
		}
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${info.id}`, {
			keyValuePairs: { description: 'The next page asks for details.' },
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.backButton).toHaveCount(0)
		await submitView.nextButton.click()
		await expect(submitView.getQuestion('Your name')).toBeVisible()
		await expect(submitView.getQuestion('City')).toBeHidden()
		await submitView.fillText('Your name', 'Alex')
		await submitView.nextButton.click()

		await expect(submitView.getQuestion('Before you continue')).toBeVisible()
		await expect(page.getByText('The next page asks for details.')).toBeVisible()
		await expect(submitView.getQuestion('Your name')).toBeHidden()
		await submitView.nextButton.click()

		await expect(submitView.getQuestion('City')).toBeVisible()
		await expect(submitView.nextButton).toHaveCount(0)
		await submitView.backButton.click()
		await submitView.backButton.click()
		await expect(
			submitView.getQuestion('Your name').getByRole('textbox'),
		).toHaveValue('Alex')
		await submitView.nextButton.click()
		await submitView.nextButton.click()
		await submitView.fillText('City', 'Berlin')

		const response = await submitView.submit()
		const { answers } = response.request().postDataJSON()
		expect(answers).toEqual({
			[name.id]: ['Alex'],
			[city.id]: ['Berlin'],
		})
		await expect(submitView.successMessage).toBeVisible()
	})

	test('Respondents skip a section with no title, no description and no questions', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['section', ''],
			['short', 'Your name'],
			['section', ''],
			['section', 'Details'],
			['short', 'City'],
			['section', ''],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.getQuestion('Your name')).toBeVisible()
		await submitView.nextButton.click()
		await expect(submitView.getQuestion('Details')).toBeVisible()
		await expect(submitView.getQuestion('City')).toBeVisible()
		await expect(submitView.nextButton).toHaveCount(0)
		await expect(submitView.submitButton).toBeVisible()
		await submitView.backButton.click()
		await expect(submitView.getQuestion('Your name')).toBeVisible()
	})

	test('A last section with a title but no questions is a page with the submit button', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['section', ''],
			['short', 'Your name'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		const closing = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'section',
				text: 'Thank you',
			},
		)
		await api(
			page.request,
			'PATCH',
			`/forms/${form.id}/questions/${closing.id}`,
			{
				keyValuePairs: { description: 'We will get back to you.' },
			},
		)
		await page.goto(`apps/forms/${form.hash}/submit`)

		await expect(submitView.getQuestion('Your name')).toBeVisible()
		await expect(submitView.submitButton).toHaveCount(0)
		await submitView.nextButton.click()
		await expect(submitView.getQuestion('Thank you')).toBeVisible()
		await expect(page.getByText('We will get back to you.')).toBeVisible()
		await expect(submitView.getQuestion('Your name')).toBeHidden()
		await expect(submitView.nextButton).toHaveCount(0)
		await expect(submitView.submitButton).toBeVisible()
	})

	test('Adding a section below questions puts them into a section "Untitled section"', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['short', 'First'],
			['long', 'Second'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await page.goto(`apps/forms/${form.hash}/edit`)

		await page.getByRole('button', { name: 'Add a question' }).click()
		await page.getByRole('menuitem', { name: 'Section' }).click()

		const items = page.locator('main li.question')
		await expect(items).toHaveCount(4)
		await expect(
			page.getByRole('textbox', { name: 'Title of section number 1' }),
		).toHaveValue('Untitled section')
		await expect(
			page.getByRole('textbox', { name: 'Title of section number 2' }),
		).toHaveValue('')
		await expect(items.nth(1)).toHaveAccessibleName('Question number 1')
		await expect(items.nth(2)).toHaveAccessibleName('Question number 2')
		await expect(items.nth(3)).toHaveAccessibleName('Section number 2')

		const questions = await api(
			page.request,
			'GET',
			`/forms/${form.id}/questions`,
		)
		expect(
			questions.map(({ type, text }: { type: string; text: string }) => [
				type,
				text,
			]),
		).toEqual([
			['section', 'Untitled section'],
			['short', 'First'],
			['long', 'Second'],
			['section', ''],
		])
	})

	test('Inserting a section between questions splits them into two sections', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['short', 'First'],
			['long', 'Second'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await page.goto(`apps/forms/${form.hash}/edit`)

		await page.getByRole('listitem', { name: 'Question number 1' }).hover()
		await page
			.getByRole('button', { name: 'Insert question after question 1' })
			.click()
		await page.getByRole('menuitem', { name: 'Section' }).click()

		await expect(page.locator('main li.question')).toHaveCount(4)
		const questions = await api(
			page.request,
			'GET',
			`/forms/${form.id}/questions`,
		)
		expect(
			questions.map(({ type, text }: { type: string; text: string }) => [
				type,
				text,
			]),
		).toEqual([
			['section', 'Untitled section'],
			['short', 'First'],
			['section', ''],
			['long', 'Second'],
		])
	})

	test('The server keeps questions out of the space before the first section', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const first = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'short',
				text: 'First',
			},
		)
		const response = await page.request.post(
			`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions`,
			{
				headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
				data: { type: 'section', text: 'Below' },
			},
		)
		expect(response.status()).toBe(400)
		const section = await api(
			page.request,
			'POST',
			`/forms/${form.id}/questions`,
			{
				type: 'section',
				text: 'Top',
				position: 1,
			},
		)
		const reorder = await page.request.patch(
			`../ocs/v2.php/apps/forms/api/v3/forms/${form.id}/questions`,
			{
				headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
				data: { newOrder: [first.id, section.id] },
			},
		)
		expect(reorder.status()).toBe(400)
	})

	test('Deleting the first section moves its questions into the next section', async ({
		page,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		for (const [type, text] of [
			['section', 'Start'],
			['short', 'First'],
			['section', 'Next'],
			['long', 'Second'],
		]) {
			await api(page.request, 'POST', `/forms/${form.id}/questions`, {
				type,
				text,
			})
		}
		await page.goto(`apps/forms/${form.hash}/edit`)

		const start = page.getByRole('listitem', { name: 'Section number 1' })
		await start.getByRole('button', { name: 'Actions', exact: true }).click()
		await page.getByRole('menuitem', { name: 'Delete section' }).click()
		const dialog = page.getByRole('dialog', { name: 'Delete section' })
		await expect(dialog).toContainText(
			'Delete only the section, so its question joins the next section',
		)
		await dialog.getByRole('button', { name: 'Keep questions' }).click()

		await expect(
			page.getByRole('textbox', { name: 'Title of section number 1' }),
		).toHaveValue('Next')
		await expect(page.locator('main li.question').first()).toHaveAccessibleName(
			'Section number 1',
		)
		const questions = await api(
			page.request,
			'GET',
			`/forms/${form.id}/questions`,
		)
		expect(questions.map(({ text }: { text: string }) => text)).toEqual([
			'Next',
			'First',
			'Second',
		])
	})

	test('An empty section sends respondents on to its target without a page of its own', async ({
		page,
		submitView,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		const ids: Record<string, number> = {}
		for (const [type, text] of [
			['section', 'Start'],
			['short', 'First'],
			['section', 'Jump'],
			['section', 'Skipped'],
			['short', 'Second'],
			['section', 'End'],
			['short', 'Third'],
			['section', 'Finish'],
			['section', 'Not reached'],
			['short', 'Never'],
		]) {
			ids[text] = (
				await api(page.request, 'POST', `/forms/${form.id}/questions`, {
					type,
					text: ['Jump', 'Finish'].includes(text) ? '' : text,
				})
			).id
		}
		for (const [section, next] of [
			['Jump', ids.End],
			['End', ids.Finish],
			['Finish', 'submit'],
		] as const) {
			await api(
				page.request,
				'PATCH',
				`/forms/${form.id}/questions/${ids[section]}`,
				{ keyValuePairs: { extraSettings: { next } } },
			)
		}
		await page.goto(`apps/forms/${form.hash}/submit`)

		await submitView.fillText('First', 'one')
		await submitView.nextButton.click()
		await expect(submitView.getQuestion('End')).toBeVisible()
		await expect(submitView.getQuestion('Skipped')).toBeHidden()
		await expect(submitView.nextButton).toHaveCount(0)
		await submitView.fillText('Third', 'three')
		const response = await submitView.submit()
		expect(
			Object.keys(response.request().postDataJSON().answers)
				.map(Number)
				.sort((a, b) => a - b),
		).toEqual([ids.First, ids.Third])
		await expect(submitView.successMessage).toBeVisible()
	})
})
