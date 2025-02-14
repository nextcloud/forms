<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Migration;

use DateTime;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;

use OCP\Migration\SimpleMigrationStep;

/**
 * Installation class for the forms app.
 * Initial db creation
 */
class Version010200Date20200323141300 extends SimpleMigrationStep {

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/** Map of questionTypes to change */
	private $questionTypeMap = [
		'radiogroup' => 'multiple_unique',
		'checkbox' => 'multiple',
		'text' => 'short',
		'comment' => 'long',
		'dropdown' => 'multiple_unique'
	];

	/**
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $connection, IConfig $config) {
		$this->connection = $connection;
		$this->config = $config;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('forms_v2_forms')) {
			$table = $schema->createTable('forms_v2_forms');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('hash', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('title', Types::STRING, [
				'notnull' => false,
				'length' => 256,
			]);
			$table->addColumn('description', Types::TEXT, [
				'notnull' => false,
				'length' => 8192,
			]);
			$table->addColumn('owner_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('access_json', Types::JSON, [
				'notnull' => false,
			]);
			$table->addColumn('created', Types::INTEGER, [
				'notnull' => false,
				'comment' => 'unix-timestamp',
			]);
			$table->addColumn('expires', Types::INTEGER, [
				'notnull' => false,
				'default' => 0,
				'comment' => 'unix-timestamp',
			]);
			$table->addColumn('is_anonymous', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('submit_once', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['hash'], 'uniqueHash');
		}

		if (!$schema->hasTable('forms_v2_questions')) {
			$table = $schema->createTable('forms_v2_questions');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('order', Types::INTEGER, [
				'notnull' => false,
				'default' => 1,
			]);
			$table->addColumn('type', Types::STRING, [
				'notnull' => true,
				'length' => 256,
			]);
			$table->addColumn('mandatory', Types::BOOLEAN, [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('text', Types::STRING, [
				'notnull' => false,
				'length' => 2048,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('forms_v2_options')) {
			$table = $schema->createTable('forms_v2_options');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('question_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('text', Types::STRING, [
				'notnull' => false,
				'length' => 1024,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('forms_v2_submissions')) {
			$table = $schema->createTable('forms_v2_submissions');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('form_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('timestamp', Types::INTEGER, [
				'notnull' => false,
				'comment' => 'unix-timestamp',
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('forms_v2_answers')) {
			$table = $schema->createTable('forms_v2_answers');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('submission_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('question_id', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('text', Types::TEXT, [
				'notnull' => false,
				'length' => 4096,
			]);
			$table->setPrimaryKey(['id']);
		}

		return $schema;
	}

	/**
	 * @return void
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// if Database exists.
		if ($schema->hasTable('forms_events')) {
			$id_mapping = [];
			$id_mapping['events'] = []; // Maps oldevent-id => ['newId' => newevent-id, 'nextQuestionOrder' => integer]
			$id_mapping['questions'] = []; // Maps oldquestion-id => ['newId' => newquestion-id]
			$id_mapping['currentSubmission'] = 0;

			//Fetch & Restore Events
			$qb_fetch = $this->connection->getQueryBuilder();
			$qb_restore = $this->connection->getQueryBuilder();

			$qb_fetch->select('id', 'hash', 'title', 'description', 'owner', 'created', 'access', 'expire', 'is_anonymous', 'unique')
				->from('forms_events');
			$cursor = $qb_fetch->executeQuery();
			while ($event = $cursor->fetch()) {
				$newAccessJSON = $this->convertAccessList($event['access']);

				$qb_restore->insert('forms_v2_forms')
					->values([
						'hash' => $qb_restore->createNamedParameter($event['hash'], IQueryBuilder::PARAM_STR),
						'title' => $qb_restore->createNamedParameter($event['title'], IQueryBuilder::PARAM_STR),
						'description' => $qb_restore->createNamedParameter($event['description'], IQueryBuilder::PARAM_STR),
						'owner_id' => $qb_restore->createNamedParameter($event['owner'], IQueryBuilder::PARAM_STR),
						'access_json' => $qb_restore->createNamedParameter($newAccessJSON, IQueryBuilder::PARAM_STR),
						'created' => $qb_restore->createNamedParameter($this->convertDateTime($event['created']), IQueryBuilder::PARAM_INT),
						'expires' => $qb_restore->createNamedParameter($this->convertDateTime($event['expire']), IQueryBuilder::PARAM_INT),
						'is_anonymous' => $qb_restore->createNamedParameter($event['is_anonymous'], IQueryBuilder::PARAM_BOOL),
						'submit_once' => $qb_restore->createNamedParameter($event['unique'], IQueryBuilder::PARAM_BOOL)
					]);
				$qb_restore->executeStatement();
				$id_mapping['events'][$event['id']] = [
					'newId' => $qb_restore->getLastInsertId(), //Store new form-id to connect questions to new form.
					'nextQuestionOrder' => 1 //Prepare for sorting questions
				];
			}
			$cursor->closeCursor();

			//Fetch & restore Questions
			$qb_fetch = $this->connection->getQueryBuilder();
			$qb_restore = $this->connection->getQueryBuilder();

			$qb_fetch->select('id', 'form_id', 'form_question_type', 'form_question_text')
				->from('forms_questions');
			$cursor = $qb_fetch->executeQuery();
			while ($question = $cursor->fetch()) {
				//In case the old Question would have been longer than current possible length, create a warning and shorten text to avoid Error on upgrade.
				if (strlen($question['form_question_text']) > 2048) {
					$output->warning("Question-text is too long for new Database: '" . $question['form_question_text'] . "'");
					$question['form_question_text'] = mb_substr($question['form_question_text'], 0, 2048);
				}

				$qb_restore->insert('forms_v2_questions')
					->values([
						'form_id' => $qb_restore->createNamedParameter($id_mapping['events'][$question['form_id']]['newId'], IQueryBuilder::PARAM_INT),
						'order' => $qb_restore->createNamedParameter($id_mapping['events'][$question['form_id']]['nextQuestionOrder']++, IQueryBuilder::PARAM_INT),
						'type' => $qb_restore->createNamedParameter($this->questionTypeMap[$question['form_question_type']], IQueryBuilder::PARAM_STR),
						'text' => $qb_restore->createNamedParameter($question['form_question_text'], IQueryBuilder::PARAM_STR)
					]);
				$qb_restore->executeStatement();
				$id_mapping['questions'][$question['id']]['newId'] = $qb_restore->getLastInsertId(); //Store new question-id to connect options to new question.
			}
			$cursor->closeCursor();

			//Fetch all Answers and restore to Options
			$qb_fetch = $this->connection->getQueryBuilder();
			$qb_restore = $this->connection->getQueryBuilder();

			$qb_fetch->select('question_id', 'text')
				->from('forms_answers');
			$cursor = $qb_fetch->executeQuery();
			while ($answer = $cursor->fetch()) {
				//In case the old Answer would have been longer than current possible length, create a warning and shorten text to avoid Error on upgrade.
				if (strlen($answer['text']) > 1024) {
					$output->warning("Option-text is too long for new Database: '" . $answer['text'] . "'");
					$answer['text'] = mb_substr($answer['text'], 0, 1024);
				}

				$qb_restore->insert('forms_v2_options')
					->values([
						'question_id' => $qb_restore->createNamedParameter($id_mapping['questions'][$answer['question_id']]['newId'], IQueryBuilder::PARAM_INT),
						'text' => $qb_restore->createNamedParameter($answer['text'], IQueryBuilder::PARAM_STR)
					]);
				$qb_restore->executeStatement();
			}
			$cursor->closeCursor();

			/* Fetch old id_structure of event-ids and question-ids
			 * This is necessary to restore the $oldQuestionId, as the vote_option_ids do not use the true question_ids
			 */
			$event_structure = [];
			$qb_fetch = $this->connection->getQueryBuilder();
			$qb_fetch->select('id')
				->from('forms_events');
			$cursor = $qb_fetch->executeQuery();
			while ($tmp = $cursor->fetch()) {
				$event_structure[$tmp['id']] = $tmp;
			}
			$cursor->closeCursor();

			foreach ($event_structure as $eventkey => $event) {
				$event_structure[$eventkey]['questions'] = [];
				$qb_fetch = $this->connection->getQueryBuilder();
				$qb_fetch->select('id', 'form_question_text')
					->from('forms_questions')
					->where($qb_fetch->expr()->eq('form_id', $qb_fetch->createNamedParameter($event['id'], IQueryBuilder::PARAM_INT)));
				$cursor = $qb_fetch->executeQuery();
				while ($tmp = $cursor->fetch()) {
					$event_structure[$event['id']]['questions'][] = $tmp;
				}
				$cursor->closeCursor();
			}

			//Fetch Votes and restore to Submissions & Answers
			$qb_fetch = $this->connection->getQueryBuilder();
			$qb_restore = $this->connection->getQueryBuilder();
			//initialize $last_vote
			$last_vote = [];
			$last_vote['form_id'] = 0;
			$last_vote['user_id'] = '';
			$last_vote['vote_option_id'] = 0;

			$qb_fetch->select('id', 'form_id', 'user_id', 'vote_option_id', 'vote_option_text', 'vote_answer')
				->from('forms_votes');
			$cursor = $qb_fetch->executeQuery();
			while ($vote = $cursor->fetch()) {
				//If the form changed, if the user changed or if vote_option_id became smaller than last one, then a new submission is interpreted.
				if (($vote['form_id'] !== $last_vote['form_id']) || ($vote['user_id'] !== $last_vote['user_id']) || ($vote['vote_option_id'] < $last_vote['vote_option_id'])) {
					$qb_restore->insert('forms_v2_submissions')
						->values([
							'form_id' => $qb_restore->createNamedParameter($id_mapping['events'][$vote['form_id']]['newId'], IQueryBuilder::PARAM_INT),
							'user_id' => $qb_restore->createNamedParameter($vote['user_id'], IQueryBuilder::PARAM_STR),
							'timestamp' => $qb_restore->createNamedParameter(time(), IQueryBuilder::PARAM_STR) //Information not available. Just using Migration-Timestamp.
						]);
					$qb_restore->executeStatement();
					$id_mapping['currentSubmission'] = $qb_restore->getLastInsertId(); //Store submission-id to connect answers to submission.
				}
				$last_vote = $vote;

				//In case the old Answer would have been longer than current possible length, create a warning and shorten text to avoid Error on upgrade.
				if (strlen($vote['vote_answer']) > 4096) {
					$output->warning("Answer-text is too long for new Database: '" . $vote['vote_answer'] . "'");
					$vote['vote_answer'] = mb_substr($vote['vote_answer'], 0, 4096);
				}

				/* Due to the unconventional storing fo vote_option_ids, the vote_option_id needs to get mapped onto old question-id and from there to new question-id.
				 * vote_option_ids count from 1 to x for the questions of a form. So the question at point [$vote[vote_option_id] - 1] within the id-structure is the corresponding question.
				 */
				$oldQuestionId = $event_structure[$vote['form_id']]['questions'][$vote['vote_option_id'] - 1]['id'];
				//Just throw an Error, if aboves QuestionId-Mapping went wrong. Double-Checked by Question-Text.
				if ($event_structure[$vote['form_id']]['questions'][$vote['vote_option_id'] - 1]['form_question_text'] !== $vote['vote_option_text']) {
					$output->warning("Some Question-Mapping went wrong within Submission-Mapping to new Database. On 'vote_id': " . $vote['id'] . " - 'vote_option_text': '" . $vote['vote_option_text'] . "'");
				}

				$qb_restore->insert('forms_v2_answers')
					->values([
						'submission_id' => $qb_restore->createNamedParameter($id_mapping['currentSubmission'], IQueryBuilder::PARAM_INT),
						'question_id' => $qb_restore->createNamedParameter($id_mapping['questions'][$oldQuestionId]['newId'], IQueryBuilder::PARAM_STR),
						'text' => $qb_restore->createNamedParameter($vote['vote_answer'], IQueryBuilder::PARAM_STR)
					]);
				$qb_restore->executeStatement();
			}
		}
	}

	/**
	 * Convert old Access-String into JSON of new Access-Structure.
	 * @param $accessString Old access-String
	 */
	private function convertAccessList($accessString) : string {
		$accessArray = [];

		if ($accessString === 'public' || $accessString === 'registered') {
			// Store type and return with empty users/groups.
			$accessArray['type'] = $accessString;
			return json_encode($accessArray);
		}

		// Access 'selected'
		$accessArray['type'] = 'selected';
		$accessArray['users'] = [];
		$accessArray['groups'] = [];

		$stringExplode = explode(';', $accessString);
		foreach ($stringExplode as $string) {
			if (strpos($string, 'user_') === 0) {
				$accessArray['users'][] = substr($string, 5);
			} elseif (strpos($string, 'group_') === 0) {
				$accessArray['groups'][] = substr($string, 6);
			}
		}

		return json_encode($accessArray);
	}

	/** Convert old Date-Format to unix-timestamps */
	private function convertDateTime($oldDate): int {
		// Expires can be NULL -> Converting to timestamp 0
		if (!$oldDate) {
			return 0;
		}

		return DateTime::createFromFormat('Y-m-d H:i:s', $oldDate)->getTimestamp();
	}
}
