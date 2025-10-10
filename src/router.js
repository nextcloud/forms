/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'
import Vue from 'vue'
import Router from 'vue-router'
import Create from './views/Create.vue'
import Results from './views/Results.vue'
import Submit from './views/Submit.vue'

Vue.use(Router)

export default new Router({
	mode: 'history',

	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	base: generateUrl('/apps/forms', ''),
	linkActiveClass: 'active',

	routes: [
		{
			path: '/',
			name: 'root',
		},
		{
			path: '/:hash',
			redirect: { name: 'submit' },
			name: 'formRoot',
			props: true,
		},
		{
			path: '/:hash/edit',
			components: {
				default: Create,
			},
			name: 'edit',
			props: { default: true },
		},
		{
			path: '/:hash/results',
			components: {
				default: Results,
			},
			name: 'results',
			props: { default: true },
		},
		{
			path: '/:hash/submit/:submissionId?',
			components: {
				default: Submit,
			},
			name: 'submit',
			props: { default: true },
		},
	],
})
