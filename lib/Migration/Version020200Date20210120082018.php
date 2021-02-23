<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Forms\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add indexes for performance
 */
class Version020200Date20210120082018 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('forms_v2_answers')) {
			$schema->getTable('forms_v2_answers')
			->addIndex(['submission_id'], 'forms_answers_submission')
			->addIndex(['question_id'], 'forms_answers_question');
		}

		if ($schema->hasTable('forms_v2_forms')) {
			$schema->getTable('forms_v2_forms')
			->addIndex(['owner_id'], 'forms_forms_owner')
			->addIndex(['created'], 'forms_forms_created');
		}

		if ($schema->hasTable('forms_v2_options')) {
			$schema->getTable('forms_v2_options')
			->addIndex(['question_id'], 'forms_options_question');
		}

		if ($schema->hasTable('forms_v2_questions')) {
			$schema->getTable('forms_v2_questions')
			->addIndex(['form_id', 'order'], 'forms_questions_form_order')
			->addIndex(['form_id'], 'forms_questions_form');
		}

		if ($schema->hasTable('forms_v2_submissions')) {
			$schema->getTable('forms_v2_submissions')
			->addIndex(['form_id'], 'forms_submissions_form');
		}

		return $schema;
	}
}
