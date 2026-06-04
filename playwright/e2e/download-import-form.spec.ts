/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { QuestionType } from '../support/sections/QuestionType.ts'

const test = mergeTests(randomUserTest, appNavigationTest, formTest)

test.describe('Download form', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('apps/forms', { waitUntil: 'networkidle' })
		await page.waitForURL(/apps\/forms\/$/)
	})

	test('Download a form as JSON file', async ({ page, appNavigation, form }) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Download test')
		await page.getByRole('button', { name: 'Add a question' }).click()
		await page.getByRole('menuitem', { name: 'Checkboxes' }).click()
		await page
			.getByRole('textbox', { name: 'Title of question number 1' })
			.fill('A')
		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByPlaceholder('Add a new answer option')
			.fill('B')
		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByPlaceholder('Add a new answer option')
			.press('Enter')
		await page.waitForTimeout(100)

		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByPlaceholder('Add a new answer option')
			.fill('C')
		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByPlaceholder('Add a new answer option')
			.press('Enter')
		await page.waitForTimeout(100)

		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByPlaceholder('Add a new answer option')
			.fill('D')
		await page
			.getByRole('listitem', { name: 'Question number 1' })
			.getByPlaceholder('Add a new answer option')
			.press('Enter')
		await page.waitForTimeout(100)

		const downloadPromise = page.waitForEvent('download')

		// Hover over the form to make the actions button visible
		await appNavigation.getOwnForm('Download test').hover()
		await page.getByRole('button', { name: 'Form actions' }).click()

		// Click Download form in the popover menu
		await page.getByRole('menuitem', { name: 'Download form' }).click()

		const download = await downloadPromise
		expect(download.suggestedFilename()).toMatch(/^Download test.*\.json$/)

		const stream = await download.createReadStream()
		const json = await new Promise<any>((resolve, reject) => {
			let raw = ''
			stream.on('data', (chunk) => (raw += chunk))
			stream.on('end', () => resolve(JSON.parse(raw)))
			stream.on('error', reject)
		})
		expect(json.form).toBeDefined()
		expect(json.form.questions).toHaveLength(1)
		expect(json.appVersion).toBeDefined()
		// Deepen: verify the JSON content matches what we created
		expect(json.form.title).toBe('Download test')
		expect(json.form.questions[0].type).toBe('multiple')
		expect(json.form.questions[0].text).toBe('A')
		expect(json.form.questions[0].options).toHaveLength(3)
		expect(json.form.questions[0].options[0].text).toBe('B')
		expect(json.form.questions[0].options[1].text).toBe('C')
		expect(json.form.questions[0].options[2].text).toBe('D')
		// Verify stripped fields are absent
		expect(json.form.id).toBeUndefined()
		expect(json.form.hash).toBeUndefined()
		expect(json.form.ownerId).toBeUndefined()
	})

	test('Download a form with multiple question types', async ({
		page,
		appNavigation,
		form,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Multi type form')
		await form.addQuestion(QuestionType.ShortAnswer)
		const q1 = await form.getQuestions()
		await q1[0].fillTitle('Name')
		await form.addQuestion(QuestionType.Dropdown)
		const q2 = await form.getQuestions()
		await q2[1].fillTitle('Country')
		await q2[1].addAnswer('DE')
		await q2[1].addAnswer('FR')
		await q2[1].addAnswer('IT')

		await page.waitForLoadState('networkidle')
		const downloadPromise = page.waitForEvent('download')

		await appNavigation.getOwnForm('Multi type form').hover()
		await page.getByRole('button', { name: 'Form actions' }).click()
		await page.getByRole('menuitem', { name: 'Download form' }).click()

		const download = await downloadPromise
		const stream = await download.createReadStream()
		const json = await new Promise<any>((resolve, reject) => {
			let raw = ''
			stream.on('data', (chunk) => (raw += chunk))
			stream.on('end', () => resolve(JSON.parse(raw)))
			stream.on('error', reject)
		})
		expect(json.form.questions).toHaveLength(2)
		expect(json.form.questions[0].type).toBe('short')
		expect(json.form.questions[0].text).toBe('Name')
		expect(json.form.questions[1].type).toBe('dropdown')
		expect(json.form.questions[1].text).toBe('Country')
		expect(json.form.questions[1].options).toHaveLength(3)
	})
})
