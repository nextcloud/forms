/**
 * @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
 *
 * @author René Gieling <github@dartcafe.de>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'
import { translate, translatePlural } from '@nextcloud/l10n'
import '@nextcloud/dialogs/styles/toast.scss'

import Vue from 'vue'

import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

import router from './router'
import Forms from './Forms'
import Modal from './plugins/plugin.js'

// TODO: not use global registration
Vue.directive('tooltip', Tooltip)

Vue.use(Modal)

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

// TODO: see if necessary
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('forms', '', 'js/')

/* eslint-disable-next-line no-new */
new Vue({
	el: '#content',
	// eslint-disable-next-line vue/match-component-file-name
	name: 'FormsRoot',
	router,
	render: h => h(Forms),
})
