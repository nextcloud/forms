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
		// Scope to main content to avoid conflict with TopBar radios that have the same names.
		// The PillMenu tabs are inside NcAppContent (main), the TopBar is in the navigation header.
		const main = this.page.getByRole('main')
		this.summaryTab = main.getByRole('radio', { name: 'Summary' })
		this.responsesTab = main.getByRole('radio', { name: 'Responses' })
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
		// ResultsSummary renders as <div class="section question-summary"> with an <h3> inside.
		// We locate the heading and return its parent element.
		return this.page.getByRole('main').getByRole('heading', { name }).locator('..')
	}
}
