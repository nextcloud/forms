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
 * Add confirmation email fields to forms
 */
class Version050300Date20260413233000 extends SimpleMigrationStep {

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
		$changed = false;

		if (!$table->hasColumn('confirmation_email_enabled')) {
			$table->addColumn('confirmation_email_enabled', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('confirmation_email_subject')) {
			$table->addColumn('confirmation_email_subject', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 255,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('confirmation_email_body')) {
			$table->addColumn('confirmation_email_body', Types::TEXT, [
				'notnull' => false,
				'default' => null,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('confirmation_email_question_id')) {
			$table->addColumn('confirmation_email_question_id', Types::INTEGER, [
				'notnull' => false,
				'default' => null,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
