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
class Version050300Date20250914000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('forms_v2_forms');
		if (!$table->hasColumn('max_submissions')) {
			$table->addColumn('max_submissions', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
				'comment' => 'Maximum number of submissions, null means unlimited',
			]);
		}
		$tableOptions = $schema->getTable('forms_v2_options');
		if (!$tableOptions->hasColumn('option_type')) {
			$tableOptions->addColumn('option_type', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 64,
			]);
		}
		return $schema;
	}
}
