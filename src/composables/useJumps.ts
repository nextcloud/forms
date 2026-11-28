/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, InjectionKey } from 'vue'
import type {
	FormsJump,
	FormsJumpTarget,
	FormsQuestion,
} from '../types/Entities.d.ts'
import type { JumpAnswer, JumpIssues } from '../utils/Jumps.ts'

import { showConfirmation } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, inject, reactive, ref, watch } from 'vue'
import {
	canDecideByAnswer,
	getJumps,
	isJumpOf,
	JUMP_TARGET_SUBMIT,
	jumpsPastFollowingQuestion,
	QUESTION_TYPES_WITH_GOTO,
	QUESTION_TYPES_WITH_NEXT,
} from '../utils/Jumps.ts'
import {
	getNumbers,
	getSectionIndexes,
	isAllowedTarget,
	QUESTION_TYPE_SECTION,
} from '../utils/Sections.ts'

/** The jump issues of the form, see `getJumpIssues()`, provided by the editor */
export const FORM_JUMP_ISSUES_KEY: InjectionKey<ComputedRef<JumpIssues>> =
	Symbol('formJumpIssues')

/** The questions of the form in their order, provided by the editor */
export const FORM_QUESTIONS_KEY: InjectionKey<ComputedRef<FormsQuestion[]>> =
	Symbol('formQuestions')

export interface JumpTargetOption {
	id: FormsJumpTarget | null
	label: string
}

interface JumpQuestionProps {
	id: number
	readOnly?: boolean
	isRequired?: boolean
	type?: unknown
	extraSettings?: Record<string, unknown> | null
}

/**
 * The jumps of a question in the editor: the target per answer (`goTo`) and after the question (`next`).
 * Changes are kept locally and saved as complete lists.
 *
 * @param props the props of the question
 * @param saveExtraSettings saves changed extra settings of the question
 */
export function useJumps(
	props: JumpQuestionProps,
	saveExtraSettings: (settings: Record<string, unknown>) => void,
) {
	const formQuestions = inject(
		FORM_QUESTIONS_KEY,
		computed(() => []),
	)
	const formJumpIssues = inject(FORM_JUMP_ISSUES_KEY, null)

	const goTo = ref<FormsJump[]>([])
	const next = ref<FormsJumpTarget | null>(null)
	const enabled = ref(false)

	const canGoTo = computed(() =>
		QUESTION_TYPES_WITH_GOTO.includes(String(props.type)),
	)
	const isSection = computed(() => props.type === QUESTION_TYPE_SECTION)
	const canNext = computed(
		() =>
			isSection.value || QUESTION_TYPES_WITH_NEXT.includes(String(props.type)),
	)

	watch(
		() => props.extraSettings,
		(extraSettings) => {
			goTo.value = canGoTo.value ? getJumps(extraSettings) : []
			next.value = canNext.value
				? ((extraSettings?.next as FormsJumpTarget | undefined) ?? null)
				: null
			if (goTo.value.length > 0 || next.value !== null) {
				enabled.value = true
			}
		},
		{ immediate: true },
	)

	const showTargets = computed(
		() => !props.readOnly && canGoTo.value && enabled.value,
	)
	const showNext = computed(
		() => !props.readOnly && canNext.value && (enabled.value || isSection.value),
	)

	const position = computed(() =>
		formQuestions.value.findIndex((question) => question.id === props.id),
	)
	const numbers = computed(() => getNumbers(formQuestions.value))
	const sections = computed(() => getSectionIndexes(formQuestions.value))
	const number = computed(() => numbers.value.get(props.id) ?? position.value + 1)
	const nextLabel = computed(() =>
		isSection.value
			? t('forms', 'After section {number} go to', { number: number.value })
			: t('forms', 'After question {number}', { number: number.value }),
	)
	const hasLaterSection = computed(() =>
		formQuestions.value
			.slice(position.value + 1)
			.some((question) => question.type === QUESTION_TYPE_SECTION),
	)
	const endsForm = computed(() => isSection.value && !hasLaterSection.value)
	const shownNext = computed(() =>
		endsForm.value && next.value === JUMP_TARGET_SUBMIT ? null : next.value,
	)
	const fallbackLabel = computed(() =>
		!isSection.value
			? undefined
			: endsForm.value
				? t(
						'forms',
						'End of the form (the chosen section was deleted or moved up)',
					)
				: t(
						'forms',
						'Next section (the chosen section was deleted or moved up)',
					),
	)

	const targetOptions = computed<JumpTargetOption[]>((previous) => {
		const submitOption: JumpTargetOption = {
			id: JUMP_TARGET_SUBMIT,
			label: t('forms', 'End of the form'),
		}
		const options: JumpTargetOption[] = [
			{
				id: null,
				label: !isSection.value
					? t('forms', 'Next question')
					: endsForm.value
						? t('forms', 'End of the form')
						: t('forms', 'Next section'),
			},
			...formQuestions.value
				.filter((question, targetPosition) =>
					isAllowedTarget(
						formQuestions.value,
						sections.value,
						position.value,
						targetPosition,
					),
				)
				.map((question) => {
					const number = numbers.value.get(question.id) ?? ''
					const title = question.text?.trim()
					return {
						id: question.id,
						label:
							question.type === QUESTION_TYPE_SECTION
								? t(
										'forms',
										'Section {number}: {title}',
										{
											number,
											title:
												title
												|| t('forms', 'Untitled section'),
										},
										{ escape: false, sanitize: false },
									)
								: t(
										'forms',
										'Question {number}: {title}',
										{
											number,
											title:
												title
												|| t('forms', 'Untitled question'),
										},
										{ escape: false, sanitize: false },
									),
					}
				}),
			...(endsForm.value ? [] : [submitOption]),
		]
		return previous?.length === options.length
			&& previous.every(
				(option, index) =>
					option.id === options[index].id
					&& option.label === options[index].label,
			)
			? previous
			: options
	})

	/**
	 * Save the jumps as complete lists, without empty keys
	 */
	function save(): void {
		saveExtraSettings({
			goTo: goTo.value.length > 0 ? [...goTo.value] : undefined,
			next: next.value ?? undefined,
		})
	}

	/**
	 * The target of an answer, `null` for the next question
	 *
	 * @param answer the answer
	 */
	function getTarget(answer: JumpAnswer): FormsJumpTarget | null {
		return goTo.value.find((jump) => isJumpOf(jump, answer))?.target ?? null
	}

	/**
	 * Set the target of an answer, `null` for the next question
	 *
	 * @param answer the answer
	 * @param target the target
	 */
	function setTarget(answer: JumpAnswer, target: FormsJumpTarget | null): void {
		goTo.value = [
			...goTo.value.filter((jump) => !isJumpOf(jump, answer)),
			...(target === null ? [] : [{ ...answer, target }]),
		]
		save()
	}

	/**
	 * Accessible name of the jump select of an answer
	 *
	 * @param answerText the text of the answer
	 */
	function getTargetLabel(answerText: string): string {
		return t(
			'forms',
			'After "{answer}" go to',
			{ answer: answerText },
			{ escape: false, sanitize: false },
		)
	}

	/**
	 * Set the target after the question, `null` for the next question
	 *
	 * @param target the target
	 */
	function setNext(target: FormsJumpTarget | null): void {
		next.value = target
		save()
	}

	/**
	 * Switch the jumps on or off; switching off asks before it removes all jumps of the question
	 *
	 * @param on whether jumps are switched on
	 */
	async function setEnabled(on: boolean): Promise<void> {
		if (!on && (goTo.value.length > 0 || next.value !== null)) {
			const remove = await showConfirmation({
				name: t('forms', 'Remove jumps'),
				text: t(
					'forms',
					'Remove all jumps of question {number}? Respondents then go to the next question.',
					{ number: number.value },
				),
				labelConfirm: t('forms', 'Remove jumps'),
				labelReject: t('forms', 'Keep jumps'),
				severity: 'warning',
			}).catch(() => false)
			if (!remove) {
				return
			}
			goTo.value = []
			next.value = null
			save()
		}
		enabled.value = on
	}

	const warnings = computed<string[]>((previous) => {
		const issues = formJumpIssues?.value
		if (!issues) {
			return []
		}
		const current = [
			...(issues.broken.has(props.id)
				? [
						t(
							'forms',
							'A jump of this question goes to a question or section that was deleted or now comes before it. Respondents go to the next question instead.',
						),
					]
				: []),
			...(issues.leavingSection.has(props.id)
				? [
						t(
							'forms',
							'Other questions of this section jump out of it as well. Respondents follow the first of these jumps on their path.',
						),
					]
				: []),
			...(issues.unreachable.has(props.id)
				? [
						props.type === QUESTION_TYPE_SECTION
							? t(
									'forms',
									'No respondent reaches this section, because the jumps of the questions before it always skip it.',
								)
							: t(
									'forms',
									'No respondent reaches this question, because the jumps of the questions before it always skip it.',
								),
					]
				: []),
		]
		return current.length === 0 && previous?.length === 0 ? previous : current
	})

	const hint = computed<string>(() => {
		if (!canGoTo.value || props.isRequired || goTo.value.length === 0) {
			return ''
		}
		const question = formQuestions.value[position.value]
		return question !== undefined
			&& canDecideByAnswer(question)
			&& jumpsPastFollowingQuestion(
				goTo.value,
				!!props.extraSettings?.allowOtherAnswer,
				position.value,
				new Map(
					formQuestions.value.map((question, index) => [
						question.id,
						index,
					]),
				),
				formQuestions.value.length,
			)
			? t(
					'forms',
					'Respondents see the questions after this one only once they answer it. If they leave it empty, they can submit the form without the questions after it, even required ones. Make it required to prevent this.',
				)
			: ''
	})

	return reactive({
		canGoTo,
		canNext,
		enabled,
		showTargets,
		showNext,
		nextLabel,
		warnings,
		hint,
		next: shownNext,
		fallbackLabel,
		targetOptions,
		getTarget,
		getTargetLabel,
		setTarget,
		setNext,
		setEnabled,
	})
}

export type Jumps = ReturnType<typeof useJumps>
