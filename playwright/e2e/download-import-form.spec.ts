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

test.describe('Import form', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('apps/forms', { waitUntil: 'networkidle' })
		await page.waitForURL(/apps\/forms\/$/)
	})

	test('Import a form from JSON file', async ({ page, appNavigation }) => {
		// Prepare JSON data matching the export format
		const formData = {
			appVersion: '5.3.0-rc.0',
			form: {
				title: 'Imported test form',
				description: 'Description',
				questions: [
					{
						id: 52,
						order: 1,
						type: 'multiple',
						isRequired: false,
						text: 'Checkbox',
						name: '',
						description: '',
						extraSettings: [],
						options: [
							{
								order: 1,
								text: 'A',
								optionType: 'choice',
							},
							{
								order: 2,
								text: 'B',
								optionType: 'choice',
							},
						],
					},
					{
						id: 53,
						order: 2,
						type: 'short',
						isRequired: false,
						text: 'Text',
						name: '',
						description: '',
						extraSettings: [],
						options: [],
					},
				],
			},
		}

		const jsonContent = JSON.stringify(formData)

		// Set up file chooser handler before clicking import
		const fileChooserPromise = page.waitForEvent('filechooser')

		// Click the Import form button in the navigation
		await page.getByRole('button', { name: 'Import a form' }).click()

		const fileChooser = await fileChooserPromise
		await fileChooser.setFiles({
			name: 'imported-form.json',
			mimeType: 'application/json',
			buffer: Buffer.from(jsonContent),
		})

		// Wait for the imported form to appear in the navigation
		await expect(appNavigation.getOwnForm('Imported test form')).toBeVisible({
			timeout: 10000,
		})

		await expect(
			page.getByRole('textbox', { name: 'Title of question number 1' }),
		).toHaveValue('Checkbox')
		await expect(
			page.getByRole('textbox', { name: 'Description', exact: true }),
		).toHaveValue('Description')
		await expect(
			page.getByRole('textbox', { name: 'The text of option 1' }),
		).toHaveValue('A')
		await expect(
			page.getByRole('textbox', { name: 'The text of option 2' }),
		).toHaveValue('B')
		await expect(
			page.getByRole('textbox', { name: 'Title of question number 2' }),
		).toHaveValue('Text')
	})

	test('Import a form with a long text question', async ({
		page,
		appNavigation,
	}) => {
		const formData = {
			appVersion: '5.3.0-rc.0',
			form: {
				title: 'Long answer form',
				description: 'Testing long text import',
				questions: [
					{
						id: 10,
						order: 1,
						type: 'long',
						text: 'Your biography',
						isRequired: true,
						options: [],
					},
				],
			},
		}

		const jsonContent = JSON.stringify(formData)

		const fileChooserPromise = page.waitForEvent('filechooser')

		// Click the Import form button in the navigation
		await page.getByRole('button', { name: 'Import a form' }).click()

		const fileChooser = await fileChooserPromise
		await fileChooser.setFiles({
			name: 'long-text.json',
			mimeType: 'application/json',
			buffer: Buffer.from(jsonContent),
		})

		await expect(appNavigation.getOwnForm('Long answer form')).toBeVisible()
		await expect(
			page.getByRole('textbox', { name: 'Title of question number 1' }),
		).toHaveValue('Your biography')
		await expect(
			page.getByRole('textbox', { name: 'Description', exact: true }),
		).toHaveValue('Testing long text import')
	})

	test('Import a form with confirmation email question remapping', async ({
		page,
		appNavigation,
	}) => {
		const formData = {
			appVersion: '5.3.0-rc.0',
			form: {
				title: 'Email confirmation',
				description: '',
				confirmationEmailQuestionId: 99,
				questions: [
					{
						id: 99,
						order: 1,
						type: 'short',
						text: 'Your email',
						options: [],
					},
				],
			},
		}

		const jsonContent = JSON.stringify(formData)

		const fileChooserPromise = page.waitForEvent('filechooser')

		// Click the Import form button in the navigation
		await page.getByRole('button', { name: 'Import a form' }).click()

		const fileChooser = await fileChooserPromise
		await fileChooser.setFiles({
			name: 'email-confirm.json',
			mimeType: 'application/json',
			buffer: Buffer.from(jsonContent),
		})

		await expect(appNavigation.getOwnForm('Email confirmation')).toBeVisible()
		await expect(
			page.getByRole('textbox', { name: 'Title of question number 1' }),
		).toHaveValue('Your email')
	})
})
