/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import IconNumeric from '@material-symbols/svg-400/outlined/123.svg?raw'
import IconPhone from '@material-symbols/svg-400/outlined/call.svg?raw'
import IconEMail from '@material-symbols/svg-400/outlined/mail.svg?raw'
import IconRegex from '@material-symbols/svg-400/outlined/regular_expression.svg?raw'
import IconTextShort from '@material-symbols/svg-400/outlined/short_text.svg?raw'
import { translate as t } from '@nextcloud/l10n'

export type ValidationFunction = (
	input: string,
	options?: Record<string, unknown>,
) => boolean

export interface ValidationType {
	label: string
	inputType: string
	errorMessage: string
	createPlaceholder?: string
	submitPlaceholder?: string
	icon: string
	validate: ValidationFunction
}

// !! Keep in SYNC with lib/Constants.php for supported types of input validation !!
const validationTypes: Record<string, ValidationType> = {
	/**
	 * Default, not validated, text input
	 */
	text: {
		icon: IconTextShort,
		inputType: 'text',
		label: t('forms', 'Text'),
		validate: () => true,
		errorMessage: '',
	},

	/**
	 * Phone number validation
	 */
	phone: {
		icon: IconPhone,
		inputType: 'tel',
		label: t('forms', 'Phone number'),
		// Remove common separator symbols, like space or braces, and validate rest are pure numbers
		validate: (input: string) =>
			/^\+?[0-9]{3,}$/.test(input.replace(/[\s()-/x.]/gi, '')),
		errorMessage: t('forms', 'The input is not a valid phone number'),
		createPlaceholder: t('forms', 'People can enter a telephone number'),
		submitPlaceholder: t('forms', 'Enter a telephone number'),
	},

	/**
	 * Email address validation
	 */
	email: {
		icon: IconEMail,
		inputType: 'email',
		label: t('forms', 'Email address'),
		// Simplified email regex as a real one would be too complex, so we validate on backend
		validate: (input: string) => /^[^@]+@[^@]+\.[^.]{2,}$/.test(input),
		errorMessage: t('forms', 'The input is not a valid email address'),
		createPlaceholder: t('forms', 'People can enter an email address'),
		submitPlaceholder: t('forms', 'Enter an email address'),
	},

	/**
	 * Numeric input validation
	 */
	number: {
		icon: IconNumeric,
		inputType: 'number',
		label: t('forms', 'Number'),
		validate: (input: string) =>
			!isNaN(Number(input)) || !isNaN(parseFloat(input)),
		errorMessage: t('forms', 'The input is not a valid number'),
		createPlaceholder: t('forms', 'People can enter a number'),
		submitPlaceholder: t('forms', 'Enter a number'),
	},

	/**
	 * Custom regular expression validation
	 */
	regex: {
		icon: IconRegex,
		inputType: 'text',
		label: t('forms', 'Custom regular expression'),
		validate: (input: string, options?: Record<string, unknown>) => {
			const opts = options || {}
			const pattern = opts.pattern as string
			const modifiers = opts.modifiers as string
			return new RegExp(pattern, modifiers).test(input)
		},
		errorMessage: t('forms', 'The input does not match the required pattern'),
	},
}

export default validationTypes
