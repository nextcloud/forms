/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../support/fixtures/random-user.ts'
import { api } from '../support/helpers.ts'

test('Moving a question names it when a jump would go back', async ({ page }) => {
	const form = await api(page.request, 'POST', '/forms')
	const ids: number[] = []
	for (const [type, text] of [
		['multiple_unique', 'Do you have a pet?'],
		['short', 'Which pet?'],
		['short', 'Comments'],
	]) {
		ids.push(
			(
				await api(page.request, 'POST', `/forms/${form.id}/questions`, {
					type,
					text,
				})
			).id,
		)
	}
	const options = await api(
		page.request,
		'POST',
		`/forms/${form.id}/questions/${ids[0]}/options`,
		{ optionTexts: ['Yes', 'No'] },
	)
	await api(page.request, 'PATCH', `/forms/${form.id}/questions/${ids[0]}`, {
		keyValuePairs: {
			extraSettings: { goTo: [{ optionId: options[1].id, target: ids[2] }] },
		},
	})
	await page.goto(`apps/forms/${form.hash}/edit`)

	const moveUp = async (number: number) => {
		await page
			.getByRole('listitem', { name: `Question number ${number}` })
			.getByRole('button', { name: 'Move question up' })
			.focus()
		await page.keyboard.press('Enter')
	}
	await moveUp(3)
	await expect(
		page.getByRole('textbox', { name: 'Title of question number 2' }),
	).toHaveValue('Comments')
	await moveUp(2)

	const dialog = page.getByRole('dialog', { name: 'Jumps cannot go back' })
	await expect(dialog).toContainText(
		'After this move, question 2 would jump to an earlier question. Move the question "Comments" back, or remove these jumps?',
	)
	await dialog.getByRole('button', { name: 'Move back' }).click()
	await expect(
		page.getByRole('textbox', { name: 'Title of question number 2' }),
	).toHaveValue('Comments')
})
