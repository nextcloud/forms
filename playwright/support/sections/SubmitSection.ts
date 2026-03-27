/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page, Response } from '@playwright/test'

export class SubmitSection {
	public readonly submitButton: Locator
	public readonly successMessage: Locator

	constructor(public readonly page: Page) {
		this.submitButton = this.page.getByRole('button', { name: 'Submit' })
		this.successMessage = this.page.getByText(
			'Thank you for completing the form!',
		)
	}

	/**
	 * Get a question's list item by its title text.
	 * Questions render as <li aria-label="Question number N">,
	 * and each contains an <h3> with the title text.
	 */
	public getQuestion(name: string | RegExp): Locator {
		return this.page
			.getByRole('listitem')
			.filter({ has: this.page.getByRole('heading', { name }) })
	}

	/**
	 * Fill a short/long text question by its title.
	 * QuestionShort renders <input aria-labelledby="qN_title">,
	 * QuestionLong renders <textarea aria-labelledby="qN_title">.
	 * Both are matched by getByRole('textbox').
	 */
	public async fillText(questionName: string | RegExp, value: string): Promise<void> {
		const question = this.getQuestion(questionName)
		await question.getByRole('textbox').fill(value)
	}

	/**
	 * Check a checkbox option within a question.
	 * QuestionMultiple renders NcCheckboxRadioSwitch as
	 * <input type="checkbox"> with the option text as label.
	 */
	public async checkOption(
		questionName: string | RegExp,
		optionName: string | RegExp,
	): Promise<void> {
		const question = this.getQuestion(questionName)
		await question.getByRole('checkbox', { name: optionName }).check()
	}

	/**
	 * Check a radio option within a question.
	 * QuestionMultiple (unique) renders NcCheckboxRadioSwitch as
	 * <input type="radio"> with the option text as label.
	 */
	public async checkRadio(
		questionName: string | RegExp,
		optionName: string | RegExp,
	): Promise<void> {
		const question = this.getQuestion(questionName)
		await question.getByRole('radio', { name: optionName }).check()
	}

	/**
	 * Select a dropdown option.
	 * QuestionDropdown renders NcSelect which uses role="combobox".
	 */
	public async selectDropdown(
		questionName: string | RegExp,
		optionName: string | RegExp,
	): Promise<void> {
		const question = this.getQuestion(questionName)
		await question.getByRole('combobox').click()
		await this.page.getByRole('option', { name: optionName }).click()
	}

	/**
	 * Click submit and wait for the POST response.
	 * Submit.vue posts to /ocs/v2.php/apps/forms/api/v3/forms/{formId}/submissions
	 */
	public async submit(): Promise<Response> {
		const response = this.page.waitForResponse(
			(resp) =>
				resp.request().method() === 'POST'
				&& resp.request().url().includes('/api/v3/forms/')
				&& resp.request().url().includes('/submissions'),
		)
		await this.submitButton.click()
		return response
	}
}
