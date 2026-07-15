<?php

declare(strict_types = 1);

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
use Override;

class Version050300Date20260713180000 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	#[Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('forms_v2_uploaded_files')) {
			return null;
		}

		$table = $schema->getTable('forms_v2_uploaded_files');
		$changed = false;

		if (!$table->hasColumn('question_id')) {
			$table->addColumn('question_id', Types::INTEGER, [
				'notnull' => false,
			]);
			$changed = true;
		}

		if (!$table->hasColumn('upload_token')) {
			$table->addColumn('upload_token', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
