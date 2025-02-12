<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
	 *
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return void
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$qb_fetch = $this->connection->getQueryBuilder();
		$qb_update = $this->connection->getQueryBuilder();

		$qb_fetch->select('id', 'access_json')
			->from('forms_v2_forms');
		$cursor = $qb_fetch->executeQuery();

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
				$qb_update->executeStatement();
			}
		}
		$cursor->closeCursor();
	}
}
