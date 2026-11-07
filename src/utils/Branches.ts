/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { FormsConditionalBranch, FormsQuestion } from '../types/Entities.d.ts'

import { splitRegex } from './RegularExpression.ts'

type BranchMatcher = (
	conditions: Record<string, unknown>[],
	values: string[],
) => boolean

/**
 * Question types that can have branches, with how a branch condition matches the answer
 * !! Keep in sync with Constants::QUESTION_TYPES_WITH_BRANCHES and SubmissionService::evaluateBranchConditions() !!
 */
const BRANCH_MATCHERS: Record<string, BranchMatcher> = {
	multiple_unique: matchOptionSelected,
	dropdown: matchOptionSelected,
	multiple: matchOptionsCombination,
	date: matchDateRange,
	datetime: matchDateRange,
	time: matchDateRange,
	file: matchFileUploaded,
	linearscale: matchValue,
	color: matchColor,
	short: matchText,
	long: matchText,
}

/**
 * A single select matches if the selected option is one of the conditions
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchOptionSelected(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	return conditions.some((condition) =>
		values.includes(String(condition.optionId)),
	)
}

/**
 * Checkboxes match if all options of any condition's combination are selected
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchOptionsCombination(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	return conditions.some(
		(condition) =>
			Array.isArray(condition.optionIds)
			&& condition.optionIds.length > 0
			&& condition.optionIds.every((id) => values.includes(String(id))),
	)
}

/**
 * Dates and times match if they are within the range of any condition, including its bounds
 *
 * Answers and bounds use the same format (`YYYY-MM-DD`, `HH:mm`), so they compare as strings.
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchDateRange(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	const value = values[0]
	return conditions.some(
		(condition) =>
			!(condition.min && value < String(condition.min))
			&& !(condition.max && value > String(condition.max)),
	)
}

/**
 * Files match if a condition is about the upload state of the answer, also "no file uploaded"
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchFileUploaded(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	const hasFile = values.length > 0 && values[0] !== ''
	return conditions.some(
		(condition) => (condition.fileUploaded ?? true) === hasFile,
	)
}

/**
 * Linear scales match if the value fits any condition
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchValue(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	const value = parseFloat(values[0]) || 0
	const min = (condition: Record<string, unknown>) =>
		condition.min ?? Number.MIN_SAFE_INTEGER
	const max = (condition: Record<string, unknown>) =>
		condition.max ?? Number.MAX_SAFE_INTEGER
	return conditions.some((condition) => {
		switch (condition.type ?? 'value_equals') {
			case 'value_equals':
				return value === parseFloat(String(condition.value))
			case 'value_not_equals':
				return value !== parseFloat(String(condition.value))
			case 'value_min':
				return value >= Number(min(condition))
			case 'value_max':
				return value <= Number(max(condition))
			case 'value_range':
				return (
					value >= Number(min(condition))
					&& value <= Number(max(condition))
				)
			default:
				return false
		}
	})
}

/**
 * Colors match if they equal the value of any condition, ignoring case
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchColor(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	const color = values[0].toLowerCase()
	return conditions.some(
		(condition) => color === String(condition.value ?? '').toLowerCase(),
	)
}

/**
 * Texts match if they equal or contain the value of any condition, or match its regular expression
 *
 * @param conditions the conditions of the branch
 * @param values the answer of the question
 */
function matchText(
	conditions: Record<string, unknown>[],
	values: string[],
): boolean {
	const text = values[0]
	return conditions.some((condition) => {
		const value = String(condition.value ?? '')
		switch (condition.type) {
			case 'regex':
				return matchRegex(value, text)
			case 'string_contains':
				return text.includes(value)
			case 'string_equals':
				return text === value
			default:
				return false
		}
	})
}

/**
 * Match a regular expression in the `/pattern/modifiers` format of the server
 *
 * @param expression the regular expression with delimiters, e.g. `/^yes$/i`
 * @param subject the text to match
 */
function matchRegex(expression: string, subject: string): boolean {
	// Long texts are not matched, like on the server
	if (!expression || subject.length > 10000) {
		return false
	}
	const { pattern, modifiers } = splitRegex(expression)
	if (!pattern) {
		return false
	}
	try {
		return new RegExp(pattern, modifiers).test(subject)
	} catch {
		return false
	}
}

/**
 * @param type the question type
 */
export function canHaveBranches(type: string): boolean {
	return type in BRANCH_MATCHERS
}

/**
 * @param question the question
 */
export function getBranches(question: FormsQuestion): FormsConditionalBranch[] {
	return question.extraSettings?.branches ?? []
}

/**
 * Extra settings as stored, the subquestions are rows of their own
 *
 * @param extraSettings the extra settings of a question
 */
export function persistedSettings<T extends Record<string, unknown>>(
	extraSettings: T,
): T {
	if (!Array.isArray(extraSettings.branches)) {
		return extraSettings
	}
	return {
		...extraSettings,
		branches: extraSettings.branches.map(
			// eslint-disable-next-line @typescript-eslint/no-unused-vars
			({ subQuestions, ...branch }) => branch,
		),
	}
}

/**
 * Branches the answer activates, an unanswered question activates none except "no file uploaded"
 *
 * @param question the question with the branches
 * @param values the answer of the question
 */
export function getActiveBranches(
	question: FormsQuestion,
	values: unknown,
): FormsConditionalBranch[] {
	const matcher = BRANCH_MATCHERS[question.type]
	const answer = Array.isArray(values) ? values.map(String) : []
	if (
		!matcher
		|| (question.type !== 'file' && (answer.length === 0 || answer[0] === ''))
	) {
		return []
	}
	return getBranches(question).filter(
		(branch) =>
			(branch.conditions?.length ?? 0) > 0
			&& matcher(branch.conditions, answer),
	)
}

/**
 * Subquestions shown for the answer
 *
 * @param question the question with the branches
 * @param values the answer of the question
 * @param isShown whether a subquestion is complete enough to be shown, like other questions
 */
export function getVisibleSubQuestions(
	question: FormsQuestion,
	values: unknown,
	isShown: (question: FormsQuestion) => boolean,
): FormsQuestion[] {
	return getActiveBranches(question, values)
		.flatMap((branch) => branch.subQuestions ?? [])
		.filter(isShown)
}
