<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version030000Date20220402151229 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		$update_necessary = false;

		/*
		 * Remove old (Forms < v2) DB-Tables
		 */
		if ($schema->hasTable('forms_events')) {
			$schema->dropTable('forms_events');
			$update_necessary = true;
		}
		if ($schema->hasTable('forms_questions')) {
			$schema->dropTable('forms_questions');
			$update_necessary = true;
		}
		if ($schema->hasTable('forms_answers')) {
			$schema->dropTable('forms_answers');
			$update_necessary = true;
		}
		if ($schema->hasTable('forms_votes')) {
			$schema->dropTable('forms_votes');
			$update_necessary = true;
		}

		return $update_necessary ? $schema : null;
	}
}
