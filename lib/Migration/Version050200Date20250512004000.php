<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version050200Date20250512004000 extends SimpleMigrationStep {

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

		if (!$table->hasColumn('locked_by')) {
			$table->addColumn('locked_by', Types::STRING, [
				'notnull' => false,
				'default' => null,
			]);
		}

		if (!$table->hascolumn('locked_until')) {
			$table->addColumn('locked_until', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
				'comment' => 'unix-timestamp',
			]);
		}

		return $schema;
	}
}
