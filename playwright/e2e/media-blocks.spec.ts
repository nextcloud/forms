/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { FormSection } from '../support/sections/FormSection.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as formTest } from '../support/fixtures/form.ts'
import { test as appNavigationTest } from '../support/fixtures/navigation.ts'
import { test as randomUserTest } from '../support/fixtures/random-user.ts'
import { test as submitTest } from '../support/fixtures/submit.ts'
import { test as topBarTest } from '../support/fixtures/topBar.ts'
import { QuestionType } from '../support/sections/QuestionType.ts'
import { FormsView } from '../support/sections/TopBarSection.ts'

const test = mergeTests(
	randomUserTest,
	appNavigationTest,
	formTest,
	topBarTest,
	submitTest,
)

const PLAYER_HOSTS = /^https:\/\/(www\.youtube-nocookie\.com|player\.vimeo\.com)\//

/**
 * Add an image or video block and fill its fields.
 *
 * @param form the form section
 * @param type the block type
 * @param title the caption of the block
 * @param fields the values of the block's fields by label
 */
async function addBlock(
	form: FormSection,
	type: QuestionType.Image | QuestionType.Video,
	title: string,
	fields: Record<string, string>,
): Promise<void> {
	await form.addQuestion(type)
	const block = (await form.getQuestions()).at(-1)!
	await block.fillTitle(title)
	for (const [label, value] of Object.entries(fields)) {
		await block.fillMediaField(label, value)
	}
}

/**
 * Answer requests to the video players with an empty page and record them.
 *
 * @param page the page to watch
 * @return the addresses of all player requests made so far
 */
async function interceptPlayers(page: Page): Promise<string[]> {
	const requests: string[] = []
	await page.route(PLAYER_HOSTS, (route) => {
		requests.push(route.request().url())
		return route.fulfill({ contentType: 'text/html', body: '<html></html>' })
	})
	return requests
}

test.describe('Image and video blocks', () => {
	test.beforeEach(async ({ page, appNavigation, form }) => {
		await page.goto('apps/forms')
		await page.waitForURL(/apps\/forms\/?$/)
		await appNavigation.clickNewForm()
		await form.fillTitle('Media blocks test form')
	})

	test('Shows an image stored on this Nextcloud', async ({
		page,
		form,
		topBar,
		submitView,
	}) => {
		const address = new URL('/core/img/favicon.png', page.url()).href
		await addBlock(form, QuestionType.Image, 'Our logo', {
			Address: address,
			'Description for screen readers': 'The logo',
		})

		const editorImage = page.getByRole('img', { name: 'The logo' })
		await expect(editorImage).toHaveAttribute('src', address)
		await expect(
			page.getByText('Only images stored on this Nextcloud'),
		).toHaveCount(0)

		await topBar.toggleView(FormsView.View)
		const image = submitView
			.getQuestion('Our logo')
			.getByRole('img', { name: 'The logo' })
		await expect(image).toBeVisible()
		await expect
			.poll(() =>
				image.evaluate((element: HTMLImageElement) => element.naturalWidth),
			)
			.toBeGreaterThan(0)
	})

	test('Warns that an image on another site cannot be shown', async ({
		page,
		form,
		topBar,
		submitView,
	}) => {
		await addBlock(form, QuestionType.Image, 'Elsewhere', {
			Address: 'https://example.org/picture.png',
			'Description for screen readers': 'A picture',
		})

		await expect(
			page.getByText('This image is on another site and will not be shown.'),
		).toBeVisible()
		await expect(page.getByRole('img', { name: 'A picture' })).toHaveCount(0)

		await topBar.toggleView(FormsView.View)
		const question = submitView.getQuestion('Elsewhere')
		await expect(question).toBeVisible()
		await expect(question.getByRole('img')).toHaveCount(0)
	})

	const players = [
		{
			address: 'https://www.youtube.com/watch?v=rauSwYA9c0Y',
			provider: 'YouTube',
			player: 'https://www.youtube-nocookie.com/embed/rauSwYA9c0Y?autoplay=1',
		},
		{
			address: 'https://youtu.be/rauSwYA9c0Y?t=10',
			provider: 'YouTube',
			player: 'https://www.youtube-nocookie.com/embed/rauSwYA9c0Y?autoplay=1',
		},
		{
			address: 'https://m.youtube.com/shorts/rauSwYA9c0Y',
			provider: 'YouTube',
			player: 'https://www.youtube-nocookie.com/embed/rauSwYA9c0Y?autoplay=1',
		},
		{
			address: 'https://vimeo.com/76979871',
			provider: 'Vimeo',
			player: 'https://player.vimeo.com/video/76979871?dnt=1&autoplay=1',
		},
	]

	for (const { address, provider, player } of players) {
		test(`Loads the player for ${address} only after consent`, async ({
			page,
			form,
			topBar,
			submitView,
		}) => {
			const requests = await interceptPlayers(page)
			await addBlock(form, QuestionType.Video, 'Tour', {
				Address: address,
				'Link text': 'Watch the tour',
			})

			await topBar.toggleView(FormsView.View)
			const question = submitView.getQuestion('Tour')
			await expect(
				question.getByText(`This video is hosted by ${provider}.`),
			).toBeVisible()
			await expect(
				question.getByRole('link', { name: 'Watch the tour' }),
			).toHaveAttribute('href', address)
			await expect(question.locator('iframe')).toHaveCount(0)
			expect(requests).toEqual([])

			await question.getByRole('button', { name: 'Play video' }).click()

			await expect(question.locator('iframe')).toHaveAttribute('src', player)
			await expect.poll(() => requests).toEqual([player])
		})
	}

	test('Shows any other video address as a link only', async ({
		form,
		topBar,
		submitView,
	}) => {
		await addBlock(form, QuestionType.Video, 'Recording', {
			Address: 'https://example.org/recording.mp4',
			'Link text': 'Download the recording',
		})

		await topBar.toggleView(FormsView.View)
		const question = submitView.getQuestion('Recording')
		await expect(
			question.getByRole('link', { name: 'Download the recording' }),
		).toHaveAttribute('href', 'https://example.org/recording.mp4')
		await expect(
			question.getByRole('button', { name: 'Play video' }),
		).toHaveCount(0)
		await expect(question.locator('iframe')).toHaveCount(0)
	})

	test('Submits a form that contains image and video blocks', async ({
		page,
		form,
		topBar,
		submitView,
	}) => {
		await interceptPlayers(page)
		await form.addQuestion(QuestionType.ShortAnswer)
		const [name] = await form.getQuestions()
		await name.fillTitle('Your name')
		await name.toggleRequired()
		await addBlock(form, QuestionType.Image, 'Our logo', {
			Address: new URL('/core/img/favicon.png', page.url()).href,
		})
		await addBlock(form, QuestionType.Video, 'Tour', {
			Address: 'https://www.youtube.com/watch?v=rauSwYA9c0Y',
		})

		await topBar.toggleView(FormsView.View)
		await submitView.fillText('Your name', 'Alice')
		await submitView.submit()

		await expect(submitView.successMessage).toBeVisible()
	})
})
