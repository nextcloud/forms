/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export enum FormsView {
	View = 'View',
	Edit = 'Edit',
	Results = 'Results',
}

export class TopBarSection {
	public readonly toolbar: Locator

	// eslint-disable-next-line no-useless-constructor
	constructor(public readonly page: Page) {
		this.toolbar = this.page.getByRole('toolbar', { name: 'View mode' })
	}

	public async getActiveView(): Promise<Locator> {
		return this.toolbar.getByRole('radio', { checked: true })
	}

	public async getAllViews(): Promise<Locator> {
		return this.toolbar.getByRole('radio')
	}

	public async toggleView(view: FormsView): Promise<void> {
		const radio = this.toolbar.getByRole('radio', { name: view })
		if (await radio.isChecked()) {
			return
		}
		await radio.check({ force: true }) // force is needed as the input element is hidden behind the icon
		await this.page.waitForURL(/\/submit$/)
	}
}
