import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

const Create = () => import('./views/Create.vue')
const Results = () => import('./views/Results.vue')
const Submit = () => import('./views/Submit.vue')

const router = new Router({
	mode: 'history',
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
