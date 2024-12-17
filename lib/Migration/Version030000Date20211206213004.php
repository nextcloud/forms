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

		// Abort if already existing.
		if ($schema->hasTable('forms_v2_shares')) {
			return null;
		}

		// Create table
		$table = $schema->createTable('forms_v2_shares');
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'notnull' => true,
		]);
		$table->addColumn('form_id', Types::INTEGER, [
			'notnull' => true,
		]);
		$table->addColumn('share_type', Types::SMALLINT, [
			'notnull' => false,
		]);
		$table->addColumn('share_with', Types::STRING, [
			'length' => 256,
		]);

		$table->setPrimaryKey(['id'])
			->addIndex(['form_id'], 'forms_shares_form')
			->addIndex(['share_type'], 'forms_shares_type')
			->addIndex(['share_with'], 'forms_shares_with');

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
		$qbFetch = $this->connection->getQueryBuilder();
		$qbUpdateAccess = $this->connection->getQueryBuilder();
		$qbInsertShares = $this->connection->getQueryBuilder();

		// Prepare Queries
		$qbFetch->select('id', 'access_json')
			->from('forms_v2_forms');

		$qbUpdateAccess->update('forms_v2_forms')
			->set('access_json', $qbUpdateAccess->createParameter('access_json'))
			->where($qbUpdateAccess->expr()->eq('id', $qbUpdateAccess->createParameter('id')));

		$qbInsertShares->insert('forms_v2_shares')
			->values([
				'form_id' => $qbInsertShares->createParameter('form_id'),
				'share_type' => $qbInsertShares->createParameter('share_type'),
				'share_with' => $qbInsertShares->createParameter('share_with'),
			]);

		// Fetch Forms...
		$cursor = $qbFetch->executeQuery();

		// ... then handle each existing form and translate its sharing settings.
		while ($row = $cursor->fetch()) {
			// Decode access to array (param assoc=true)
			$access = json_decode($row['access_json'], true);

			// In case there are already migrated forms, just skip.
			if (array_key_exists('permitAllUsers', $access)) {
				$output->warning('Already migrated form: ' . $row['id'] . ', access: ' . $row['access_json']);
				continue;
			}

			switch ($access['type']) {
				case 'public':
					$newAccess = [
						'legacyLink' => true,
						'permitAllUsers' => false,
						'showToAllUsers' => false,
					];
					$qbUpdateAccess->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
						->setParameter('access_json', json_encode($newAccess), IQueryBuilder::PARAM_STR)
						->executeStatement();
					break;

				case 'registered':
					$newAccess = [
						'permitAllUsers' => true,
						'showToAllUsers' => true,
					];
					$qbUpdateAccess->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
						->setParameter('access_json', json_encode($newAccess), IQueryBuilder::PARAM_STR)
						->executeStatement();
					break;

				case 'selected':
					$newAccess = [
						'permitAllUsers' => false,
						'showToAllUsers' => false,
					];
					$qbUpdateAccess->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
						->setParameter('access_json', json_encode($newAccess), IQueryBuilder::PARAM_STR)
						->executeStatement();

					// Insert single selected shares.
					foreach ($access['users'] as $user) {
						$qbInsertShares->setParameter('form_id', $row['id'], IQueryBuilder::PARAM_INT)
							->setParameter('share_type', IShare::TYPE_USER, IQueryBuilder::PARAM_INT)
							->setParameter('share_with', $user, IQueryBuilder::PARAM_STR)
							->executeStatement();
					}
					foreach ($access['groups'] as $group) {
						$qbInsertShares->setParameter('form_id', $row['id'], IQueryBuilder::PARAM_INT)
							->setParameter('share_type', IShare::TYPE_GROUP, IQueryBuilder::PARAM_INT)
							->setParameter('share_with', $group, IQueryBuilder::PARAM_STR)
							->executeStatement();
					}
					break;

				default:
					$output->warning('Unknown access property on form. ID: ' . $row['id'] . ', access: ' . $row['access_json']);
			}
		}
		$cursor->closeCursor();
	}
}
