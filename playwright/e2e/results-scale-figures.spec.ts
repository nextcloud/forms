/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as resultsTest } from '../support/fixtures/results.ts'
import { api, waitForApiResponse } from '../support/helpers.ts'

const test = mergeTests(randomUserTest, formTest, resultsTest)

/**
 * Create a form with the linear scale "Recommend" and one submission per answer
 *
 * @param request the request context of the page
 * @param extraSettings the extra settings of the scale
 * @param answers the answers to submit, one submission each
 */
async function createScaleForm(
	request: APIRequestContext,
	extraSettings: Record<string, unknown>,
	answers: string[] = [],
) {
	const form = await api(request, 'POST', '/forms')
	await api(request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { submitMultiple: true },
	})
	const scale = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'linearscale',
		text: 'Recommend',
	})
	await api(request, 'PATCH', `/forms/${form.id}/questions/${scale.id}`, {
		keyValuePairs: { extraSettings },
	})
	for (const answer of answers) {
		await api(request, 'POST', `/forms/${form.id}/submissions`, {
			answers: { [scale.id]: [answer] },
		})
	}
	return form
}

test('Scale figures and the promoter score are shown above the bars', async ({
	page,
	resultsView,
}) => {
	const form = await createScaleForm(
		page.request,
		{ optionsLowest: 0, optionsHighest: 10, showPromoterScore: true },
		['10', '9', '7', '3'],
	)
	await page.goto(`apps/forms/${form.hash}/results`)
	await page.waitForURL(/\/results\/summary$/)

	const summary = resultsView.getSummaryForQuestion('Recommend')
	const figure = (label: string) => summary.locator(`dt:text-is("${label}") + dd`)
	await expect(figure('Answers')).toHaveText('4')
	await expect(figure('Average')).toHaveText('7.25')
	await expect(figure('Median')).toHaveText('8')
	await expect(figure('Minimum')).toHaveText('3')
	await expect(figure('Maximum')).toHaveText('10')
	await expect(figure('Promoter score (promoters − detractors)')).toHaveText('25')
	await expect(summary.getByText('50% promoters, 25% detractors')).toBeVisible()
	await expect(summary.locator('meter')).not.toHaveCount(0)
})

test('A 0 to 10 scale without the promoter score switched on shows no score', async ({
	page,
	resultsView,
}) => {
	const form = await createScaleForm(
		page.request,
		{ optionsLowest: 0, optionsHighest: 10 },
		['10', '3'],
	)
	await page.goto(`apps/forms/${form.hash}/results`)
	await page.waitForURL(/\/results\/summary$/)

	const summary = resultsView.getSummaryForQuestion('Recommend')
	await expect(summary.locator('dt:text-is("Average") + dd')).toHaveText('6.5')
	await expect(summary.getByText(/Promoter score/)).toHaveCount(0)
})

test('Switching the promoter score on locks the range of a 0 to 10 scale at once', async ({
	page,
	form,
}) => {
	const created = await createScaleForm(page.request, {
		optionsLowest: 0,
		optionsHighest: 10,
	})
	await page.clock.install()
	await page.goto(`apps/forms/${created.hash}/edit`)
	await expect(
		page.getByRole('listitem', { name: /Question number 1/i }),
	).toBeVisible()
	const [question] = await form.getQuestions()
	await question.openActionsMenu()
	const toggle = page.getByRole('checkbox', {
		name: 'Promoter score (0 to 10 scale)',
	})
	await expect(toggle).toBeVisible()

	await page.clock.pauseAt(Date.now() + 1000)
	await toggle.click({ force: true })
	await expect(page.getByRole('combobox', { name: 'Lowest value' })).toBeDisabled()
	await expect(
		page.getByRole('combobox', { name: 'Highest value' }),
	).toBeDisabled()

	const saved = waitForApiResponse(page, 'PATCH')
	await page.clock.runFor(400)
	const response = await saved
	expect(
		response.request().postDataJSON().keyValuePairs.extraSettings,
	).toMatchObject({
		optionsLowest: 0,
		optionsHighest: 10,
		showPromoterScore: true,
	})
})

test('The promoter score cannot be switched on for another scale', async ({
	page,
	form,
}) => {
	const created = await createScaleForm(page.request, {})
	await page.goto(`apps/forms/${created.hash}/edit`)
	await expect(
		page.getByRole('listitem', { name: /Question number 1/i }),
	).toBeVisible()
	const [question] = await form.getQuestions()
	await question.openActionsMenu()

	await expect(
		page.getByRole('checkbox', { name: 'Promoter score (0 to 10 scale)' }),
	).toBeDisabled()
})
