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
		// NcCheckboxRadioSwitch renders a hidden <input type="radio"> with
		// v-on="{ change: onToggle }". Dispatch the change event directly.
		await this.summaryTab.dispatchEvent('change')
	}

	public async switchToResponses(): Promise<void> {
		await this.responsesTab.dispatchEvent('change')
	}

	/** Get the summary section for a specific question by its title. */
	public getSummaryForQuestion(name: string | RegExp): Locator {
		return this.page
			.getByRole('main')
			.getByRole('heading', { name })
			.locator('..')
	}
}
