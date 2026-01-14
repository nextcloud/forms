/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate, translatePlural } from '@nextcloud/l10n'
import { createApp } from 'vue'
import Forms from './Forms.vue'
import router from './router.js'

import 'vite/modulepreload-polyfill'
import '@nextcloud/dialogs/style.css'

const app = createApp(Forms)

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.use(router)

app.mount('#content')

export default app
