<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Gives the option_type column a 'choice' default and backfills existing
 * rows that were stored without a type. Options created through the API
 * without an explicit optionType previously kept a null type, which the
 * frontend does not render.
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
		$changed = false;

		if ($table->hasColumn('option_type')) {
			$column = $table->getColumn('option_type');
			if ($column->getDefault() === null) {
				$column->setDefault('choice');
				$changed = true;
			}
		}

		return $changed ? $schema : null;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qbUpdate = $this->db->getQueryBuilder();

		$qbUpdate->update('forms_v2_options')
			->set('option_type', $qbUpdate->createNamedParameter('choice'))
			->where($qbUpdate->expr()->isNull('option_type'))
			->executeStatement();
	}
}
