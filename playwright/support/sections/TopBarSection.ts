/**
 * SPDX-FileCopyrightText: 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export enum FormsView {
	View = 'View',
	Edit = 'Edit',
	// TopBar.vue labels this view "Responses" in the UI
	Results = 'Responses',
}

export class TopBarSection {
	public readonly toolbar: Locator

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
		const viewRoutes: Record<FormsView, RegExp> = {
			[FormsView.View]: /\/submit(\/|$)/,
			[FormsView.Edit]: /\/edit(\/|$)/,
			[FormsView.Results]: /\/results(\/|$)/,
		}
		const radio = this.toolbar.getByRole('radio', { name: view })
		if (await radio.isChecked()) {
			return
		}
		// NcCheckboxRadioSwitch hides the input inside a label; click the label
		// to trigger Vue's event chain rather than force-checking the hidden input
		// (which would fail because Vue resets the controlled input state before
		// Playwright can verify it)
		await radio.locator('xpath=..').click()
		await this.page.waitForURL(viewRoutes[view])
	}
}
