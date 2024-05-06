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
	} catch (e) {
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
