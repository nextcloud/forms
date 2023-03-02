<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Christian Hartmann <chris-hartmann@gmx.de>
 *
 * @author Christian Hartmann <chris-hartmann@gmx.de>
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
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030101Date20230302175747 extends SimpleMigrationStep {


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

		if (!$table->hasColumn('color')) {
			$table->addColumn('color', Types::STRING, [
				'notnull' => true,
				'length' => 20,
				'default' => '#ffffff'
			]);
			if (!$table->hasColumn('img')) {
				$table->addColumn('img', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
			}
			return $schema;
		}

		return null;
	}
}
