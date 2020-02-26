/* jshint esversion: 6 */
/**
 * @copyright Copyright (c) 2018 René Gieling <github@dartcafe.de>
 *
 * @author René Gieling <github@dartcafe.de>
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

import Vue from 'vue'
import router from './router'
import App from './App.vue'
import VueClipboard from 'vue-clipboard2'

import DatetimePicker from '@nextcloud/vue/dist/Components/DatetimePicker'
import PopoverMenu from '@nextcloud/vue/dist/Components/PopoverMenu'
import Tooltip from '@nextcloud/vue/dist/Components/Tooltip'

import Modal from './plugins/plugin.js'
import Controls from './components/_base-Controls.vue'
import UserDiv from './components/_base-UserDiv.vue'
import SideBar from './components/_base-SideBar.vue'
import SideBarClose from './components/sideBarClose.vue'
import ShareDiv from './components/shareDiv.vue'
import LoadingOverlay from './components/_base-LoadingOverlay.vue'

Vue.component('Controls', Controls)
Vue.component('PopoverMenu', PopoverMenu)
Vue.component('DatePicker', DatetimePicker)
Vue.component('UserDiv', UserDiv)
Vue.component('SideBar', SideBar)
Vue.component('SideBarClose', SideBarClose)
Vue.component('ShareDiv', ShareDiv)
Vue.component('LoadingOverlay', LoadingOverlay)

Vue.directive('tooltip', Tooltip)

Vue.use(VueClipboard)
Vue.use(Modal)

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(OC.requestToken)

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('forms', 'js/')

/* eslint-disable-next-line no-new */
new Vue({
	el: '#app-forms',
	router: router,
	render: h => h(App)
})
