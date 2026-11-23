/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	DatePollCount,
	DatePollVote,
	FormsOption,
	FormsSubmission,
} from '../types/Entities.d.ts'

import { t } from '@nextcloud/l10n'
import { DATEPOLL_VOTES } from '../models/Constants.ts'

/**
 * Show a stored slot in the local date and time format.
 *
 * @param text The stored slot, for example 2026-11-17 10:00
 */
export function formatDatePollSlot(text: string): string {
	const match = /^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/.exec(text)
	if (!match) {
		return text
	}
	const date = new Date(`${match[1]}T${match[2]}`)
	if (Number.isNaN(date.getTime())) {
		return text
	}
	return new Intl.DateTimeFormat(undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	}).format(date)
}

/**
 * @param vote The stored vote
 */
export function isDatePollVote(vote: unknown): vote is DatePollVote {
	return (DATEPOLL_VOTES as readonly unknown[]).includes(vote)
}

/**
 * The translated word for a vote.
 *
 * @param vote The vote
 */
export function datePollVoteLabel(vote: DatePollVote): string {
	switch (vote) {
		case 'yes':
			return t('forms', 'Yes')
		case 'no':
			return t('forms', 'No')
		case 'maybe':
			return t('forms', 'Maybe')
	}
}

/**
 * Read the stored slot id => vote map of one answer, skipping anything that is not a vote.
 *
 * @param text The stored answer text
 */
export function parseDatePollVotes(
	text: string | undefined,
): Record<string, DatePollVote> {
	let parsed: unknown
	try {
		parsed = JSON.parse(text ?? '')
	} catch {
		return {}
	}
	if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
		return {}
	}
	return Object.fromEntries(
		Object.entries(parsed).filter(([, vote]) => isDatePollVote(vote)),
	) as Record<string, DatePollVote>
}

/**
 * Count yes, no, and maybe for each slot of a question.
 *
 * @param questionId The date poll question
 * @param slots The slots of the question
 * @param submissions All submissions of the form
 */
export function countDatePollVotes(
	questionId: number,
	slots: FormsOption[],
	submissions: FormsSubmission[],
): DatePollCount[] {
	const counts = new Map<string, DatePollCount>(
		slots.map((slot) => [
			String(slot.id),
			{ optionId: slot.id, text: slot.text, yes: 0, no: 0, maybe: 0 },
		]),
	)

	for (const submission of submissions) {
		const answer = submission.answers.find(
			(answer) => answer.questionId === questionId,
		)
		for (const [slotId, vote] of Object.entries(
			parseDatePollVotes(answer?.text),
		)) {
			const count = counts.get(slotId)
			if (count) {
				count[vote]++
			}
		}
	}

	return [...counts.values()]
}
