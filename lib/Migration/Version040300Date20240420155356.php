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

/**
 * Add "order" column for options
 */
class Version040300Date20240420155356 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('forms_v2_options');

		// Abort if already existing.
		if ($table->hasColumn('order')) {
			return null;
		}

		// Create new column
		$table->addColumn('order', Types::INTEGER, [
			'notnull' => false,
			'default' => null,
			'unsigned' => true,
		]);

		// Add index for better performance
		$table->addIndex(['question_id', 'order'], 'forms_options_question_order');

		return $schema;
	}
}
