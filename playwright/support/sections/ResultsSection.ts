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
		// PillMenu renders NcCheckboxRadioSwitch as radio buttons
		this.summaryTab = this.page.getByRole('radio', { name: 'Summary' })
		this.responsesTab = this.page.getByRole('radio', { name: 'Responses' })
		this.noResponsesMessage = this.page.getByText('No responses yet')
		// Results.vue renders: "{amount} responses" in a <p> tag
		this.responseCount = this.page.getByText(/\d+ responses?/)
	}

	public async switchToSummary(): Promise<void> {
		await this.summaryTab.check({ force: true })
	}

	public async switchToResponses(): Promise<void> {
		await this.responsesTab.check({ force: true })
	}

	/**
	 * Get the summary section for a specific question by its title.
	 * ResultsSummary components render the question text as a heading.
	 */
	public getSummaryForQuestion(name: string | RegExp): Locator {
		return this.page
			.locator('section')
			.filter({ has: this.page.getByRole('heading', { name }) })
	}
}
