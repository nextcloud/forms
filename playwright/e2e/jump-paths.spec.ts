/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FormsQuestion } from '../../src/types/Entities.d.ts'

import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { getVisibleQuestions } from '../../src/utils/Jumps.ts'

/**
 * Runs the cases of tests/fixtures/jump-paths.json, shared with SubmissionServiceTest::testJumpPaths()
 */
const cases: Array<{
	name: string
	questions: FormsQuestion[]
	answers: Record<string, unknown>
	visible: number[]
}> = JSON.parse(
	readFileSync(
		new URL('../../tests/fixtures/jump-paths.json', import.meta.url),
		'utf-8',
	),
)

for (const { name, questions, answers, visible } of cases) {
	test(`jump path: ${name}`, () => {
		expect(
			getVisibleQuestions(questions, answers).map((question) => question.id),
		).toEqual(visible)
	})
}
