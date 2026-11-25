/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ComputedRef, Ref } from 'vue'

import { n, t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'

export type AnswerLimitSettings = {
	lengthMax?: number
	lengthMin?: number
	numberMax?: number
	numberMin?: number
}

type AnswerLimitKey = keyof AnswerLimitSettings

/**
 * The number of characters of an answer, counting each Unicode code point once
 *
 * @param answer the answer
 */
export function answerLength(answer: string): number {
	return [...answer].length
}

/**
 * The rule of the length limits as a sentence for respondents
 *
 * @param min the minimum number of characters
 * @param max the maximum number of characters
 */
function lengthHint(min?: number, max?: number): string | null {
	if (min && max) {
		return min === max
			? n(
					'forms',
					'Enter exactly %n character',
					'Enter exactly %n characters',
					min,
				)
			: t('forms', 'Enter between {min} and {max} characters', { min, max })
	}
	if (min) {
		return n(
			'forms',
			'Enter at least %n character',
			'Enter at least %n characters',
			min,
		)
	}
	if (max) {
		return n(
			'forms',
			'Enter at most %n character',
			'Enter at most %n characters',
			max,
		)
	}
	return null
}

/**
 * The rule of the number range as a sentence for respondents
 *
 * @param min the lowest number
 * @param max the highest number
 */
function numberHint(min?: number, max?: number): string | null {
	const hasMin = min !== undefined && min !== null
	const hasMax = max !== undefined && max !== null
	if (hasMin && hasMax) {
		return t('forms', 'Enter a number from {min} to {max}', { min, max })
	}
	if (hasMin) {
		return t('forms', 'Enter a number of at least {min}', { min })
	}
	if (hasMax) {
		return t('forms', 'Enter a number of at most {max}', { max })
	}
	return null
}

/**
 * Length limits and number range of a text answer: the hint and counter shown
 * to respondents, the check of an answer and the editor handlers of the limits
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
	const limitHint = computed(
		() =>
			lengthHint(extraSettings.value.lengthMin, extraSettings.value.lengthMax)
			?? numberHint(
				extraSettings.value.numberMin,
				extraSettings.value.numberMax,
			),
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
	 * The hint as error message if the answer breaks a limit, otherwise null
	 *
	 * @param answer the answer to check
	 */
	const limitError = (answer: string): string | null => {
		const { lengthMin, lengthMax, numberMin, numberMax } = extraSettings.value
		if (!answer) {
			return null
		}
		if (
			(lengthMin && answerLength(answer) < lengthMin)
			|| (lengthMax && answerLength(answer) > lengthMax)
		) {
			return limitHint.value
		}
		const number = Number(answer)
		if (
			answer.trim() !== ''
			&& !isNaN(number)
			&& ((numberMin !== undefined && number < numberMin)
				|| (numberMax !== undefined && number > numberMax))
		) {
			return limitHint.value
		}
		return null
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
