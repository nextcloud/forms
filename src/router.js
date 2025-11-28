/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'

const Create = () => import('./views/Create.vue')
const Results = () => import('./views/Results.vue')
const Submit = () => import('./views/Submit.vue')

const router = createRouter({
	history: createWebHistory(generateUrl('/apps/forms', '')),
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
			components: { default: Create },
			name: 'edit',
			props: { default: true },
		},
		{
			path: '/:hash/results',
			components: { default: Results },
			name: 'results',
			props: { default: true },
		},
		{
			path: '/:hash/submit/:submissionId?',
			components: { default: Submit },
			name: 'submit',
			props: { default: true },
		},
	],
})

export default router
