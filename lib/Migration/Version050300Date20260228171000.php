<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
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

class Version050300Date20260228171000 extends SimpleMigrationStep {

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

		$submissionsTable = $schema->getTable('forms_v2_submissions');
		if (!$submissionsTable->hasColumn('is_verified')) {
			$submissionsTable->addColumn('is_verified', Types::BOOLEAN, [
				'notnull' => false,
				'default' => true,
			]);
		}

		if (!$schema->hasTable('forms_v2_submission_verify')) {
			$verificationTable = $schema->createTable('forms_v2_submission_verify');
			$verificationTable->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$verificationTable->addColumn('submission_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$verificationTable->addColumn('recipient_email_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$verificationTable->addColumn('token_hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$verificationTable->addColumn('expires', Types::INTEGER, [
				'notnull' => true,
				'comment' => 'unix-timestamp',
			]);
			$verificationTable->addColumn('used', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
				'comment' => 'unix-timestamp',
			]);

			$verificationTable->setPrimaryKey(['id'], 'forms_subv_id');
			$verificationTable->addUniqueIndex(['submission_id'], 'forms_subv_sub_id');
			$verificationTable->addUniqueIndex(['token_hash'], 'forms_subv_token_hash');
		}

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('forms_v2_submissions')
			->set('is_verified', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->isNull('is_verified'))
			->executeStatement();
	}
}
