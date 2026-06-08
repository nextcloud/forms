/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export class ResultsSection {
	public readonly summaryTab: Locator
	public readonly responsesTab: Locator
	public readonly noResponsesMessage: Locator
	public readonly responseCount: Locator

	constructor(public readonly page: Page) {
		const main = this.page.getByRole('main')
		this.summaryTab = main.getByRole('radio', { name: 'Summary' })
		// "Responses" exists in both the TopBar (value="results") and the Results PillMenu
		// (value="responses"). Use .and() with the value attribute to disambiguate.
		this.responsesTab = main
			.getByRole('radio', { name: 'Responses' })
			.and(this.page.locator('[value="responses"]'))
		this.noResponsesMessage = this.page.getByText('No responses yet')
		this.responseCount = this.page.getByText(/\d+ responses?/)
	}

	public async switchToSummary(): Promise<void> {
		if (await this.summaryTab.isChecked()) {
			return
		}
		// NcRadioGroupButton wraps the hidden radio input in a clickable container.
		// Click the parent to trigger the same interaction path as a real user.
		await this.summaryTab.locator('xpath=..').click()
	}

	public async switchToResponses(): Promise<void> {
		if (await this.responsesTab.isChecked()) {
			return
		}
		await this.responsesTab.locator('xpath=..').click()
	}

	/**
	 * Get the summary section for a specific question by its title.
	 *
	 * @param name the title of the question
	 */
	public getSummaryForQuestion(name: string | RegExp): Locator {
		return this.page
			.getByRole('main')
			.getByRole('heading', { name })
			.locator('..')
	}
}
