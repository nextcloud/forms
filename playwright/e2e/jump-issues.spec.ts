/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FormsQuestion } from '../../src/types/Entities.d.ts'

import { expect, test } from '@playwright/test'
import {
	formatList,
	getJumpIssues,
	getNewlyBrokenQuestions,
} from '../../src/utils/Jumps.ts'

/**
 * A radio question with the options "Yes" (id 11) and "No" (id 12)
 *
 * @param id the id of the question
 * @param extraSettings the extra settings
 * @param isRequired whether the question is required
 */
function radio(
	id: number,
	extraSettings: FormsQuestion['extraSettings'],
	isRequired = false,
): FormsQuestion {
	return {
		id,
		text: `Question ${id}`,
		type: 'multiple_unique',
		isRequired,
		options: [
			{ id: 11, questionId: id, text: 'Yes', order: 1 },
			{ id: 12, questionId: id, text: 'No', order: 2 },
		],
		extraSettings,
	} as FormsQuestion
}

/**
 * A short text question
 *
 * @param id the id of the question
 * @param extraSettings the extra settings
 * @param isRequired whether the question is required
 */
function short(
	id: number,
	extraSettings: FormsQuestion['extraSettings'] = {},
	isRequired = false,
): FormsQuestion {
	return { id, text: `Question ${id}`, type: 'short', isRequired, extraSettings }
}

/**
 * The issues as plain values
 *
 * @param questions the questions of the form
 */
function issues(questions: FormsQuestion[]) {
	const { broken, unreachable, skippable } = getJumpIssues(questions)
	return {
		broken: [...broken],
		unreachable: [...unreachable],
		skippable: [...skippable],
	}
}

test('jump lists: a language variant such as sr@latin lists as its base language', () => {
	expect(formatList(['1', '2', '3'], 'sr@latin')).toBe('1, 2 i 3')
	expect(formatList(['1', '2'], 'de-DE')).toBe('1 und 2')
})

test('jump issues: a jump over a question lets respondents skip it', () => {
	expect(
		issues([
			radio(1, { goTo: [{ optionId: 12, target: 3 }] }),
			short(2),
			short(3),
		]),
	).toEqual({ broken: [], unreachable: [], skippable: [2] })
})

test('jump issues: "End of the form" lets respondents skip all later questions', () => {
	expect(
		issues([
			radio(1, { goTo: [{ optionId: 12, target: 'submit' }] }),
			short(2),
			short(3),
		]),
	).toEqual({ broken: [], unreachable: [], skippable: [2, 3] })
})

test('jump issues: a required question whose answers all jump past a question makes it unreachable', () => {
	expect(
		issues([
			radio(
				1,
				{
					goTo: [
						{ optionId: 11, target: 3 },
						{ optionId: 12, target: 'submit' },
					],
				},
				true,
			),
			short(2),
			short(3),
		]),
	).toEqual({ broken: [], unreachable: [2], skippable: [3] })
})

test('jump issues: an optional question without an answer goes to the following question', () => {
	expect(
		issues([
			radio(1, {
				goTo: [
					{ optionId: 11, target: 3 },
					{ optionId: 12, target: 3 },
				],
			}),
			short(2),
			short(3),
		]),
	).toEqual({ broken: [], unreachable: [], skippable: [2] })
})

test('jump issues: "End of the form" after a question makes the later questions unreachable', () => {
	expect(issues([short(1, { next: 'submit' }), short(2), short(3)])).toEqual({
		broken: [],
		unreachable: [2, 3],
		skippable: [],
	})
})

test('jump issues: a jump to an earlier or deleted question is broken', () => {
	expect(
		issues([
			short(1),
			radio(2, { goTo: [{ optionId: 12, target: 1 }] }),
			short(3, { next: 99 }),
			short(4),
		]),
	).toEqual({ broken: [2, 3], unreachable: [], skippable: [] })
})

test('jump issues: a jump that breaks is new even when its question has a broken jump already', () => {
	const pet = radio(1, {
		goTo: [
			{ optionId: 11, target: 99 },
			{ optionId: 12, target: 3 },
		],
	})
	const before = getJumpIssues([pet, short(2), short(3)])
	const after = getJumpIssues([short(3), pet, short(2)])

	expect(getNewlyBrokenQuestions(before, after)).toEqual([1])
	expect(getNewlyBrokenQuestions(before, before)).toEqual([])
})

test('jump issues: a malformed jump is ignored', () => {
	expect(
		issues([
			radio(1, {
				goTo: [null, { optionId: 12, target: [3] }],
			} as unknown as FormsQuestion['extraSettings']),
			short(2),
			short(3),
		]),
	).toEqual({ broken: [], unreachable: [], skippable: [] })
})

test('jump issues: a jump of a deleted option is ignored', () => {
	expect(
		issues([
			radio(1, { goTo: [{ optionId: 99, target: 3 }] }),
			short(2),
			short(3),
		]),
	).toEqual({ broken: [], unreachable: [], skippable: [] })
})

test('jump issues: with "Other" allowed, the answer "Other" needs a jump as well to make a question unreachable', () => {
	const goTo = [
		{ optionId: 11, target: 3 },
		{ optionId: 12, target: 3 },
	]
	expect(
		issues([
			radio(1, { goTo, allowOtherAnswer: true }, true),
			short(2),
			short(3),
		]).unreachable,
	).toEqual([])
	expect(
		issues([
			radio(
				1,
				{
					goTo: [...goTo, { other: true, target: 3 }],
					allowOtherAnswer: true,
				},
				true,
			),
			short(2),
			short(3),
		]).unreachable,
	).toEqual([2])
})

test('jump issues: the jumps of a question the submit view does not show lead nowhere', () => {
	const other = radio(
		1,
		{ goTo: [{ other: true, target: 3 }], allowOtherAnswer: true },
		true,
	)
	expect(issues([{ ...other, options: [] }, short(2), short(3)])).toEqual({
		broken: [],
		unreachable: [],
		skippable: [],
	})
	const untitled = radio(1, { goTo: [{ optionId: 12, target: 3 }] }, true)
	expect(issues([{ ...untitled, text: ' ' }, short(2), short(3)])).toEqual({
		broken: [],
		unreachable: [],
		skippable: [],
	})
})

test('jump issues: a jump of the answer "Other" breaks while "Other" is off', () => {
	const pet = radio(2, { goTo: [{ other: true, target: 3 }] })
	expect(
		getNewlyBrokenQuestions(
			getJumpIssues([short(1), pet, short(3)]),
			getJumpIssues([short(3), short(1), pet]),
		),
	).toEqual([2])
})
