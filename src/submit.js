/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'
import FormsSubmitRoot from './FormsSubmit.vue'

import 'vite/modulepreload-polyfill'

const app = createApp(FormsSubmitRoot)

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.mount('#content')

export default app
