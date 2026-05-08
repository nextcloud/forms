/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		emptyContent: resolve(join('src', 'emptyContent.ts')),
		main: resolve(join('src', 'main.ts')),
		submit: resolve(join('src', 'submit.ts')),
		settings: resolve(join('src', 'settings.ts')),
	},
	{
		config: {
			build: {
				cssCodeSplit: false,
				rollupOptions: {
					output: {
						manualChunks: {
							vendor: ['vue', 'vue-router'],
						},
					},
				},
			},
		},
	},
)
