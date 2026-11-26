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

export type AnswerLimitKey = 'lengthMax' | 'lengthMin' | 'numberMax' | 'numberMin'

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
	 * Why the value last entered for a limit was not saved, by limit
	 */
	const limitErrors = ref<Partial<Record<AnswerLimitKey, string>>>({})

	/**
	 * A limit value as entered, as whole number for lengths
	 *
	 * @param input the entered value
	 * @param integer whether only whole numbers are allowed
	 */
	const parseLimit = (input: number | string, integer: boolean): number =>
		integer
			? Number.parseInt(input.toString(), 10)
			: Number.parseFloat(input.toString())

	/**
	 * Why a limit cannot be set to a value: a length below 1 or above the stored
	 * answer length, or a minimum above its maximum; null if it can
	 *
	 * @param key the limit
	 * @param value the new value
	 */
	const limitProblem = (key: AnswerLimitKey, value: number): string | null => {
		if (key.startsWith('length') && value < 1) {
			return t('forms', 'Enter a number of at least {min}', { min: 1 })
		}
		if (key.startsWith('length') && value > answerLengthMax) {
			return n(
				'forms',
				'An answer can have at most %n character',
				'An answer can have at most %n characters',
				answerLengthMax,
			)
		}
		const settings = { ...extraSettings.value, [key]: value }
		const [min, max] = key.startsWith('length')
			? [settings.lengthMin, settings.lengthMax]
			: [settings.numberMin, settings.numberMax]
		if (min !== undefined && max !== undefined && min > max) {
			return t('forms', 'The minimum must not be greater than the maximum')
		}
		return null
	}

	/**
	 * Save one limit while it is typed, if the value fits; a value that does
	 * not fit is left unsaved and reported when its field loses focus
	 *
	 * @param key the limit to change
	 * @param input the new value, null to remove the limit; an empty value is not saved
	 * @param integer whether only whole numbers from 1 are allowed
	 * @param onExtraSettingsChange saves changed extra settings of the question
	 */
	const saveLimit = (
		key: AnswerLimitKey,
		input: number | string | null,
		integer: boolean,
		onExtraSettingsChange: (settings: Record<string, unknown>) => void,
	): void => {
		if (input === '') {
			return
		}
		let value: number | undefined
		if (input !== null) {
			value = parseLimit(input, integer)
			if (isNaN(value) || limitProblem(key, value)) {
				return
			}
		}
		limitErrors.value = { ...limitErrors.value, [key]: undefined }
		onExtraSettingsChange({ [key]: value })
	}

	/**
	 * The error state of the editor field of a limit, and the check when the
	 * field loses focus: a value that was not saved is reported and replaced by
	 * the saved one
	 *
	 * @param key the limit
	 */
	const limitFieldState = (key: AnswerLimitKey) => {
		const error = limitErrors.value[key]
		return {
			error: !!error,
			helperText: error,
			'aria-invalid': error ? ('true' as const) : undefined,
			onBlur: (event: FocusEvent): void => {
				const field = event.target as HTMLInputElement
				const saved = extraSettings.value[key]
				const parsed = parseLimit(field.value, key.startsWith('length'))
				if (parsed === saved) {
					return
				}
				const problem = isNaN(parsed) ? null : limitProblem(key, parsed)
				if (problem) {
					limitErrors.value = { ...limitErrors.value, [key]: problem }
				}
				field.value = saved === undefined ? '' : String(saved)
			},
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
		limitFieldState,
		limitHint,
		saveLimit,
	}
}
