/* jshint esversion: 6 */
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
import Vue from 'vue'
import Router from 'vue-router'

// Dynamic loading
const Create = () => import('./views/Create')
const List = () => import('./views/List')
const Results = () => import('./views/Results')
Vue.use(Router)

export default new Router({
	mode: 'history',
	base: OC.generateUrl(''),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/:index(index.php/)?apps/forms/',
			components: {
				default: List,
			},
			props: false,
			name: 'list',
		},
		{
			path: '/:index(index.php/)?apps/forms/edit/:hash',
			components: {
				default: Create,
			},
			props: true,
			name: 'edit',
		},
		{
			path: '/:index(index.php/)?apps/forms/results/:hash',
			components: {
				default: Results,
			},
			props: false,
			name: 'results',
		},
		{
			path: '/:index(index.php/)?apps/forms/clone/:hash',
			components: {
				default: Create,
			},
			props: true,
			name: 'clone',
		},
		{
			path: '/:index(index.php/)?apps/forms/new',
			components: {
				default: Create,
			},
			props: false,
			name: 'create',
		},
	],
})
