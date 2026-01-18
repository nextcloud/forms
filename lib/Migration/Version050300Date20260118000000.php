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
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add parent_question_id and branch_id columns for conditional questions
 */
class Version050300Date20260118000000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$table = $schema->getTable('forms_v2_questions');

		// Add parent_question_id column - references parent conditional question
		if (!$table->hasColumn('parent_question_id')) {
			$table->addColumn('parent_question_id', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
				'comment' => 'ID of parent conditional question, null for top-level questions',
			]);
		}

		// Add branch_id column - identifies which branch this subquestion belongs to
		if (!$table->hasColumn('branch_id')) {
			$table->addColumn('branch_id', Types::STRING, [
				'notnull' => false,
				'length' => 64,
				'default' => null,
				'comment' => 'Branch identifier within parent conditional question',
			]);
		}

		// Add index for efficient lookup of subquestions by parent
		if (!$table->hasIndex('forms_v2_q_parent_idx')) {
			$table->addIndex(['parent_question_id'], 'forms_v2_q_parent_idx');
		}

		return $schema;
	}
}
