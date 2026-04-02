/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { waitForApiResponse } from '../helpers.ts'

export class QuestionSection {
	public readonly titleInput: Locator
	public readonly descriptionInput: Locator
	public readonly newAnswerInput: Locator
	public readonly answerInputs: Locator

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
		const saved = waitForApiResponse(this.page, 'PATCH')
		await this.titleInput.fill(title)
		await saved
	}

	async fillDescription(description: string): Promise<void> {
		const saved = waitForApiResponse(this.page, 'PATCH')
		await this.descriptionInput.fill(description)
		await saved
	}

	async addAnswer(text: string): Promise<void> {
		const saved = waitForApiResponse(this.page, 'POST')
		await this.newAnswerInput.fill(text)
		await this.newAnswerInput.press('Enter')
		await saved
	}

	async openActionsMenu(): Promise<void> {
		await this.section
			.getByRole('button', { name: 'Actions', exact: true })
			.click()
	}

	async delete(): Promise<void> {
		await this.openActionsMenu()
		const deleted = waitForApiResponse(this.page, 'DELETE')
		await this.page.getByRole('button', { name: 'Delete question' }).click()
		await deleted
	}

	async clone(): Promise<void> {
		await this.openActionsMenu()
		const cloned = waitForApiResponse(this.page, 'POST')
		await this.page.getByRole('button', { name: 'Copy question' }).click()
		await cloned
	}

	async toggleRequired(): Promise<void> {
		await this.openActionsMenu()
		// Wait for the debounced PATCH so it doesn't get caught
		// by a later waitForResponse from fillTitle or similar.
		const saved = waitForApiResponse(this.page, 'PATCH')
		await this.page
			.getByRole('checkbox', { name: 'Required' })
			.click({ force: true })
		await saved
		await this.page.keyboard.press('Escape')
	}
}
