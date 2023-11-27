/**
 * @copyright Copyright (c) 2023 Ferdinand Thiessen <rpm@fthiessen.de>
 *
 * @author Ferdinand Thiessen <rpm@fthiessen.de>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { translate as t } from '@nextcloud/l10n'

import IconEMail from 'vue-material-design-icons/Email.vue'
import IconPhone from 'vue-material-design-icons/Phone.vue'
import IconRegex from 'vue-material-design-icons/Regex.vue'
import IconTextShort from 'vue-material-design-icons/TextShort.vue'
import IconNumeric from 'vue-material-design-icons/Numeric.vue'

/**
 * @callback ValidationFunction
 * @param {string} input User input text
 * @param {?Record<any>} options Optional setting for validation, like regex pattern.
 * @return {boolean} True if the input is valid, false otherwise
 */
/**
 * @typedef {object} ValidationType
 * @property {string} label The validation-type label, that users will see.
 * @property {string} inputType The HTML <input> type used.
 * @property {string} errorMessage The error message shown if the validation fails.
 * @property {string|undefined} createPlaceholder *optional* A typed placeholder that is visible in edit-mode, to indicate a submission form-input field
 * @property {string|undefined} submitPlaceholder *optional* A typed placeholder that is visible in submit-mode, to indicate a form input-field
 * @property {import('vue').Component} icon The icon users will see on the input field.
 * @property {ValidationFunction} validate Function for validating user input to match the selected input type.
 */

// !! Keep in SYNC with lib/Constants.php for supported types of input validation !!
export default {
	/**
	 * Default, not validated, text input
	 *
	 * @type {ValidationType}
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
	 *
	 * @type {ValidationType}
	 */
	phone: {
		icon: IconPhone,
		inputType: 'tel',
		label: t('forms', 'Phone number'),
		// Remove common separator symbols, like space or braces, and validate rest are pure numbers
		validate: (input) => /^\+?[0-9]{3,}$/.test(input.replace(/[\s()-/x.]/ig, '')),
		errorMessage: t('forms', 'The input is not a valid phone number'),
		createPlaceholder: t('forms', 'People can enter a telephone number'),
		submitPlaceholder: t('forms', 'Enter a telephone number'),
	},

	/**
	 * Email address validation
	 *
	 * @type {ValidationType}
	 */
	email: {
		icon: IconEMail,
		inputType: 'email',
		label: t('forms', 'Email address'),
		// Simplified email regex as a real one would be too complex, so we validate on backend
		validate: (input) => /^[^@]+@[^@]+\.[^.]{2,}$/.test(input),
		errorMessage: t('forms', 'The input is not a valid email address'),
		createPlaceholder: t('forms', 'People can enter an email address'),
		submitPlaceholder: t('forms', 'Enter an email address'),
	},

	/**
	 * Numeric input validation
	 *
	 * @type {ValidationType}
	 */
	number: {
		icon: IconNumeric,
		inputType: 'number',
		label: t('forms', 'Number'),
		validate: (input) => !isNaN(input) || !isNaN(parseFloat(input)),
		errorMessage: t('forms', 'The input is not a valid number'),
		createPlaceholder: t('forms', 'People can enter a number'),
		submitPlaceholder: t('forms', 'Enter a number'),
	},

	/**
	 * Custom regular expression validation
	 *
	 * @type {ValidationType}
	 */
	regex: {
		icon: IconRegex,
		inputType: 'text',
		label: t('forms', 'Custom regular expression'),
		validate: (input, { pattern, modifiers }) => (new RegExp(pattern, modifiers)).test(input),
		errorMessage: t('forms', 'The input does not match the required pattern'),
	},
}
