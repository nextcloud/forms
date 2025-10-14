/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommended } from '@nextcloud/eslint-config'
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended'

export default [
	...recommended,
	eslintPluginPrettierRecommended,
	{
		rules: {
			'@stylistic/exp-list-style': 'off',
		},
	},
]
