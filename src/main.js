/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { translate, translatePlural } from '@nextcloud/l10n'
import Tooltip from '@nextcloud/vue/directives/Tooltip'
import Vue from 'vue'
import Forms from './Forms.vue'
import router from './router.js'

import 'vite/modulepreload-polyfill'
import '@nextcloud/dialogs/style.css'

Vue.directive('tooltip', Tooltip)

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

export default new Vue({
	el: '#content',
	name: 'FormsRoot',
	router,
	render: (h) => h(Forms),
})
