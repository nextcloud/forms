<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Gives the option_type column a 'choice' default and backfills existing
 * rows that were stored without a type. Options created through the API
 * without an explicit optionType previously kept a null type, which the
 * frontend does not render.
 *
 * The column is normally created by Version050300Date20250914000000. That
 * migration being recorded in oc_migrations is not a guarantee that its DDL
 * was applied, so this step recreates the column when it is missing instead
 * of failing the whole upgrade.
 */
class Version050300Date20260716000000 extends SimpleMigrationStep {

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
		$table = $schema->getTable('forms_v2_options');

		if (!$table->hasColumn('option_type')) {
			$table->addColumn('option_type', Types::STRING, [
				'notnull' => false,
				'length' => 255,
				'default' => 'choice',
			]);

			return $schema;
		}

		$column = $table->getColumn('option_type');
		if ($column->getDefault() === null) {
			$column->setDefault('choice');

			return $schema;
		}

		return null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->getTable('forms_v2_options')->hasColumn('option_type')) {
			return;
		}

		$qbUpdate = $this->db->getQueryBuilder();

		$qbUpdate->update('forms_v2_options')
			->set('option_type', $qbUpdate->createNamedParameter('choice'))
			->where($qbUpdate->expr()->isNull('option_type'))
			->executeStatement();
	}
}
