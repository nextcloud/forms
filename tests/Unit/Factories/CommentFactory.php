<?php
/**
 * @copyright Copyright (c) 2017 Kai Schröer <git@schroeer.co>
 *
 * @author Kai Schröer <git@schroeer.co>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
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

use League\FactoryMuffin\Faker\Facade as Faker;

/**
 * General factory for the comment model.
 */
$fm->define('OCA\Forms\Db\Comment')->setDefinitions([
	'userId' => Faker::firstNameMale(),
	'dt' => function() {
		$date = new DateTime('today');
		return $date->format('Y-m-d H:i:s');
	},
	'comment' => Faker::text(255)
]);
