import Vue from 'vue'
import { Tooltip } from '@nextcloud/vue'

import router from './router.js'
import Forms from './Forms.vue'
import { translate, translatePlurals } from '@nextcloud/l10n'

import '@nextcloud/dialogs/style.css'

Vue.prototype.t = translate
Vue.prototype.n = translatePlurals

Vue.directive('tooltip', Tooltip)

const app = new Vue({
	router,
	render: (h) => h(Forms),
}).$mount('#content')

export default app
