/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { RouteRecordRaw } from 'vue-router'

import { generateUrl } from '@nextcloud/router'
import { createRouter, createWebHistory } from 'vue-router'

const Create = () => import('./views/Create.vue')
const Results = () => import('./views/Results.vue')
const Submit = () => import('./views/Submit.vue')
const EmptyContent = () => import('./FormsEmptyContent.vue')

const routes = [
	{
		path: '/',
		name: 'root',
		components: { default: EmptyContent },
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
		children: [
			{
				path: '',
				name: 'results',
				props: { default: true },
				redirect: (to) => {
					const validViews = ['summary', 'responses']
					const storedView = localStorage.getItem(
						`nextcloud_forms_${to.params.hash as string}_activeResponseView`,
					)
					const lastViewId =
						storedView && validViews.includes(storedView)
							? storedView
							: 'summary'
					return {
						name: `results.${lastViewId}`,
						params: { hash: to.params.hash },
					}
				},
			},
			{
				path: 'summary',
				name: 'results.summary',
				props: { default: true },
				components: { default: Results },
			},
			{
				path: 'responses',
				name: 'results.responses',
				props: { default: true },
				components: { default: Results },
			},
		],
	},
	{
		path: '/:hash/submit/:submissionId?',
		components: { default: Submit },
		name: 'submit',
		props: { default: true },
	},
]

const router = createRouter({
	history: createWebHistory(generateUrl('/apps/forms')),
	linkActiveClass: 'active',
	routes: routes as unknown as RouteRecordRaw[],
})

export default router
