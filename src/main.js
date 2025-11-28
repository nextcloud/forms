/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { translate, translatePlural } from '@nextcloud/l10n'
import Forms from './Forms.vue'
import router from './router.js'

import 'vite/modulepreload-polyfill'
import '@nextcloud/dialogs/style.css'

const app = createApp(Forms)

app.use(router)

app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural

app.mount('#content')

export default app
