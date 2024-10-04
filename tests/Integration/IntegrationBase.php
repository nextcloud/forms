<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
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
namespace OCA\Forms\Tests\Integration;

use OCP\DB\QueryBuilder\IQueryBuilder;
use Test\TestCase;

/**
 * @group DB
 */
class IntegrationBase extends TestCase {
	/** @var Array */
	protected $testForms;

	/**
	 * Users that are needed by this test case
	 * @var array<string,string>
	 */
	protected array $users;

	/**
	 * Set up test environment.
	 * Writing testforms into db, preparing http request
	 */
	public function setUp(): void {
		parent::setUp();

		$userManager = \OC::$server->getUserManager();
		foreach ($this->users as $userId => $displayName) {
			$user = $userManager->get($userId);
			if ($user === null) {
				$user = $userManager->createUser($userId, $userId);
			}
			$user->setDisplayName($displayName);
		}

		$qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();

		// Write our test forms into db
		foreach ($this->testForms as $index => $form) {
			$qb->insert('forms_v2_forms')
				->values([
					'hash' => $qb->createNamedParameter($form['hash'], IQueryBuilder::PARAM_STR),
					'title' => $qb->createNamedParameter($form['title'], IQueryBuilder::PARAM_STR),
					'description' => $qb->createNamedParameter($form['description'], IQueryBuilder::PARAM_STR),
					'owner_id' => $qb->createNamedParameter($form['owner_id'], IQueryBuilder::PARAM_STR),
					'access_enum' => $qb->createNamedParameter($form['access_enum'], IQueryBuilder::PARAM_INT),
					'created' => $qb->createNamedParameter($form['created'], IQueryBuilder::PARAM_INT),
					'expires' => $qb->createNamedParameter($form['expires'], IQueryBuilder::PARAM_INT),
					'state' => $qb->createNamedParameter($form['state'], IQueryBuilder::PARAM_INT),
					'is_anonymous' => $qb->createNamedParameter($form['is_anonymous'], IQueryBuilder::PARAM_BOOL),
					'submit_multiple' => $qb->createNamedParameter($form['submit_multiple'], IQueryBuilder::PARAM_BOOL),
					'show_expiration' => $qb->createNamedParameter($form['show_expiration'], IQueryBuilder::PARAM_BOOL),
					'last_updated' => $qb->createNamedParameter($form['last_updated'], IQueryBuilder::PARAM_INT),
					'submission_message' => $qb->createNamedParameter($form['submission_message'], IQueryBuilder::PARAM_STR),
					'file_id' => $qb->createNamedParameter($form['file_id'], IQueryBuilder::PARAM_INT),
					'file_format' => $qb->createNamedParameter($form['file_format'], IQueryBuilder::PARAM_STR),
				]);
			$qb->executeStatement();
			$formId = $qb->getLastInsertId();
			$this->testForms[$index]['id'] = $formId;

			// Insert Questions into DB
			foreach (($form['questions'] ?? []) as $qIndex => $question) {
				$qb->insert('forms_v2_questions')
					->values([
						'form_id' => $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT),
						'order' => $qb->createNamedParameter($question['order'], IQueryBuilder::PARAM_INT),
						'type' => $qb->createNamedParameter($question['type'], IQueryBuilder::PARAM_STR),
						'is_required' => $qb->createNamedParameter($question['isRequired'], IQueryBuilder::PARAM_BOOL),
						'text' => $qb->createNamedParameter($question['text'], IQueryBuilder::PARAM_STR),
						'name' => $qb->createNamedParameter($question['name'], IQueryBuilder::PARAM_STR),
						'description' => $qb->createNamedParameter($question['description'], IQueryBuilder::PARAM_STR),
						'extra_settings_json' => $qb->createNamedParameter(json_encode($question['extraSettings']), IQueryBuilder::PARAM_STR),
					]);
				$qb->executeStatement();
				$questionId = $qb->getLastInsertId();
				$this->testForms[$index]['questions'][$qIndex]['id'] = $questionId;

				// Insert Options into DB
				foreach ($question['options'] as $oIndex => $option) {
					$qb->insert('forms_v2_options')
						->values([
							'question_id' => $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT),
							'text' => $qb->createNamedParameter($option['text'], IQueryBuilder::PARAM_STR),
							'order' => $qb->createNamedParameter($option['order'], IQueryBuilder::PARAM_INT)
						]);
					$qb->executeStatement();
					$this->testForms[$index]['questions'][$qIndex]['options'][$oIndex]['id'] = $qb->getLastInsertId();
				}
			}

			// Insert Shares into DB
			foreach ($form['shares'] as $sIndex => $share) {
				$qb->insert('forms_v2_shares')
					->values([
						'form_id' => $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT),
						'share_type' => $qb->createNamedParameter($share['shareType'], IQueryBuilder::PARAM_STR),
						'share_with' => $qb->createNamedParameter($share['shareWith'], IQueryBuilder::PARAM_STR),
						'permissions_json' => $qb->createNamedParameter(json_encode($share['permissions'] ?? null), IQueryBuilder::PARAM_STR),
					]);
				$qb->executeStatement();
				$this->testForms[$index]['shares'][$sIndex]['id'] = $qb->getLastInsertId();
			}

			// Insert Submissions into DB
			foreach (($form['submissions'] ?? []) as $suIndex => $submission) {
				$qb->insert('forms_v2_submissions')
					->values([
						'form_id' => $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT),
						'user_id' => $qb->createNamedParameter($submission['userId'], IQueryBuilder::PARAM_STR),
						'timestamp' => $qb->createNamedParameter($submission['timestamp'], IQueryBuilder::PARAM_INT)
					]);
				$qb->executeStatement();
				$submissionId = $qb->getLastInsertId();
				$this->testForms[$index]['submissions'][$suIndex]['id'] = $submissionId;

				foreach ($submission['answers'] as $aIndex => $answer) {
					$qb->insert('forms_v2_answers')
						->values([
							'submission_id' => $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT),
							'question_id' => $qb->createNamedParameter($this->testForms[$index]['questions'][$answer['questionIndex']]['id'], IQueryBuilder::PARAM_INT),
							'text' => $qb->createNamedParameter($answer['text'], IQueryBuilder::PARAM_STR)
						]);
					$qb->executeStatement();
					$this->testForms[$index]['submissions'][$suIndex]['answers'][$aIndex]['id'] = $qb->getLastInsertId();
				}
			}
		}
	}

	/** Clean up database from testforms */
	public function tearDown(): void {
		$qb = \OC::$server->getDatabaseConnection()->getQueryBuilder();

		foreach ($this->testForms as $form) {
			$qb->delete('forms_v2_forms')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($form['id'], IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			foreach ($form['questions'] as $question) {
				$qb->delete('forms_v2_questions')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($question['id'], IQueryBuilder::PARAM_INT)));
				$qb->executeStatement();

				foreach ($question['options'] as $option) {
					$qb->delete('forms_v2_options')
						->where($qb->expr()->eq('id', $qb->createNamedParameter($option['id'], IQueryBuilder::PARAM_INT)));
					$qb->executeStatement();
				}
			}

			foreach ($form['shares'] as $share) {
				$qb->delete('forms_v2_shares')
					->where($qb->expr()->eq('id', $qb->createNamedParameter($share['id'], IQueryBuilder::PARAM_INT)));
				$qb->executeStatement();
			}

			if (isset($form['submissions'])) {
				foreach ($form['submissions'] as $submission) {
					$qb->delete('forms_v2_submissions')
						->where($qb->expr()->eq('id', $qb->createNamedParameter($submission['id'], IQueryBuilder::PARAM_INT)));
					$qb->executeStatement();

					foreach ($submission['answers'] as $answer) {
						$qb->delete('forms_v2_answers')
							->where($qb->expr()->eq('id', $qb->createNamedParameter($answer['id'], IQueryBuilder::PARAM_INT)));
						$qb->executeStatement();
					}
				}
			}
		}

		parent::tearDown();
	}
};
