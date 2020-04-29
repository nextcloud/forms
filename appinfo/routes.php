<?php
/**
 * @copyright Copyright (c] 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		// Before /{hash} to avoid conflict
		['name' => 'page#createForm', 'url' => '/new', 'verb' => 'GET'],
		['name' => 'page#editForm', 'url' => '/{hash}/edit', 'verb' => 'GET'],
		['name' => 'page#cloneForm', 'url' => '/{hash}/clone', 'verb' => 'GET'],
		['name' => 'page#getResult', 'url' => '/{hash}/results', 'verb' => 'GET'],

		['name' => 'page#goto_form', 'url' => '/{hash}', 'verb' => 'GET'],

		// Forms
		['name' => 'api#getForms', 'url' => '/api/v1/forms', 'verb' => 'GET'],
		['name' => 'api#newForm', 'url' => '/api/v1/form', 'verb' => 'POST'],
		['name' => 'api#getForm', 'url' => '/api/v1/form/{id}', 'verb' => 'GET'],
		['name' => 'api#updateForm', 'url' => '/api/v1/form/update', 'verb' => 'POST'],
		['name' => 'api#deleteForm', 'url' => '/api/v1/form/{id}', 'verb' => 'DELETE'],

		// Questions
		['name' => 'api#newQuestion', 'url' => '/api/v1/question', 'verb' => 'POST'],
		['name' => 'api#updateQuestion', 'url' => '/api/v1/question/update', 'verb' => 'POST'],
		['name' => 'api#reorderQuestions', 'url' => '/api/v1/question/reorder', 'verb' => 'POST'],
		['name' => 'api#deleteQuestion', 'url' => '/api/v1/question/{id}', 'verb' => 'DELETE'],

		// Answers
		['name' => 'api#newOption', 'url' => '/api/v1/option', 'verb' => 'POST'],
		['name' => 'api#updateOption', 'url' => '/api/v1/option/update', 'verb' => 'POST'],
		['name' => 'api#deleteOption', 'url' => '/api/v1/option/{id}', 'verb' => 'DELETE'],

		['name' => 'api#getSubmissions', 'url' => '/api/v1/submissions/{hash}', 'verb' => 'GET'],
		['name' => 'api#insertSubmission', 'url' => '/api/v1/submissions/insert', 'verb' => 'POST'],

		['name' => 'system#get_site_users_and_groups', 'url' => '/get/siteusers', 'verb' => 'POST'],
	]
];
