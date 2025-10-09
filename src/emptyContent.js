/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { translate, translatePlural } from '@nextcloud/l10n'
import Vue from 'vue'
import FormsEmptyContent from './FormsEmptyContent.vue'

import 'vite/modulepreload-polyfill'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

export default new Vue({
	el: '#content',
	name: 'FormsEmptyContent',
	render: (h) => h(FormsEmptyContent),
})
