<?php
/**
 * @copyright Copyright (c] 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option] any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'page#goto_form', 'url' => '/form/{hash}', 'verb' => 'GET'],

		['name' => 'page#create_form', 'url' => '/new', 'verb' => 'GET'],
		['name' => 'page#edit_form', 'url' => '/edit/{hash}', 'verb' => 'GET'],
		['name' => 'page#clone_form', 'url' => '/clone/{hash}', 'verb' => 'GET'],
		['name' => 'page#getResult', 'url' => '/results/{id}', 'verb' => 'GET'],

		['name' => 'page#delete_form', 'url' => '/delete', 'verb' => 'POST'],
		['name' => 'page#insert_vote', 'url' => '/insert/vote', 'verb' => 'POST'],
		['name' => 'page#search', 'url' => '/search', 'verb' => 'POST'],
		['name' => 'page#get_display_name', 'url' => '/get/displayname', 'verb' => 'POST'],

		['name' => 'api#write_form', 'url' => '/write/form', 'verb' => 'POST'],
		['name' => 'api#get_form', 'url' => '/get/form/{formIdOrHash}', 'verb' => 'GET'],
		['name' => 'api#get_options', 'url' => '/get/options/{formId}', 'verb' => 'GET'],
		['name' => 'api#get_votes', 'url' => '/get/votes/{formId}', 'verb' => 'GET'],
		['name' => 'api#get_shares', 'url' => '/get/shares/{formId}', 'verb' => 'GET'],
		['name' => 'api#get_event', 'url' => '/get/event/{formId}', 'verb' => 'GET'],
		['name' => 'api#remove_form', 'url' => '/forms/{id}', 'verb' => 'DELETE'],
		['name' => 'api#get_forms', 'url' => '/get/forms', 'verb' => 'GET'],

		['name' => 'system#get_site_users_and_groups', 'url' => '/get/siteusers', 'verb' => 'POST'],
	]
];
