<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Integration\DB;

use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Tests\Integration\IntegrationBase;
use OCP\IDBConnection;

/**
 * @group DB
 */
class SubmissionMapperTest extends IntegrationBase {
	/** @var SubmissionMapper */
	private $submissionMapper;

	protected array $users = [
		'test' => 'Test user',
		'user1' => 'User One',
		'user2' => 'User Two',
	];

	private function setTestForms() {
		$this->testForms = [
			[
				'hash' => 'test_form_1',
				'title' => 'Test Form 1',
				'description' => 'Form for submission testing',
				'owner_id' => 'test',
				'access_enum' => 0,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => true,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => '',
				'file_id' => null,
				'file_format' => null,
				'questions' => [
					[
						'type' => 'short',
						'text' => 'First Question?',
						'isRequired' => true,
						'name' => '',
						'order' => 1,
						'options' => [],
						'accept' => [],
						'description' => 'Please answer this.',
						'extraSettings' => []
					]
				],
				'shares' => [],
				'submissions' => [
					[
						'userId' => 'user1',
						'timestamp' => 100000,
						'answers' => [
							[
								'questionIndex' => 0,
								'text' => 'Answer 1'
							]
						]
					],
					[
						'userId' => 'user1',
						'timestamp' => 100001,
						'answers' => [
							[
								'questionIndex' => 0,
								'text' => 'Answer 2'
							]
						]
					],
					[
						'userId' => 'user2',
						'timestamp' => 100002,
						'answers' => [
							[
								'questionIndex' => 0,
								'text' => 'Search term'
							]
						]
					]
				]
			],
			[
				'hash' => 'test_form_2',
				'title' => 'Test Form 2',
				'description' => 'Empty form',
				'owner_id' => 'test',
				'access_enum' => 0,
				'created' => 12345,
				'expires' => 0,
				'state' => 0,
				'is_anonymous' => false,
				'submit_multiple' => false,
				'show_expiration' => false,
				'last_updated' => 123456789,
				'submission_message' => '',
				'file_id' => null,
				'file_format' => null,
				'questions' => [],
				'shares' => [],
				'submissions' => []
			]
		];
	}

	public function setUp(): void {
		$this->setTestForms();
		parent::setUp();

		$db = \OCP\Server::get(IDBConnection::class);
		$answerMapper = \OCP\Server::get(\OCA\Forms\Db\AnswerMapper::class);
		$this->submissionMapper = new SubmissionMapper($db, $answerMapper);
	}

	public function testFindByFormBasic(): void {
		$submissions = $this->submissionMapper->findByForm($this->testForms[0]['id']);

		$this->assertCount(3, $submissions);
		$this->assertEquals('user2', $submissions[0]->getUserId());
		$this->assertEquals(100002, $submissions[0]->getTimestamp());
	}

	public function testFindByFormWithUser(): void {
		$submissions = $this->submissionMapper->findByForm($this->testForms[0]['id'], 'user1');

		$this->assertCount(2, $submissions);
		foreach ($submissions as $submission) {
			$this->assertEquals('user1', $submission->getUserId());
		}
	}

	public function testFindByFormWithSearchQuery(): void {
		$submissions = $this->submissionMapper->findByForm($this->testForms[0]['id'], null, 'Search term');

		$this->assertCount(1, $submissions);
		$this->assertEquals('user2', $submissions[0]->getUserId());
	}

	public function testFindByFormWithLimit(): void {
		$submissions = $this->submissionMapper->findByForm($this->testForms[0]['id'], null, null, 2);

		$this->assertCount(2, $submissions);
	}

	public function testFindByFormWithOffset(): void {
		$submissions = $this->submissionMapper->findByForm($this->testForms[0]['id'], null, null, null, 1);

		$this->assertCount(2, $submissions);
	}

	public function testCountSubmissionsBasic(): void {
		$count = $this->submissionMapper->countSubmissions($this->testForms[0]['id']);

		$this->assertEquals(3, $count);
	}

	public function testCountSubmissionsWithUser(): void {
		$count = $this->submissionMapper->countSubmissions($this->testForms[0]['id'], 'user1');

		$this->assertEquals(2, $count);
	}

	public function testCountSubmissionsWithSearch(): void {
		$count = $this->submissionMapper->countSubmissions($this->testForms[0]['id'], null, 'Search term');

		$this->assertEquals(1, $count);
	}

	public function testCountSubmissionsEmptyForm(): void {
		$count = $this->submissionMapper->countSubmissions($this->testForms[1]['id']);

		$this->assertEquals(0, $count);
	}
}
