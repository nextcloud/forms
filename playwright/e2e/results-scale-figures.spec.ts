/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as resultsTest } from '../support/fixtures/results.ts'
import { api, waitForApiResponse } from '../support/helpers.ts'
import {
	LinearScaleSubtype,
	QuestionType,
} from '../support/sections/QuestionType.ts'

const test = mergeTests(randomUserTest, appNavigationTest, formTest, resultsTest)

/**
 * Create a form with the linear scale "Recommend" and one submission per answer
 *
 * @param request the request context of the page
 * @param subtype the sub type of the scale, or null for a plain scale
 * @param extraSettings further extra settings of the scale
 * @param answers the answers to submit, one submission each
 */
async function createScaleForm(
	request: APIRequestContext,
	subtype: string | null,
	extraSettings: Record<string, unknown>,
	answers: string[] = [],
) {
	const form = await api(request, 'POST', '/forms')
	await api(request, 'PATCH', `/forms/${form.id}`, {
		keyValuePairs: { submitMultiple: true },
	})
	const scale = await api(request, 'POST', `/forms/${form.id}/questions`, {
		type: 'linearscale',
		subtype,
		text: 'Recommend',
	})
	if (Object.keys(extraSettings).length > 0) {
		await api(request, 'PATCH', `/forms/${form.id}/questions/${scale.id}`, {
			keyValuePairs: {
				extraSettings: { ...scale.extraSettings, ...extraSettings },
			},
		})
	}
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
	const form = await createScaleForm(page.request, 'promoter', {}, [
		'10',
		'9',
		'7',
		'3',
	])
	await page.goto(`apps/forms/${form.hash}/results`)
	await page.waitForURL(/\/results\/summary$/)

	const summary = resultsView.getSummaryForQuestion('Recommend')
	await expect(
		summary.getByText(
			'Linear scale (0: Not at all likely, 10: Extremely likely)',
			{ exact: true },
		),
	).toBeVisible()
	const figure = (label: string) => summary.locator(`dt:text-is("${label}") + dd`)
	await expect(figure('Answers')).toHaveText('4')
	await expect(figure('Average')).toHaveText('7.3')
	await expect(figure('Median')).toHaveText('8')
	await expect(figure('Minimum')).toHaveText('3')
	await expect(figure('Maximum')).toHaveText('10')
	await expect(figure('Promoter score (promoters − detractors)')).toHaveText('25')
	await expect(summary.getByText('50% promoters, 25% detractors')).toBeVisible()
	await expect(summary.locator('dl + ol meter')).not.toHaveCount(0)
})

test('A plain 0 to 10 scale shows no promoter score', async ({
	page,
	resultsView,
}) => {
	const form = await createScaleForm(
		page.request,
		null,
		{ optionsLowest: 0, optionsHighest: 10 },
		['10', '3'],
	)
	await page.goto(`apps/forms/${form.hash}/results`)
	await page.waitForURL(/\/results\/summary$/)

	const summary = resultsView.getSummaryForQuestion('Recommend')
	await expect(summary.locator('dt:text-is("Average") + dd')).toHaveText('6.5')
	await expect(summary.getByText(/Promoter score/)).toHaveCount(0)
})

test('A rating shows its average rating and no scale figures', async ({
	page,
	resultsView,
}) => {
	const form = await createScaleForm(page.request, 'rating', {}, ['5', '4'])
	await page.goto(`apps/forms/${form.hash}/results`)
	await page.waitForURL(/\/results\/summary$/)

	const summary = resultsView.getSummaryForQuestion('Recommend')
	await expect(
		summary.getByText('Average rating 4.5 of 5, from 2 ratings'),
	).toBeVisible()
	await expect(summary.locator('dl')).toHaveCount(0)
	await expect(summary.getByText(/Promoter score/)).toHaveCount(0)
})

test('A promoter score question from the menu is a fixed 0 to 10 scale', async ({
	page,
	appNavigation,
	form,
}) => {
	await page.goto('apps/forms')
	await appNavigation.clickNewForm()
	await form.fillTitle('Promoter form')

	const created = waitForApiResponse(page, 'POST')
	await form.addQuestion(QuestionType.LinearScale, LinearScaleSubtype.Promoter)
	const response = await created
	expect(response.request().postDataJSON()).toMatchObject({
		type: 'linearscale',
		subtype: 'promoter',
	})
	expect((await response.json()).ocs.data.extraSettings).toEqual({
		questionType: 'promoter',
		optionsLowest: 0,
		optionsHighest: 10,
	})

	const [question] = await form.getQuestions()
	await expect(question.section.getByRole('radio')).toHaveCount(11)
	await expect(question.section.getByRole('radio').first()).toHaveAccessibleName(
		'0',
	)
	await expect(question.section.getByRole('radio').last()).toHaveAccessibleName(
		'10',
	)
	await expect(
		question.section.getByRole('textbox', { name: 'Label for lowest value' }),
	).toHaveValue('Not at all likely')
	await expect(
		question.section.getByRole('textbox', { name: 'Label for highest value' }),
	).toHaveValue('Extremely likely')

	await question.openActionsMenu()
	await expect(page.getByRole('checkbox', { name: 'Required' })).toBeVisible()
	await expect(page.getByRole('combobox', { name: 'Lowest value' })).toHaveCount(0)
	await expect(page.getByRole('combobox', { name: 'Highest value' })).toHaveCount(
		0,
	)
})

test('A numbers question from the menu has no sub type', async ({
	page,
	appNavigation,
	form,
}) => {
	await page.goto('apps/forms')
	await appNavigation.clickNewForm()
	await form.fillTitle('Numbers form')

	const created = waitForApiResponse(page, 'POST')
	await form.addQuestion(QuestionType.LinearScale, LinearScaleSubtype.Numbers)
	const response = await created
	expect(response.request().postDataJSON().subtype).toBeNull()
	expect((await response.json()).ocs.data.extraSettings).toEqual({})

	const [question] = await form.getQuestions()
	await question.openActionsMenu()
	await expect(page.getByRole('combobox', { name: 'Lowest value' })).toBeVisible()
})
