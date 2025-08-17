/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { INPUT_DEBOUNCE_MS } from '../models/Constants.ts'
import debounce from 'debounce'

/**
 *
 * @param {any} initialValue Initial value
 * @param {number} delay delay in milliseconds
 */
export function debouncedProperty(initialValue, delay = INPUT_DEBOUNCE_MS) {
	const observable = Vue.observable({ value: initialValue })

	return {
		get() {
			return observable.value
		},
		set: debounce((newValue) => {
			observable.value = newValue
		}, delay),
	}
}
