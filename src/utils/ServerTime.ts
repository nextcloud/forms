/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'

const formsAppName = 'forms'
const serverTimeAtLoad = loadState(
	formsAppName,
	'serverTime',
	Math.floor(Date.now() / 1000),
) as number
const monotonicTimeAtLoad = performance.now()

/**
 * Get the current Unix timestamp relative to the server clock.
 *
 * A monotonic timer keeps the value independent of client clock changes after
 * the page has loaded.
 */
export function getCurrentServerTime(): number {
	const elapsedSeconds = (performance.now() - monotonicTimeAtLoad) / 1000
	return Math.floor(serverTimeAtLoad + elapsedSeconds)
}
