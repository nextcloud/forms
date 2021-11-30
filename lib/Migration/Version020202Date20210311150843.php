<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
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
namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020202Date20210311150843 extends SimpleMigrationStep {
	/** @var IDBConnection */
	protected $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * Update old Access-Objects to be full objects containing (empty) user- and group-key.
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$qb_fetch = $this->connection->getQueryBuilder();
		$qb_update = $this->connection->getQueryBuilder();

		$qb_fetch->select('id', 'access_json')
			->from('forms_v2_forms');
		$cursor = $qb_fetch->execute();

		$qb_update->update('forms_v2_forms')
			->set('access_json', $qb_update->createParameter('access_json'))
			->where($qb_update->expr()->eq('id', $qb_update->createParameter('id')));

		while ($row = $cursor->fetch()) {
			// Decode access to array (param assoc=true)
			$access = json_decode($row['access_json'], true);
			$update_necessary = false;

			// Add empty Arrays, if they do not exist
			if (!array_key_exists('users', $access)) {
				$access['users'] = [];
				$update_necessary = true;
			}
			if (!array_key_exists('groups', $access)) {
				$access['groups'] = [];
				$update_necessary = true;
			}

			// If it was necessary, to insert users or groups, update the table
			if ($update_necessary) {
				$qb_update->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
					->setParameter('access_json', json_encode($access), IQueryBuilder::PARAM_STR);
				$qb_update->execute();
			}
		}
		$cursor->closeCursor();
	}
}
