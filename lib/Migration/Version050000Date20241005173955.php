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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version050000Date20241005173955 extends SimpleMigrationStep {

	public function __construct(
		protected IDBConnection $db,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qbUpdate = $this->db->getQueryBuilder();

		$qbUpdate->update('forms_v2_forms')
			->set('access_enum', $qbUpdate->func()->subtract(
				'access_enum',
				$qbUpdate->createNamedParameter(3, IQueryBuilder::PARAM_INT)
			))
			->where($qbUpdate->expr()->gte('access_enum', $qbUpdate->createNamedParameter(3, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}
}
