/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Validate a regex, ensures enclosed with delimiters and only supported modifiers by PHP *and* JS
 */
const REGEX_WITH_DELIMITERS = /^\/(.+)\/([smi]{0,3})$/
/**
 * Find unescaped slashes within a string
 */
const REGEX_UNESCAPED_SLASH = /(?:^|[^\\])(?:\\\\)*\//

/**
 * Check if a regex is valid and enclosed with delimiters
 *
 * @param {string} input regular expression
 * @return {boolean}
 */
export function validateExpression(input) {
	// empty regex passes
	if (input.length === 0) {
		return true
	}

	// Validate regex has delimters
	if (!REGEX_WITH_DELIMITERS.test(input)) {
		return false
	}

	// Check pattern is escaped
	const { pattern, modifiers } = splitRegex(input)
	if (REGEX_UNESCAPED_SLASH.test(pattern)) {
		return false
	}

	// Check if regular expression can be compiled
	try {
		;(() => new RegExp(pattern, modifiers))()
		return true
	} catch {
		return false
	}
}

/**
 * Split an enclosed regular expression into pattern and modifiers
 *
 * @param {string} regex regular expression with delimiters
 * @return {{pattern: string, modifiers: string}} pattern and modifiers
 */
export function splitRegex(regex) {
	const [, pattern, modifiers] = regex.match(REGEX_WITH_DELIMITERS) || ['', '', '']
	return { pattern, modifiers }
}
