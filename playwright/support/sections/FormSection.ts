/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'
import type { QuestionType } from './QuestionType.ts'

import { waitForApiResponse } from '../helpers.ts'
import { QuestionSection } from './QuestionSection.ts'

export class FormSection {
	public readonly mainContent: Locator
	public readonly titleField: Locator
	public readonly descriptionField: Locator
	public readonly newQuestionButton: Locator

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
		const created = waitForApiResponse(this.page, 'POST')
		await this.newQuestionButton.click()
		await this.page.getByRole('menuitem', { name: type }).click()
		await created
	}

	public async getQuestions(): Promise<QuestionSection[]> {
		return this.page
			.getByRole('listitem', { name: /Question number \d+/i })
			.all()
			.then((items) =>
				items.map((item) => new QuestionSection(this.page, item)),
			)
	}

	private getFormUpdatedPromise() {
		return waitForApiResponse(this.page, 'PATCH')
	}
}
