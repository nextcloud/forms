/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { InjectionKey, Ref } from 'vue'

/** The question type of a section */
export const QUESTION_TYPE_SECTION = 'section'

/** The number of the section whose bar sticks to the top of the editor, null while none does */
export const STUCK_SECTION_KEY: InjectionKey<Readonly<Ref<number | null>>> =
	Symbol('forms:stuck-section')

/**
 * The prefix of the element ids of a question or section: `q` or `s` with its number
 *
 * @param type the question type
 * @param index the number of the question or section
 */
export function getIdPrefix(type: string, index: number | undefined): string {
	return (type === QUESTION_TYPE_SECTION ? 's' : 'q') + index
}

/**
 * The number of each question and section by id: questions count from 1 without the sections, sections count from 1 on their own
 *
 * @param questions the questions and sections in their order
 */
export function getNumbers(
	questions: Array<{ id: number; type: string }>,
): Map<number, number> {
	let questionNumber = 0
	let sectionNumber = 0
	return new Map(
		questions.map((question) => [
			question.id,
			question.type === QUESTION_TYPE_SECTION
				? ++sectionNumber
				: ++questionNumber,
		]),
	)
}

/**
 * Whether a question or section has a title other than white space
 *
 * @param question the question or section
 * @param question.text its title
 */
export function hasTitle(question: { text: string }): boolean {
	return question.text.trim() !== ''
}

/**
 * Whether a question is a section that shows respondents nothing: no title and no description
 *
 * @param question the question or section
 * @param question.type its type
 * @param question.text its title
 * @param question.description its description
 */
export function isEmptySection(question: {
	type: string
	text: string
	description?: string
}): boolean {
	return (
		question.type === QUESTION_TYPE_SECTION
		&& !hasTitle(question)
		&& !question.description?.trim()
	)
}

/**
 * The ids of the questions that follow a section with a title; a section without title ends the section before it, so its questions are headed like questions outside a section
 *
 * @param questions the questions and sections in their order
 */
export function getQuestionIdsInSections(
	questions: Array<{ id: number; type: string; text: string }>,
): Set<number> {
	let inSection = false
	return new Set(
		questions
			.filter((question) => {
				if (question.type === QUESTION_TYPE_SECTION) {
					inSection = hasTitle(question)
					return false
				}
				return inSection
			})
			.map((question) => question.id),
	)
}
