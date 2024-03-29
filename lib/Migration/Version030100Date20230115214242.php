<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
