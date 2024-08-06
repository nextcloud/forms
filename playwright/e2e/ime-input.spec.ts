import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user'
import { test as appNavigationTest } from '../support/fixtures/navigation'
import { test as formTest } from '../support/fixtures/form'
import { QuestionType } from '../support/sections/QuestionType'

const test = mergeTests(randomUserTest, appNavigationTest, formTest)

test.beforeEach(async ({ page }) => {
	await page.goto('apps/forms')
	await page.waitForURL(/apps\/forms$/)
})

test(
	'IME input does not trigger new option',
	{
		annotation: {
			type: 'issue',
			description: 'https://github.com/nextcloud/forms/issues/2220',
		},
	},
	async ({ browserName, appNavigation, page, form }) => {
		test.skip(
			browserName !== 'chromium',
			'IME testing is currently only implemented in Chromium API',
		)

		// Now get the developer tools API
		const client = await page.context().newCDPSession(page)
		// Create a new form
		await appNavigation.clickNewForm()
		await form.fillTitle('Example')
		// Create a new Drop down question
		await form.addQuestion(QuestionType.Dropdown)
		const question = (await form.getQuestions()).at(-1)!
		// expect there is question
		expect(question).not.toBe(undefined)
		// Add the title
		await question.fillTitle('IME input')

		// no answers yet
		await expect(question.answerInputs).toHaveCount(0)

		// Start composing a new name by focussing the input and composing
		await question.newAnswerInput.focus()
		await client.send('Input.imeSetComposition', {
			selectionStart: -1,
			selectionEnd: -1,
			text: '',
		})
		await expect(question.newAnswerInput).toHaveValue('')
		await expect(question.answerInputs).toHaveCount(0) // not committed yet

		await client.send('Input.imeSetComposition', {
			selectionStart: 0,
			selectionEnd: 1,
			text: 's',
		})
		await expect(question.newAnswerInput).toHaveValue('s')
		await expect(question.answerInputs).toHaveCount(0) // not committed yet

		await client.send('Input.imeSetComposition', {
			selectionStart: 0,
			selectionEnd: 2,
			text: 'sa',
		})
		await expect(question.newAnswerInput).toHaveValue('sa')
		await expect(question.answerInputs).toHaveCount(0) // not committed yet

		await client.send('Input.insertText', {
			text: 'さ',
		})
		// so there were 4 inputs but those should only result in one new option
		await expect(question.answerInputs).toHaveCount(1)
		await expect(question.answerInputs).toHaveValue('さ')
	},
)
