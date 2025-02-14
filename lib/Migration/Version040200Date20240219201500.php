<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
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

/**
 * This migration adds the `state` property to forms for closed, archived or active forms
 */
class Version040200Date20240219201500 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $db,
	) {
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

		if (!$table->hasColumn('state')) {
			$table->addColumn('state', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
		}

		return $schema;
	}

	/**
	 * Set all old forms to active state
	 *
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		$query = $this->db->getQueryBuilder();
		$query->update('forms_v2_forms')
			->set('state', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->executeStatement();
	}
}
