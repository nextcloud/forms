/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page, Response } from '@playwright/test'
import type { QuestionType } from './QuestionType'
import { QuestionSection } from './QuestionSection'

export class FormSection {
	public readonly mainContent: Locator
	public readonly titleField: Locator
	public readonly descriptionField: Locator
	public readonly newQuestionButton: Locator

	// eslint-disable-next-line no-useless-constructor
	constructor(public readonly page: Page) {
		this.mainContent = this.page.getByRole('main')
		this.newQuestionButton = this.page.getByRole('button', {
			name: 'Add a question',
		})
		this.titleField = this.mainContent.getByRole('textbox', {
			name: 'Form title',
		})
		this.descriptionField = this.mainContent.getByRole('textbox', {
			name: 'Description',
		})
	}

	public async fillTitle(text: string): Promise<void> {
		const update = this.getFormUpdatedPromise()
		await this.titleField.fill(text)
		await update
	}

	public async fillDescription(text: string): Promise<void> {
		const update = this.getFormUpdatedPromise()
		await this.descriptionField.fill(text)
		await update
	}

	public async addQuestion(type: QuestionType): Promise<void> {
		await this.newQuestionButton.click()
		await this.page.getByRole('menuitem', { name: type }).click()
	}

	public async getQuestions(): Promise<QuestionSection[]> {
		return this.page
			.locator('main section')
			.all()
			.then((sections) =>
				sections.map((section) => new QuestionSection(this.page, section)),
			)
	}

	private getFormUpdatedPromise(): Promise<Response> {
		return this.page.waitForResponse(
			(response) =>
				response.request().method() === 'PATCH'
				&& response
					.request()
					.url()
					.includes('/ocs/v2.php/apps/forms/api/v3/forms/'),
		)
	}
}
