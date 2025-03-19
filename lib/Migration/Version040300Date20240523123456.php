<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version040300Date20240523123456 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$answersTable = $schema->getTable('forms_v2_answers');
		if (!$answersTable->hasColumn('file_id')) {
			$answersTable->addColumn('file_id', Types::BIGINT, [
				'notnull' => false,
				'default' => null,
				'length' => 11,
				'unsigned' => true,
			]);
		}

		if (!$schema->hasTable('forms_v2_uploaded_files')) {
			$table = $schema->createTable('forms_v2_uploaded_files');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('original_file_name', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => false,
				'default' => null,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('created', Types::INTEGER, [
				'notnull' => false,
				'comment' => 'unix-timestamp',
			]);
			$table->setPrimaryKey(['id'], 'forms_upload_files_id');
		}

		return $schema;
	}
}
