/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateUrl } from '@nextcloud/router'
import { createRouter, createWebHistory } from 'vue-router'
import Create from './views/Create.vue'
import Results from './views/Results.vue'
import Submit from './views/Submit.vue'

const routes = [
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
		component: Create,
		name: 'edit',
		props: true,
	},
	{
		path: '/:hash/results',
		component: Results,
		name: 'results',
		props: true,
	},
	{
		path: '/:hash/submit/:submissionId?',
		component: Submit,
		name: 'submit',
		props: true,
	},
]

export default createRouter({
	history: createWebHistory(generateUrl('/apps/forms', '')),
	linkActiveClass: 'active',
	routes,
})
