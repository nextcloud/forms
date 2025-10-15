/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { translate, translatePlural } from '@nextcloud/l10n'
import { createApp } from 'vue'
import FormsEmptyContent from './FormsEmptyContent.vue'

import 'vite/modulepreload-polyfill'

const app = createApp(FormsEmptyContent)
app.config.globalProperties.t = translate
app.config.globalProperties.n = translatePlural
app.mount('#content')
