/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	FormsJump,
	FormsJumpTarget,
	FormsQuestion,
} from '../types/Entities.d.ts'

import { QUESTION_EXTRASETTINGS_OTHER_PREFIX } from '../models/Constants.ts'

export const JUMP_TARGET_SUBMIT = 'submit'

export const QUESTION_TYPES_WITH_GOTO = ['dropdown', 'multiple_unique']

export const QUESTION_TYPES_WITH_NEXT = [
	'color',
	'date',
	'file',
	'grid',
	'long',
	'multiple',
	'ranking',
	'short',
	'time',
]

/** The answer a jump belongs to: an option or the answer "Other" */
export type JumpAnswer = { optionId: number } | { other: true }

/**
 * Whether the jump belongs to the answer
 *
 * @param jump the jump
 * @param answer the answer
 */
export function isJumpOf(jump: FormsJump, answer: JumpAnswer): boolean {
	return 'optionId' in answer
		? jump.optionId === answer.optionId
		: jump.other === true
}

/**
 * The jump target of a question for its answer: the target of the `goTo` entry matching the first value of the answer, else `next` on question types with `next`, else `null` for the following question.
 * Mirrors `SubmissionService::getJumpTarget()`.
 *
 * @param question the question
 * @param answer the values of the answer to the question
 */
export function getJumpTarget(
	question: FormsQuestion,
	answer: unknown,
): FormsJumpTarget | null {
	const extraSettings = question.extraSettings ?? {}
	const value: unknown = Array.isArray(answer) ? answer[0] : undefined
	const goTo: unknown[] = Array.isArray(extraSettings.goTo)
		? extraSettings.goTo
		: []

	if (typeof value === 'number' || typeof value === 'string') {
		for (const entry of goTo) {
			if (typeof entry !== 'object' || entry === null) {
				continue
			}
			const jump = entry as Partial<Record<keyof FormsJump, unknown>>
			if (
				(jump.optionId !== undefined
					&& String(jump.optionId) === String(value))
				|| (jump.other === true
					&& !!extraSettings.allowOtherAnswer
					&& typeof value === 'string'
					&& value.startsWith(QUESTION_EXTRASETTINGS_OTHER_PREFIX))
			) {
				return typeof jump.target === 'number'
					|| typeof jump.target === 'string'
					? (jump.target as FormsJumpTarget)
					: null
			}
		}
	}

	const next = QUESTION_TYPES_WITH_NEXT.includes(question.type)
		? extraSettings.next
		: undefined
	return typeof next === 'number' || typeof next === 'string' ? next : null
}

/**
 * The questions on the path of the respondent: from the first question, each question leads to the target of its answer in `goTo`, else to its `next` target, else to the following question, until the end of the form.
 * A target that is not a later question of the form counts as the following question.
 * Mirrors `SubmissionService::getVisibleQuestions()`.
 *
 * @param questions the questions of the form in their order
 * @param answers the answers by question id
 */
export function getVisibleQuestions<T extends FormsQuestion>(
	questions: T[],
	answers: Record<number | string, unknown>,
): T[] {
	const positions = new Map(
		questions.map((question, position) => [question.id, position]),
	)
	const visibleQuestions: T[] = []

	let position = 0
	while (position < questions.length) {
		const question = questions[position]
		visibleQuestions.push(question)

		const target = getJumpTarget(question, answers[question.id])
		if (target === JUMP_TARGET_SUBMIT) {
			break
		}
		const targetPosition =
			typeof target === 'number' ? positions.get(target) : undefined
		position =
			targetPosition !== undefined && targetPosition > position
				? targetPosition
				: position + 1
	}

	return visibleQuestions
}

/** The jumps of a form that change the path in a way the author may not expect */
export interface JumpIssues {
	/** Ids of the questions with a jump to a question that was deleted or comes before them */
	broken: Set<number>
	/** These jumps, each as the question id, a colon and `next` or the answer of the jump as JSON */
	brokenJumps: Set<string>
	/** Ids of the questions no respondent reaches */
	unreachable: Set<number>
	/** Ids of the reachable questions that a jump of an earlier question can skip */
	skippable: Set<number>
}

/**
 * The answers a respondent can give to a question with one answer, for which `goTo` can hold a jump; none without options, as the submit view does not show such a question
 *
 * @param question the question
 */
function getPossibleAnswers(question: FormsQuestion): JumpAnswer[] | null {
	if (!QUESTION_TYPES_WITH_GOTO.includes(question.type)) {
		return null
	}
	const answers: JumpAnswer[] = (question.options ?? []).map((option) => ({
		optionId: option.id,
	}))
	if (answers.length > 0 && question.extraSettings?.allowOtherAnswer) {
		answers.push({ other: true })
	}
	return answers
}

/**
 * The positions a question can lead to, the number of questions standing for the end of the form, and its jumps to a question that was deleted or comes before it.
 * A question with answers to choose from leads to the following question instead of a `next` target, unless it is required and all its answers have a jump. Jumps of answers that no longer exist, of the answer "Other" while it is off, and of questions without a title, which the submit view does not show, lead nowhere, but still count as broken.
 *
 * @param questions the questions of the form in their order
 * @param position the position of the question
 * @param positions the positions by question id
 */
function getTargetPositions(
	questions: FormsQuestion[],
	position: number,
	positions: Map<number, number>,
): { targets: Set<number>; brokenJumps: string[] } {
	const question = questions[position]
	const extraSettings = question.extraSettings ?? {}
	const goTo = getJumps(extraSettings)
	const brokenJumps: string[] = []

	const toPosition = (
		target: FormsJumpTarget | null | undefined,
		jump: string,
	): number => {
		if (target === JUMP_TARGET_SUBMIT) {
			return questions.length
		}
		const targetPosition =
			typeof target === 'number' ? positions.get(target) : undefined
		if (targetPosition !== undefined && targetPosition > position) {
			return targetPosition
		}
		if (target !== null && target !== undefined) {
			brokenJumps.push(`${question.id}:${jump}`)
		}
		return position + 1
	}

	const possibleAnswers = question.text?.trim() ? getPossibleAnswers(question) : []
	const targets = new Set<number>()
	for (const jump of goTo) {
		const targetPosition = toPosition(
			jump.target,
			JSON.stringify({ ...jump, target: undefined }),
		)
		if (
			possibleAnswers === null
			|| possibleAnswers.some((answer) => isJumpOf(jump, answer))
		) {
			targets.add(targetPosition)
		}
	}
	const allAnswersJump =
		!!question.isRequired
		&& possibleAnswers !== null
		&& possibleAnswers.length > 0
		&& possibleAnswers.every((answer) =>
			goTo.some((jump) => isJumpOf(jump, answer)),
		)
	if (!allAnswersJump) {
		targets.add(
			toPosition(
				QUESTION_TYPES_WITH_NEXT.includes(question.type)
					? extraSettings.next
					: null,
				'next',
			),
		)
	}

	return { targets, brokenJumps }
}

/**
 * Find the jumps of a form that lead somewhere else than the author chose, the questions no respondent reaches, and the questions a jump can skip
 *
 * @param questions the questions of the form in their order
 */
export function getJumpIssues(questions: FormsQuestion[]): JumpIssues {
	const positions = new Map(
		questions.map((question, position) => [question.id, position]),
	)
	const issues: JumpIssues = {
		broken: new Set(),
		brokenJumps: new Set(),
		unreachable: new Set(),
		skippable: new Set(),
	}

	const reached = new Set(questions.length > 0 ? [0] : [])
	let farthestTarget = 0
	for (let position = 0; position < questions.length; position++) {
		const question = questions[position]
		const { targets, brokenJumps } = getTargetPositions(
			questions,
			position,
			positions,
		)
		if (brokenJumps.length > 0) {
			issues.broken.add(question.id)
			brokenJumps.forEach((jump) => issues.brokenJumps.add(jump))
		}
		if (!reached.has(position)) {
			issues.unreachable.add(question.id)
			continue
		}
		if (farthestTarget > position) {
			issues.skippable.add(question.id)
		}
		for (const target of targets) {
			reached.add(target)
			farthestTarget = Math.max(farthestTarget, target)
		}
	}

	return issues
}

/**
 * The ids of the questions with a jump that is broken after a change but was not before
 *
 * @param before the jump issues before the change
 * @param after the jump issues after the change
 */
export function getNewlyBrokenQuestions(
	before: JumpIssues,
	after: JumpIssues,
): number[] {
	return [
		...new Set(
			[...after.brokenJumps]
				.filter((jump) => !before.brokenJumps.has(jump))
				.map((jump) => Number.parseInt(jump, 10)),
		),
	]
}

/**
 * The well-formed entries of `goTo`: objects with the id of a question or the end of the form as target
 *
 * @param extraSettings the extra settings of a question
 */
export function getJumps(
	extraSettings: { goTo?: unknown } | null | undefined,
): FormsJump[] {
	const goTo = extraSettings?.goTo
	return Array.isArray(goTo)
		? goTo.filter(
				(jump): jump is FormsJump =>
					typeof jump === 'object'
					&& jump !== null
					&& (typeof jump.target === 'number'
						|| typeof jump.target === 'string'),
			)
		: []
}

/**
 * The items as a list in the given language, e.g. "1, 2 and 3".
 * A language variant after "@" counts as its base language, "sr@latin" as Serbian in Latin script.
 *
 * @param items the items
 * @param language the language code of Nextcloud, e.g. from `getLanguage()`
 */
export function formatList(items: string[], language: string): string {
	return new Intl.ListFormat(
		language.replace('@latin', '-Latn').replace(/@.*$/, ''),
		{ type: 'conjunction' },
	).format(items)
}
