<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
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
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20220402100057 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$update_necesssary = false;

		// Change Type of Description from string to text. Necessary due to length restrictions.
		$column = $schema->getTable('forms_v2_forms')->getColumn('description');
		if ($column->getType() === Type::getType(Types::STRING)) {
			$column->setType(Type::getType(Types::TEXT));
			$update_necesssary = true;
		}

		// Change Type of Answer-Text from string to text. Necessary due to length restrictions.
		$column = $schema->getTable('forms_v2_answers')->getColumn('text');
		if ($column->getType() === Type::getType(Types::STRING)) {
			$column->setType(Type::getType(Types::TEXT));
			$update_necesssary = true;
		}

		return $update_necesssary ? $schema : null;
	}
}
