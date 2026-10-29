<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\Attributes\AddColumn;
use OCP\Migration\Attributes\ColumnType;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Override;

#[AddColumn(table: 'forms_v2_forms', name: 'opens', type: ColumnType::INTEGER, description: 'Timestamp from which a form accepts responses')]
class Version050500Date20261030000000 extends SimpleMigrationStep {
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('forms_v2_forms');

		if ($table->hasColumn('opens')) {
			return null;
		}

		$table->addColumn('opens', Types::INTEGER, [
			'notnull' => true,
			'default' => 0,
			'comment' => 'Timestamp from which the form accepts responses, 0 means immediately',
		]);

		return $schema;
	}
}
