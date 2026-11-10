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
	getJumps,
	isJumpOf,
	JUMP_TARGET_SUBMIT,
	QUESTION_TYPES_WITH_GOTO,
	QUESTION_TYPES_WITH_NEXT,
} from '../utils/Jumps.ts'

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
	const switchedOn = ref(false)

	const canGoTo = computed(() =>
		QUESTION_TYPES_WITH_GOTO.includes(String(props.type)),
	)
	const canNext = computed(() =>
		QUESTION_TYPES_WITH_NEXT.includes(String(props.type)),
	)

	watch(
		() => props.extraSettings,
		(extraSettings) => {
			goTo.value = canGoTo.value ? getJumps(extraSettings) : []
			next.value = canNext.value
				? ((extraSettings?.next as FormsJumpTarget | undefined) ?? null)
				: null
		},
		{ immediate: true },
	)

	const enabled = computed(
		() => switchedOn.value || goTo.value.length > 0 || next.value !== null,
	)
	const showTargets = computed(
		() => !props.readOnly && canGoTo.value && enabled.value,
	)
	const showNext = computed(
		() => !props.readOnly && canNext.value && enabled.value,
	)

	const position = computed(() =>
		formQuestions.value.findIndex((question) => question.id === props.id),
	)
	const nextLabel = computed(() =>
		t('forms', 'After question {number}', { number: position.value + 1 }),
	)

	const targetOptions = computed<JumpTargetOption[]>((previous) => {
		const options: JumpTargetOption[] = [
			{ id: null, label: t('forms', 'Next question') },
			...formQuestions.value
				.slice(position.value + 1)
				.map((question, offset) => ({
					id: question.id,
					label: t(
						'forms',
						'Question {number}: {title}',
						{
							number: position.value + offset + 2,
							title: question.text || t('forms', 'Untitled question'),
						},
						{ escape: false, sanitize: false },
					),
				})),
			{ id: JUMP_TARGET_SUBMIT, label: t('forms', 'End of the form') },
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
		switchedOn.value = true
		goTo.value = [
			...goTo.value.filter((jump) => !isJumpOf(jump, answer)),
			...(target === null ? [] : [{ ...answer, target }]),
		]
		save()
	}

	/**
	 * Label of the jump select of an answer
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
		switchedOn.value = true
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
					{ number: position.value + 1 },
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
		switchedOn.value = on
	}

	const warnings = computed<string[]>(() => {
		const issues = formJumpIssues?.value
		if (!issues) {
			return []
		}
		return [
			...(issues.broken.has(props.id)
				? [
						t(
							'forms',
							'A jump of this question goes to a question that was deleted or now comes before it. Respondents go to the next question instead.',
						),
					]
				: []),
			...(issues.unreachable.has(props.id)
				? [
						t(
							'forms',
							'No respondent reaches this question, because the jumps of the questions before it always skip it.',
						),
					]
				: []),
		]
	})

	return reactive({
		canGoTo,
		canNext,
		enabled,
		showTargets,
		showNext,
		nextLabel,
		warnings,
		next,
		targetOptions,
		getTarget,
		getTargetLabel,
		setTarget,
		setNext,
		setEnabled,
	})
}

export type Jumps = ReturnType<typeof useJumps>
