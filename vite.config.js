/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'
import { join, resolve } from 'path'

export default createAppConfig(
	{
		emptyContent: resolve(join('src', 'emptyContent.js')),
		main: resolve(join('src', 'main.js')),
		submit: resolve(join('src', 'submit.js')),
		settings: resolve(join('src', 'settings.js')),
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
				watch: {
					allowInputInsideOutputPath: true,
				},
			},
		},
	},
)
