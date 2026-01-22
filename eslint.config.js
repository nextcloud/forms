/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommendedVue2 } from '@nextcloud/eslint-config'
import eslintPluginPrettierRecommended from 'eslint-plugin-prettier/recommended'

export default [...recommendedVue2, eslintPluginPrettierRecommended]
