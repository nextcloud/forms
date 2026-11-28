/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import {
	getQuestionIdsInSections,
	isEmptySection,
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

	test('A jump lists a later section by its own number', async ({ page }) => {
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
			'Question 2: Which pet?',
			'Question 3: Comments',
			'End of the form',
		])
	})

	test('A respondent sees the section and submits the questions around it', async ({
		page,
		submitView,
	}) => {
		const { form, pet, options, section, which, comments } =
			await createSectionForm(page.request)
		await api(page.request, 'PATCH', `/forms/${form.id}/questions/${pet.id}`, {
			keyValuePairs: {
				extraSettings: {
					goTo: [{ optionId: options[1].id, target: comments.id }],
				},
			},
		})
		await page.goto(`apps/forms/${form.hash}/submit`)

		await submitView.checkRadio('Do you have a pet?', 'Yes')
		await expect(submitView.getQuestion('About the pet')).toBeVisible()
		await expect(
			page.getByRole('listitem', { name: 'Question number 2' }),
		).toContainText('Which pet?')
		await submitView.fillText('Which pet?', 'Rex')
		await submitView.checkRadio('Do you have a pet?', 'No')
		await expect(submitView.getQuestion('About the pet')).toBeHidden()
		await submitView.fillText('Comments', 'None')

		const response = await submitView.submit()
		const { answers } = response.request().postDataJSON()
		expect(answers).not.toHaveProperty(String(section.id))
		expect(answers).not.toHaveProperty(String(which.id))
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
		await expect(questions.getByRole('heading', { level: 3 })).toHaveText([
			'Intro',
			'Part one',
			'Second',
			'Third',
		])
		await expect(questions.getByRole('heading', { level: 4 })).toHaveText([
			'First',
		])
		await expect(questions).toContainText('Only a note')
		await expect(questions.locator(':scope > ul > li')).toHaveCount(6)
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
		await page.getByRole('menuitem', { name: 'Delete question' }).click()

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
})
