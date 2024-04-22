/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export class QuestionSection {
	public readonly titleInput: Locator
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
		this.newAnswerInput = this.section.getByRole('textbox', {
			name: 'Add a new answer option',
		})
		this.answerInputs = this.section.getByRole('textbox', {
			name: /The text of option \d+/i,
		})
	}

	async fillTitle(title: string): Promise<void> {
		await this.titleInput.fill(title)
	}
}
