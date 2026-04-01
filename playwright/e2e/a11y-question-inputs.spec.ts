/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as topBarTest } from '../support/fixtures/topBar'
import { QuestionType } from '../support/sections/QuestionType'
import { FormsView } from '../support/sections/TopBarSection'

const test = mergeTests(randomUserTest, appNavigationTest, formTest, topBarTest)

test.beforeEach(async ({ page }) => {
	await page.goto('apps/forms', { waitUntil: 'networkidle' })
	await page.waitForURL(/apps\/forms\/$/)
})

test.describe('Accessibility: aria attributes on question inputs', () => {
	test('Short answer with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My question')
		await questions[0].fillDescription('Some context')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const input = question.getByRole('textbox')

		await expect(input).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(input).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(page.getByRole('heading', { name: 'My question' })).toHaveId(
			'q1_title',
		)
		await expect(page.locator('#q1_desc')).toContainText('Some context')
	})

	test('Short answer without description has aria-labelledby but no aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.ShortAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My question')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const input = question.getByRole('textbox')

		await expect(input).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(input).not.toHaveAttribute('aria-describedby')
	})

	test('Checkboxes fieldset with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.Checkboxes)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My checkbox question')
		await questions[0].fillDescription('Pick one or more')
		await questions[0].addAnswer('Option 1')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const fieldset = question.getByRole('group').first()

		await expect(fieldset).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(fieldset).toHaveAttribute('aria-describedby', 'q1_desc')
	})

	test('Long answer with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.LongAnswer)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My long question')
		await questions[0].fillDescription('Please elaborate')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const textarea = question.getByRole('textbox')

		await expect(textarea).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(textarea).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(
			page.getByRole('heading', { name: 'My long question' }),
		).toHaveId('q1_title')
		await expect(page.locator('#q1_desc')).toContainText('Please elaborate')
	})

	test('Dropdown with description has aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.Dropdown)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My dropdown question')
		await questions[0].fillDescription('Choose an option')
		await questions[0].addAnswer('Option 1')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const group = question.getByRole('group').first()

		await expect(group).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(group).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(
			page.getByRole('heading', { name: 'My dropdown question' }),
		).toHaveId('q1_title')
		await expect(page.locator('#q1_desc')).toContainText('Choose an option')
	})

	test('Date question with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.Date)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My date question')
		await questions[0].fillDescription('Pick a date')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const group = question.getByRole('group').first()

		await expect(group).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(group).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(
			page.getByRole('heading', { name: 'My date question' }),
		).toHaveId('q1_title')
		await expect(page.locator('#q1_desc')).toContainText('Pick a date')
	})

	test('Linear scale question with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.LinearScale)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('Rate your experience')
		await questions[0].fillDescription('From 1 to 5')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const fieldset = question.getByRole('group').first()

		await expect(fieldset).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(fieldset).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(
			page.getByRole('heading', { name: 'Rate your experience' }),
		).toHaveId('q1_title')
		await expect(page.locator('#q1_desc')).toContainText('From 1 to 5')
	})

	test('File question with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.File)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My file question')
		await questions[0].fillDescription('Upload your file')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const group = question.getByRole('group').first()

		await expect(group).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(group).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(
			page.getByRole('heading', { name: 'My file question' }),
		).toHaveId('q1_title')
		await expect(page.locator('#q1_desc')).toContainText('Upload your file')
	})

	test('Color question with description has aria-labelledby and aria-describedby', async ({
		appNavigation,
		form,
		topBar,
		page,
	}) => {
		await appNavigation.clickNewForm()
		await form.fillTitle('Test form')

		await form.addQuestion(QuestionType.Color)
		const questions = await form.getQuestions()
		await questions[0].fillTitle('My color question')
		await questions[0].fillDescription('Pick a color')

		await topBar.toggleView(FormsView.View)

		const question = page.getByRole('listitem', { name: /Question number 1/ })
		const group = question.getByRole('group').first()

		await expect(group).toHaveAttribute('aria-labelledby', 'q1_title')
		await expect(group).toHaveAttribute('aria-describedby', 'q1_desc')

		await expect(
			page.getByRole('heading', { name: 'My color question' }),
		).toHaveId('q1_title')
		await expect(page.locator('#q1_desc')).toContainText('Pick a color')
	})
})
