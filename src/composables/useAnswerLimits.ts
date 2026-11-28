/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, Ref } from 'vue'

import { getCanonicalLocale, n, t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

export type AnswerLimitSettings = {
	lengthMax?: number
	lengthMin?: number
	numberExclude?: boolean
	numberInteger?: boolean
	numberMax?: number
	numberMin?: number
	textContains?: string
	textNotContains?: string
}

export type NumberRule =
	| 'any'
	| 'between'
	| 'notBetween'
	| 'greaterThan'
	| 'atLeast'
	| 'lessThan'
	| 'atMost'
	| 'equal'
	| 'notEqual'

export type AnswerRuleKind =
	'length' | 'contains' | 'notContains' | 'number' | 'whole'

/**
 * The extra settings of all answer rules, each removed
 */
export const NO_ANSWER_RULES: Record<keyof AnswerLimitSettings, undefined> = {
	lengthMax: undefined,
	lengthMin: undefined,
	numberExclude: undefined,
	numberInteger: undefined,
	numberMax: undefined,
	numberMin: undefined,
	textContains: undefined,
	textNotContains: undefined,
}

type AnswerRule = {
	hint: string
	isValid: (answer: string) => boolean
}

/**
 * The number of characters of an answer, counting each Unicode code point once
 *
 * @param answer the answer
 */
export function answerLength(answer: string): number {
	return [...answer].length
}

/**
 * The number rule the range settings of a question describe
 *
 * @param settings the extra settings of the question
 */
export function numberRule(settings: AnswerLimitSettings): NumberRule {
	const { numberMin: min, numberMax: max, numberExclude: exclude } = settings
	if (min !== undefined && max !== undefined) {
		if (min === max) {
			return exclude ? 'notEqual' : 'equal'
		}
		return exclude ? 'notBetween' : 'between'
	}
	if (min !== undefined) {
		return exclude ? 'lessThan' : 'atLeast'
	}
	if (max !== undefined) {
		return exclude ? 'greaterThan' : 'atMost'
	}
	return 'any'
}

/**
 * The lowest power of ten with one digit more than the whole part of a number,
 * like 100 for 18 and 10 for 0
 *
 * @param number the number
 */
function nextPowerOfTen(number: number): number {
	return 10 ** String(Math.trunc(Math.abs(number))).length
}

/**
 * The range proposed when switching to a range rule: an existing range stays,
 * a highest number becomes 0 to it, a lowest number goes up to the next power
 * of ten, and an equal number becomes 0 to its double
 *
 * @param min the current lowest number
 * @param max the current highest number
 */
function proposedRange(min?: number, max?: number): [number, number] {
	if (min !== undefined && max !== undefined) {
		if (min < max) {
			return [min, max]
		}
		return min === 0 ? [0, 10] : [Math.min(0, min * 2), Math.max(0, min * 2)]
	}
	if (min !== undefined) {
		return [min, nextPowerOfTen(min)]
	}
	if (max !== undefined) {
		return max > 0 ? [0, max] : [-nextPowerOfTen(max), max]
	}
	return [0, 100]
}

/**
 * The range settings for a number rule, keeping the current numbers where possible
 *
 * @param rule the new number rule
 * @param settings the current extra settings of the question
 */
export function numberRuleSettings(
	rule: NumberRule,
	settings: AnswerLimitSettings,
): Pick<AnswerLimitSettings, 'numberExclude' | 'numberMax' | 'numberMin'> {
	const { numberMin: min, numberMax: max } = settings
	const low = min ?? max ?? 0
	switch (rule) {
		case 'between':
		case 'notBetween': {
			const [rangeMin, rangeMax] = proposedRange(min, max)
			return {
				numberMin: rangeMin,
				numberMax: rangeMax,
				numberExclude: rule === 'notBetween' || undefined,
			}
		}
		case 'atLeast':
		case 'lessThan':
			return {
				numberMin: low,
				numberMax: undefined,
				numberExclude: rule === 'lessThan' || undefined,
			}
		case 'greaterThan':
			return { numberMin: undefined, numberMax: low, numberExclude: true }
		case 'atMost':
			return {
				numberMin: undefined,
				numberMax: max ?? low,
				numberExclude: undefined,
			}
		case 'equal':
		case 'notEqual':
			return {
				numberMin: low,
				numberMax: low,
				numberExclude: rule === 'notEqual' || undefined,
			}
		default:
			return {
				numberMin: undefined,
				numberMax: undefined,
				numberExclude: undefined,
			}
	}
}

/**
 * Whether any answer rule is set
 *
 * @param settings the extra settings of the question
 */
export function hasAnswerRules(settings: AnswerLimitSettings): boolean {
	return Object.keys(NO_ANSWER_RULES).some(
		(key) => settings[key as keyof AnswerLimitSettings] !== undefined,
	)
}

/**
 * Why answer rules of different kinds contradict each other, so that no
 * answer can meet them all; null if they do not
 *
 * @param settings the extra settings of the question
 */
export function rulesConflict(settings: AnswerLimitSettings): string | null {
	const { textContains, textNotContains, lengthMax } = settings
	if (
		textContains
		&& textNotContains
		&& textContains.toLowerCase().includes(textNotContains.toLowerCase())
	) {
		return t('forms', 'The required text contains the excluded text')
	}
	if (textContains && lengthMax && answerLength(textContains) > lengthMax) {
		return t('forms', 'The required text is longer than the maximum length')
	}
	const { numberInteger, numberExclude, numberMin, numberMax } = settings
	if (
		numberInteger
		&& !numberExclude
		&& numberMin !== undefined
		&& numberMax !== undefined
		&& Math.ceil(numberMin) > numberMax
	) {
		return t('forms', 'No whole number is in this range')
	}
	return null
}

/**
 * The rule of the length limits for respondents
 *
 * @param min the minimum number of characters
 * @param max the maximum number of characters
 */
function lengthRule(min?: number, max?: number): AnswerRule | null {
	let hint: string
	if (min && max) {
		hint =
			min === max
				? n(
						'forms',
						'Enter exactly %n character',
						'Enter exactly %n characters',
						min,
					)
				: t('forms', 'Enter between {min} and {max} characters', {
						min,
						max,
					})
	} else if (min) {
		hint = n(
			'forms',
			'Enter at least %n character',
			'Enter at least %n characters',
			min,
		)
	} else if (max) {
		hint = n(
			'forms',
			'Enter at most %n character',
			'Enter at most %n characters',
			max,
		)
	} else {
		return null
	}
	return {
		hint,
		isValid: (answer) => {
			const length = answerLength(answer)
			return (!min || length >= min) && (!max || length <= max)
		},
	}
}

/**
 * The rule of the number range for respondents
 *
 * @param settings the extra settings of the question
 */
function numberRangeRule(settings: AnswerLimitSettings): AnswerRule | null {
	const { numberMin, numberMax } = settings
	const format = new Intl.NumberFormat(getCanonicalLocale(), {
		maximumFractionDigits: 20,
	}).format
	const min = numberMin === undefined ? '' : format(numberMin)
	const max = numberMax === undefined ? '' : format(numberMax)
	const hints: Record<NumberRule, string | null> = {
		any: null,
		between: t('forms', 'Enter a number from {min} to {max}', { min, max }),
		notBetween: t('forms', 'Enter a number below {min} or above {max}', {
			min,
			max,
		}),
		greaterThan: t('forms', 'Enter a number greater than {number}', {
			number: max,
		}),
		atLeast: t('forms', 'Enter a number of at least {min}', { min }),
		lessThan: t('forms', 'Enter a number less than {number}', { number: min }),
		atMost: t('forms', 'Enter a number of at most {max}', { max }),
		equal: t('forms', 'Enter the number {number}', { number: min }),
		notEqual: t('forms', 'Enter any number except {number}', { number: min }),
	}
	const hint = hints[numberRule(settings)]
	if (!hint) {
		return null
	}
	return {
		hint,
		isValid: (answer) => {
			const number = Number(answer)
			if (answer.trim() === '' || isNaN(number)) {
				return true
			}
			const inRange =
				(numberMin === undefined || number >= numberMin)
				&& (numberMax === undefined || number <= numberMax)
			return settings.numberExclude ? !inRange : inRange
		},
	}
}

/**
 * The rule that a number answer must be a whole number
 *
 * @param integer whether only whole numbers are accepted
 */
function wholeNumberRule(integer?: boolean): AnswerRule | null {
	if (!integer) {
		return null
	}
	return {
		hint: t('forms', 'Enter a whole number'),
		isValid: (answer) => {
			const number = Number(answer)
			return answer.trim() === '' || isNaN(number) || Number.isInteger(number)
		},
	}
}

/**
 * The rule that an answer must or must not contain a text, ignoring case
 *
 * @param text the text to look for
 * @param contains whether the answer must contain the text or must not
 */
function containsRule(
	text: string | undefined,
	contains: boolean,
): AnswerRule | null {
	if (!text) {
		return null
	}
	const hint = contains
		? t('forms', 'Include “{text}”', { text }, undefined, { escape: false })
		: t('forms', 'Do not include “{text}”', { text }, undefined, {
				escape: false,
			})
	return {
		hint,
		isValid: (answer) =>
			answer.toLowerCase().includes(text.toLowerCase()) === contains,
	}
}

/**
 * Length limits, number range and required texts of a text answer: the hint
 * and counter shown to respondents, the check of an answer and the editor
 * handlers of the limits
 *
 * @param extraSettings the extra settings of the question
 * @param value the current answer
 * @param answerLengthMax the longest answer that can be stored
 */
export function useAnswerLimits(
	extraSettings: ComputedRef<AnswerLimitSettings>,
	value: Ref<string>,
	answerLengthMax: number,
) {
	const rules = computed(() =>
		[
			lengthRule(extraSettings.value.lengthMin, extraSettings.value.lengthMax),
			numberRangeRule(extraSettings.value),
			wholeNumberRule(extraSettings.value.numberInteger),
			containsRule(extraSettings.value.textContains, true),
			containsRule(extraSettings.value.textNotContains, false),
		].filter((rule): rule is AnswerRule => rule !== null),
	)

	const limitHint = computed(
		() => rules.value.map((rule) => rule.hint).join(' · ') || null,
	)

	const hasLengthLimit = computed(
		() => !!(extraSettings.value.lengthMin || extraSettings.value.lengthMax),
	)

	const lengthCounter = computed(() => {
		const count = answerLength(value.value)
		const max = extraSettings.value.lengthMax
		return max
			? t('forms', '{count} / {max}', { count, max })
			: n('forms', '%n character', '%n characters', count)
	})

	/**
	 * The hint of the first rule the answer breaks as error message, otherwise null
	 *
	 * @param answer the answer to check
	 */
	const limitError = (answer: string): string | null => {
		if (!answer) {
			return null
		}
		return rules.value.find((rule) => !rule.isValid(answer))?.hint ?? null
	}

	/**
	 * Whether the answer rules are switched on in the editor, initially when any is set
	 */
	const rulesSwitchedOn = ref(hasAnswerRules(extraSettings.value))

	/**
	 * Whether the editor shows the answer rules: when switched on or any is set
	 */
	const showRules = computed(
		() => rulesSwitchedOn.value || hasAnswerRules(extraSettings.value),
	)

	/**
	 * Show or hide the answer rules in the editor, removing them all when hidden
	 *
	 * @param shown whether to show the rules
	 * @param onExtraSettingsChange saves changed extra settings of the question
	 */
	const toggleRules = (
		shown: boolean,
		onExtraSettingsChange: (settings: Record<string, unknown>) => void,
	): void => {
		rulesSwitchedOn.value = shown
		if (!shown) {
			onExtraSettingsChange({ ...NO_ANSWER_RULES })
		}
	}

	/**
	 * Cut text just typed or pasted into an answer field beyond the maximum
	 * length, counted in characters like the counter and the server do; text
	 * that was already in the field is kept
	 *
	 * @param field the answer field, changed since `value` was last updated
	 */
	const fitLength = (field: HTMLInputElement | HTMLTextAreaElement): void => {
		const length = answerLength(field.value)
		const overflow = Math.min(
			length - (extraSettings.value.lengthMax || answerLengthMax),
			length - answerLength(value.value),
		)
		if (overflow <= 0) {
			return
		}
		const caret = field.selectionStart ?? field.value.length
		const typed = [...field.value.slice(0, caret)]
		const kept = typed.slice(0, Math.max(0, typed.length - overflow)).join('')
		field.value = kept + field.value.slice(caret)
		if (field.selectionStart !== null) {
			field.setSelectionRange(kept.length, kept.length)
		}
	}

	return {
		hasLengthLimit,
		lengthCounter,
		fitLength,
		limitError,
		limitHint,
		showRules,
		toggleRules,
	}
}
