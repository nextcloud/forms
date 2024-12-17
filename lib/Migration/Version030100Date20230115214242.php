<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030100Date20230115214242 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$result = $this->ensureColumnIsNullable($schema, 'forms_v2_answers', 'text');
		$result |= $this->ensureColumnIsNullable($schema, 'forms_v2_forms', 'title');
		$result |= $this->ensureColumnIsNullable($schema, 'forms_v2_options', 'text');
		$result |= $this->ensureColumnIsNullable($schema, 'forms_v2_questions', 'order');
		$result |= $this->ensureColumnIsNullable($schema, 'forms_v2_questions', 'text');
		$result |= $this->ensureColumnIsNullable($schema, 'forms_v2_shares', 'share_type');

		return $result ? $schema : null;
	}

	protected function ensureColumnIsNullable(ISchemaWrapper $schema, string $tableName, string $columnName): bool {
		$table = $schema->getTable($tableName);
		$column = $table->getColumn($columnName);

		if ($column->getNotnull()) {
			$column->setNotnull(false);
			return true;
		}

		return false;
	}
}
