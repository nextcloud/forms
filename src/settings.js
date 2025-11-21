/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'
import FormsSettings from './FormsSettings.vue'

import 'vite/modulepreload-polyfill'

const app = createApp(FormsSettings)

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.mount('#forms-settings')

export default app
