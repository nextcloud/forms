/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'
import type { QuestionType } from './QuestionType'
import { QuestionSection } from './QuestionSection'

export class FormSection {
	public readonly mainContent: Locator
	public readonly titleField: Locator
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
	}

	public async fillTitle(text: string): Promise<void> {
		await this.titleField.fill(text)
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
}
