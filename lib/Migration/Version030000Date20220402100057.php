<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
