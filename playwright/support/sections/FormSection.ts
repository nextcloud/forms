/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export class FormSection {
	public readonly mainContent: Locator
	public readonly titleField: Locator

	// eslint-disable-next-line no-useless-constructor
	constructor(public readonly page: Page) {
		this.mainContent = this.page.getByRole('main')
		this.titleField = this.mainContent.getByRole('textbox', {
			name: 'Form title',
		})
	}

	public async fillTitle(text: string): Promise<void> {
		await this.titleField.fill(text)
	}
}
