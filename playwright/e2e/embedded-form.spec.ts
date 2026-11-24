/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { api } from '../support/helpers.ts'

const test = mergeTests(randomUserTest)

test.describe('Embedded form', () => {
	test('An embedded form has a single main landmark', async ({
		page,
		browser,
		baseURL,
	}) => {
		const form = await api(page.request, 'POST', '/forms')
		await api(page.request, 'PATCH', `/forms/${form.id}`, {
			keyValuePairs: { title: 'Embedded form' },
		})
		await api(page.request, 'POST', `/forms/${form.id}/questions`, {
			type: 'short',
			text: 'Your name',
		})
		const share = await api(page.request, 'POST', `/forms/${form.id}/shares`, {
			shareType: 3,
			permissions: ['submit', 'embed'],
		})

		const embedded = await browser.newPage({ storageState: undefined, baseURL })
		await embedded.goto(`apps/forms/embed/${share.shareWith}`)

		await expect(
			embedded.getByRole('heading', { name: 'Your name' }),
		).toBeVisible()
		await expect(embedded.getByRole('main')).toHaveCount(1)
		await embedded.close()
	})
})
