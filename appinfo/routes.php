<?php
/**
 * @copyright Copyright (c] 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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

return [
	'routes' => [
		// Internal views
		['name' => 'page#views', 'url' => '/{hash}/{view}', 'verb' => 'GET'],
		// Share-Link & public submit
		['name' => 'page#goto_form', 'url' => '/{hash}', 'verb' => 'GET'],
		// App Root
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
	],
	'ocs' => [
		// Forms
		['name' => 'api#getForms', 'url' => '/api/v1/forms', 'verb' => 'GET'],
		['name' => 'api#newForm', 'url' => '/api/v1/form', 'verb' => 'POST'],
		['name' => 'api#getForm', 'url' => '/api/v1/form/{id}', 'verb' => 'GET'],
		['name' => 'api#cloneForm', 'url' => '/api/v1/form/clone/{id}', 'verb' => 'POST'],
		['name' => 'api#updateForm', 'url' => '/api/v1/form/update', 'verb' => 'POST'],
		['name' => 'api#deleteForm', 'url' => '/api/v1/form/{id}', 'verb' => 'DELETE'],
		['name' => 'api#getSharedForms', 'url' => '/api/v1/shared_forms', 'verb' => 'GET'],

		// Questions
		['name' => 'api#newQuestion', 'url' => '/api/v1/question', 'verb' => 'POST'],
		['name' => 'api#updateQuestion', 'url' => '/api/v1/question/update', 'verb' => 'POST'],
		['name' => 'api#reorderQuestions', 'url' => '/api/v1/question/reorder', 'verb' => 'POST'],
		['name' => 'api#deleteQuestion', 'url' => '/api/v1/question/{id}', 'verb' => 'DELETE'],

		// Answers
		['name' => 'api#newOption', 'url' => '/api/v1/option', 'verb' => 'POST'],
		['name' => 'api#updateOption', 'url' => '/api/v1/option/update', 'verb' => 'POST'],
		['name' => 'api#deleteOption', 'url' => '/api/v1/option/{id}', 'verb' => 'DELETE'],

		// Submissions
		['name' => 'api#getSubmissions', 'url' => '/api/v1/submissions/{hash}', 'verb' => 'GET'],
		['name' => 'api#exportSubmissions', 'url' => '/api/v1/submissions/export/{hash}', 'verb' => 'GET'],
		['name' => 'api#exportSubmissionsToCloud', 'url' => '/api/v1/submissions/export', 'verb' => 'POST'],
		['name' => 'api#deleteAllSubmissions', 'url' => '/api/v1/submissions/{formId}', 'verb' => 'DELETE'],

		['name' => 'api#insertSubmission', 'url' => '/api/v1/submission/insert', 'verb' => 'POST'],
		['name' => 'api#deleteSubmission', 'url' => '/api/v1/submission/{id}', 'verb' => 'DELETE'],
	]
];
