<?php
/**
 * @copyright Copyright (c] 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
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

return [
	'routes' => [
		// Internal views
		[
			'name' => 'page#views',
			'url' => '/{hash}/{view}',
			'verb' => 'GET'
		],
		// Share-Link & public submit
		[
			'name' => 'page#goto_form',
			'url' => '/{hash}',
			'verb' => 'GET'
		],
		// App Root
		[
			'name' => 'page#index',
			'url' => '/',
			'verb' => 'GET'
		],
	],
	'ocs' => [
		
		// Forms
		[
			'name' => 'api#getForms',
			'url' => '/api/{apiVersion}/forms',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#newForm',
			'url' => '/api/{apiVersion}/form',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#getForm',
			'url' => '/api/{apiVersion}/form/{id}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#cloneForm',
			'url' => '/api/{apiVersion}/form/clone/{id}',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#updateForm',
			'url' => '/api/{apiVersion}/form/update',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#deleteForm',
			'url' => '/api/{apiVersion}/form/{id}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#getSharedForms',
			'url' => '/api/{apiVersion}/shared_forms',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],

		// Questions
		[
			'name' => 'api#newQuestion',
			'url' => '/api/{apiVersion}/question',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#updateQuestion',
			'url' => '/api/{apiVersion}/question/update',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#reorderQuestions',
			'url' => '/api/{apiVersion}/question/reorder',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#deleteQuestion',
			'url' => '/api/{apiVersion}/question/{id}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],

		// Options
		[
			'name' => 'api#newOption',
			'url' => '/api/{apiVersion}/option',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#updateOption',
			'url' => '/api/{apiVersion}/option/update',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#deleteOption',
			'url' => '/api/{apiVersion}/option/{id}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],

		// Shares
		[
			'name' => 'shareApi#newShare',
			'url' => '/api/{apiVersion}/share',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v2'
			]
		],
		[
			'name' => 'shareApi#deleteShare',
			'url' => '/api/{apiVersion}/share/{id}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v2'
			]
		],

		// Submissions
		[
			'name' => 'api#getSubmissions',
			'url' => '/api/{apiVersion}/submissions/{hash}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#exportSubmissions',
			'url' => '/api/{apiVersion}/submissions/export/{hash}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#exportSubmissionsToCloud',
			'url' => '/api/{apiVersion}/submissions/export',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#deleteAllSubmissions',
			'url' => '/api/{apiVersion}/submissions/{formId}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#insertSubmission',
			'url' => '/api/{apiVersion}/submission/insert',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
		[
			'name' => 'api#deleteSubmission',
			'url' => '/api/{apiVersion}/submission/{id}',
			'verb' => 'DELETE',
			'requirements' => [
				'apiVersion' => 'v1(\.1)?'
			]
		],
	]
];
