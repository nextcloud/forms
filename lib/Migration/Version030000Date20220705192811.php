<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
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

class Version030000Date20220705192811 extends SimpleMigrationStep {
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
		$table = $schema->getTable('forms_v2_forms');

		if (!$table->hasColumn('submit_multiple')) {
			$table->addColumn('submit_multiple', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);

			return $schema;
		}

		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qbFetch = $this->connection->getQueryBuilder();
		$qbUpdate = $this->connection->getQueryBuilder();

		$qbFetch->select('id', 'submit_once')
			->from('forms_v2_forms');
		$qbUpdate->update('forms_v2_forms')
			->set('submit_multiple', $qbUpdate->createParameter('submit_multiple'))
			->where($qbUpdate->expr()->eq('id', $qbUpdate->createParameter('id')));

		// Fetch Forms and copy inverse submit_once
		$cursor = $qbFetch->executeQuery();
		while ($row = $cursor->fetch()) {
			$qbUpdate->setParameter('id', $row['id'], IQueryBuilder::PARAM_INT)
				->setParameter('submit_multiple', !$row['submit_once'], IQueryBuilder::PARAM_BOOL)
				->executeStatement();
		}
		$cursor->closeCursor();
	}
}
