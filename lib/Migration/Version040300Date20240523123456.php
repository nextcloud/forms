<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Kostiantyn Miakshyn <molodchick@gmail.com>
 *
 * @author Kostiantyn Miakshyn <molodchick@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
