/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export class AppNavigationSection {
	public readonly navigationLocator: Locator
	public readonly newFormLocator: Locator
	public readonly ownFormsLocator: Locator
	public readonly sharedFormsLocator: Locator

	// eslint-disable-next-line no-useless-constructor
	constructor(public readonly page: Page) {
		this.navigationLocator = this.page.getByRole('navigation', {
			name: 'Forms navigation',
		})
		this.newFormLocator = this.navigationLocator.getByRole('button', {
			name: 'New form',
		})
		this.ownFormsLocator = this.navigationLocator
			.getByRole('list', { name: 'Your forms' })
			.getByRole('listitem')
		this.sharedFormsLocator = this.navigationLocator
			.getByRole('button', { name: 'Shared forms' })
			.getByRole('listitem')
	}

	public async clickNewForm(): Promise<void> {
		await this.newFormLocator.click()
	}

	public async openArchivedForms(): Promise<void> {
		await this.navigationLocator
			.getByRole('button', { name: 'Archived forms' })
			.click()
	}

	public getOwnForm(name: string | RegExp): Locator {
		return this.ownFormsLocator.getByRole('link', { name })
	}
}
