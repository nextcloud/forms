<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020002Date20200729205932 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forms_v2_forms')) {
			$schema->getTable('forms_v2_forms')
				->changeColumn('description', [
					'length' => 8192,
				]);
		}

		if ($schema->hasTable('forms_v2_answers')) {
			$schema->getTable('forms_v2_answers')
				->changeColumn('text', [
					'length' => 4096,
				]);
		}

		return $schema;
	}
}
