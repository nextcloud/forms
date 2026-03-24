/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page, Response } from '@playwright/test'

export class QuestionSection {
	public readonly titleInput: Locator
	public readonly descriptionInput: Locator
	public readonly newAnswerInput: Locator
	public readonly answerInputs: Locator

	// eslint-disable-next-line no-useless-constructor
	constructor(
		public readonly page: Page,
		public readonly section: Locator,
	) {
		this.titleInput = this.section.getByRole('textbox', {
			name: /title of/i,
		})
		this.descriptionInput = this.section.getByPlaceholder(
			'Description (formatting using Markdown is supported)',
		)
		this.newAnswerInput = this.section.getByRole('textbox', {
			name: 'Add a new answer option',
		})
		this.answerInputs = this.section.getByRole('textbox', {
			name: /The text of option \d+/i,
		})
	}

	async fillTitle(title: string): Promise<void> {
		const saved = this.getQuestionUpdatedPromise()
		await this.titleInput.fill(title)
		await saved
	}

	async fillDescription(description: string): Promise<void> {
		const saved = this.getQuestionUpdatedPromise()
		await this.descriptionInput.fill(description)
		await saved
	}

	async addAnswer(text: string): Promise<void> {
		const saved = this.page.waitForResponse(
			(response) =>
				response.request().method() === 'POST'
				&& response.request().url().includes('/api/v3/forms/'),
		)
		await this.newAnswerInput.fill(text)
		await this.newAnswerInput.press('Enter')
		await saved
	}

	private getQuestionUpdatedPromise(): Promise<Response> {
		return this.page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response.request().url().includes('/api/v3/forms/'),
		)
	}
}
