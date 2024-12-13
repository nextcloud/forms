<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
