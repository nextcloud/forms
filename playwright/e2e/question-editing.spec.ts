/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as formTest } from '../support/fixtures/form'
import { QuestionType } from '../support/sections/QuestionType'

const test = mergeTests(randomUserTest, appNavigationTest, formTest)

test.describe('Question editing lifecycle', () => {
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Editing test form')
	})

	const questionTypes = [
		QuestionType.ShortAnswer,
		QuestionType.LongAnswer,
		QuestionType.Checkboxes,
		QuestionType.RadioButtons,
		QuestionType.Dropdown,
		QuestionType.Date,
		QuestionType.LinearScale,
		QuestionType.Color,
	]

	for (const type of questionTypes) {
		test(`Add a ${type} question`, async ({ form }) => {
			await form.addQuestion(type)

			const questions = await form.getQuestions()
			expect(questions).toHaveLength(1)
			await expect(questions[0].titleInput).toBeVisible()
		})
	}

	test('Edit question title and description', async ({ form }) => {
		await form.addQuestion(QuestionType.ShortAnswer)

		const questions = await form.getQuestions()
		const question = questions[0]

		await question.fillTitle('What is your name?')
		await expect(question.titleInput).toHaveValue('What is your name?')

		await question.fillDescription('Please enter your full name')
		await expect(question.descriptionInput).toHaveValue(
			'Please enter your full name',
		)
	})

	test('Add answer options to a checkbox question', async ({ form }) => {
		await form.addQuestion(QuestionType.Checkboxes)

		const questions = await form.getQuestions()
		const question = questions[0]

		await question.addAnswer('Option A')
		await question.addAnswer('Option B')
		await question.addAnswer('Option C')

		await expect(question.answerInputs).toHaveCount(3)
	})

	test('Delete a question', async ({ page, form }) => {
		await form.addQuestion(QuestionType.ShortAnswer)
		await form.addQuestion(QuestionType.LongAnswer)

		let questions = await form.getQuestions()
		expect(questions).toHaveLength(2)
		await questions[0].fillTitle('First question')
		await questions[1].fillTitle('Second question')

		// Open the actions menu on the first question and delete it.
		// NcActions renders as a button inside the question section.
		// Question.vue uses force-menu so there's always a trigger button.
		const firstSection = questions[0].section
		await firstSection.getByRole('button', { name: 'Actions' }).click()
		await page.getByRole('menuitem', { name: 'Delete question' }).click()

		// Wait for the DELETE response
		questions = await form.getQuestions()
		expect(questions).toHaveLength(1)
		await expect(questions[0].titleInput).toHaveValue('Second question')
	})

	test('Clone a question', async ({ page, form }) => {
		await form.addQuestion(QuestionType.Checkboxes)

		const questions = await form.getQuestions()
		const question = questions[0]
		await question.fillTitle('Favorite colors')
		await question.addAnswer('Red')
		await question.addAnswer('Blue')

		// Clone via the actions menu
		await question.section.getByRole('button', { name: 'Actions' }).click()
		await page.getByRole('menuitem', { name: 'Copy question' }).click()

		// Wait for the clone to appear
		const updatedQuestions = await form.getQuestions()
		expect(updatedQuestions).toHaveLength(2)

		// The clone should have the same title and options
		const clone = updatedQuestions[1]
		await expect(clone.titleInput).toHaveValue('Favorite colors')
		await expect(clone.answerInputs).toHaveCount(2)
	})
})
