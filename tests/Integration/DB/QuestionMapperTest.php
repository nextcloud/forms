<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Integration\DB;

use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Tests\Integration\IntegrationBase;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * @group DB
 */
class QuestionMapperTest extends IntegrationBase {
	private QuestionMapper $questionMapper;

	protected array $users = [
		'test' => 'Test user',
	];

	public function setUp(): void {
		$this->testForms = [
			[
				'hash' => 'question_mapper_form',
				'title' => 'Question mapper form',
				'description' => '',
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
				'allow_comments' => false,
				'questions' => [
					[
						'type' => 'short',
						'text' => 'Active question',
						'isRequired' => false,
						'name' => '',
						'order' => 1,
						'options' => [],
						'accept' => [],
						'description' => '',
						'extraSettings' => [],
					],
					[
						'type' => 'short',
						'text' => 'Deleted question',
						'isRequired' => false,
						'name' => '',
						'order' => 0,
						'options' => [],
						'accept' => [],
						'description' => '',
						'extraSettings' => [],
					],
				],
				'shares' => [],
				'submissions' => [],
			],
		];
		parent::setUp();

		$this->questionMapper = \OCP\Server::get(QuestionMapper::class);
	}

	public function testFindByIdFindsActiveQuestion(): void {
		$question = $this->questionMapper->findById($this->testForms[0]['questions'][0]['id']);

		$this->assertSame('Active question', $question->getText());
	}

	public function testFindByIdSkipsDeletedQuestion(): void {
		$this->expectException(DoesNotExistException::class);
		$this->questionMapper->findById($this->testForms[0]['questions'][1]['id']);
	}

	public function testFindByIdThrowsForUnknownQuestion(): void {
		$this->expectException(DoesNotExistException::class);
		$this->questionMapper->findById(2147483647);
	}
}
