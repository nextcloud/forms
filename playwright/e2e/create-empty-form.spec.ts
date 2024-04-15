/**
 * @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
import { expect } from '@playwright/test'
import { test } from '../fixtures/random-user'

test.beforeEach(async ({ page }) => {
	await page.goto('apps/forms')
	await page.waitForURL(/apps\/forms$/)
})

test('It shows the empty content', async ({ page }) => {
	await expect(page.getByText('No forms created yet')).toBeVisible()
	await expect(page.getByRole('button', { name: 'Create a form' })).toBeVisible()
})

test('Use button to create new form', async ({ page }) => {
	await page.getByRole('button', { name: 'Create a form' }).click()

	await page.waitForURL(/apps\/forms\/.+/)

	// check we are in edit mode by default and the heading is focussed
	await expect(page.locator('h2 textarea')).toBeVisible()
	await expect(page.locator('h2 textarea')).toBeFocused()
})

test('Use app navigation to create new form', async ({ page }) => {
	await page.getByRole('navigation')
		.getByRole('button', { name: 'New form' })
		.click()

	await page.waitForURL(/apps\/forms\/.+/)

	// check we are in edit mode by default and the heading is focussed
	await expect(page.locator('h2 textarea')).toBeVisible()
	await expect(page.locator('h2 textarea')).toBeFocused()
})

test('Form name updated in navigation', async ({ page }) => {
	// Create a form
	await page
		.getByRole('navigation')
		.getByRole('button', { name: 'New form' })
		.click()

	await page.waitForURL(/apps\/forms\/.+/)

	// check we are in edit mode by default and the heading is focussed
	await expect(page.locator('h2 textarea')).toBeVisible()
	await expect(page.locator('h2 textarea')).toBeFocused()

	// check the form exists in the navigation
	await page
		.getByRole('list', { name: 'Your forms' })
		.getByRole('link', { name: 'New form' })
		.isVisible()

	// Update the title
	await page.locator('h2 textarea').fill('My example form')

	// See the title is updated
	await page
		.getByRole('list', { name: 'Your forms' })
		.getByRole('link', { name: 'My example form' })
		.isVisible()
})
