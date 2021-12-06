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
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\Share\IShare;

class Version030000Date20211206213004 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	/**
	 * @param IDBConnection $connection
	 */
	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('forms_v2_shares')) {
			$table = $schema->createTable('forms_v2_shares');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('share_type', Types::SMALLINT, [
				'notnull' => true,
			]);
			$table->addColumn('share_with', Types::STRING, [
				'length' => 256,
			]);

			$table->setPrimaryKey(['id'])
				->addIndex(['form_id'], 'forms_shares_form')
				->addIndex(['share_type'], 'forms_shares_type')
				->addIndex(['share_with'], 'forms_shares_with');
		}

		return $schema;
	}

	/**
	 * Migrate the currently active old sharing to the new sharing.
	 * -> 'public' gets mapped to: legacy-link
	 * -> 'registered' mapped to: permit & show to all
	 * -> 'selected' mapped to: selected shares
	 *
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb_fetch = $this->connection->getQueryBuilder();
		$qb_update_access = $this->connection->getQueryBuilder();
		$qb_insert_shares = $this->connection->getQueryBuilder();

		$qb_fetch->select('id', 'access_json')
			->from('forms_v2_forms');
		$cursor = $qb_fetch->execute();

		$qb_update_access->update('forms_v2_forms')
			->set('access_json', $qb_update_access->createParameter('access_json'))
			->where($qb_update_access->expr()->eq('id', $qb_update_access->createParameter('id')));

		$qb_insert_shares->insert('forms_v2_shares');

		while ($row = $cursor->fetch()) {
			// Decode access to array (param assoc=true)
			$access = json_decode($row['access_json'], true);

			switch ($access['type']) {
				case 'public':
					$qb_update_access->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
						->setParameter('access_json', json_encode([
							'permitAllUsers' => false,
							'showToAllUsers' => false,
						]), IQueryBuilder::PARAM_STR);
					$qb_update_access->execute();
					break;

				case 'registered':
					$qb_update_access->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
						->setParameter('access_json', json_encode([
							'permitAllUsers' => true,
							'showToAllUsers' => true,
						]), IQueryBuilder::PARAM_STR);
					$qb_update_access->execute();
					break;

				case 'selected':
					$qb_update_access->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
						->setParameter('access_json', json_encode([
							'permitAllUsers' => false,
							'showToAllUsers' => false,
						]), IQueryBuilder::PARAM_STR);
					$qb_update_access->execute();

					foreach ($access['users'] as $user) {
						$qb_insert_shares->values([
							'form_id' => $qb_insert_shares->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
							'share_type' => $qb_insert_shares->createNamedParameter(IShare::TYPE_USER, IQueryBuilder::PARAM_INT),
							'share_with' => $qb_insert_shares->createNamedParameter($user, IQueryBuilder::PARAM_STR),
						]);
						$qb_insert_shares->execute();
					}
					foreach ($access['groups'] as $group) {
						$qb_insert_shares->values([
							'form_id' => $qb_insert_shares->createNamedParameter($row['id'], IQueryBuilder::PARAM_INT),
							'share_type' => $qb_insert_shares->createNamedParameter(IShare::TYPE_GROUP, IQueryBuilder::PARAM_INT),
							'share_with' => $qb_insert_shares->createNamedParameter($group, IQueryBuilder::PARAM_STR),
						]);
						$qb_insert_shares->execute();
					}
					break;

				default:
					$output->warning('Unknown access property on form. ID: ' . $row['id'], ', access: ' . $row['access_json']);
			}
		}
		$cursor->closeCursor();
	}
}
