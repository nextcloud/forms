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
		// The radio input is visually hidden and wrapped in a clickable button-like
		// container. Click the parent container to follow the real user interaction
		// path and let Vue update the controlled state.
		await radio.locator('xpath=..').click()
		await this.page.waitForURL(viewRoutes[view])
	}
}
