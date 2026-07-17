/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { RouteLocationNormalizedLoaded, Router } from 'vue-router'

declare module '@vue/runtime-core' {
	interface ComponentCustomProperties {
		$route: RouteLocationNormalizedLoaded
		$router: Router
	}
}
