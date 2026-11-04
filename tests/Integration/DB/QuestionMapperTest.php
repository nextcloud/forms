<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Integration\DB;

use OCA\Forms\Constants;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Tests\Integration\IntegrationBase;

/**
 * @group DB
 */
class QuestionMapperTest extends IntegrationBase {
	private QuestionMapper $questionMapper;
	/** @var array<string, Question> */
	private array $questions = [];

	protected array $users = [
		'test' => 'Test user',
	];

	protected array $testForms = [
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
			'questions' => [],
			'shares' => [],
		],
	];

	public function setUp(): void {
		parent::setUp();
		$this->questionMapper = \OCP\Server::get(QuestionMapper::class);

		$formId = $this->testForms[0]['id'];
		$this->questions['parent'] = $this->insertQuestion($formId, Constants::ANSWER_TYPE_CONDITIONAL, 1);
		$this->questions['deletedTop'] = $this->insertQuestion($formId, Constants::ANSWER_TYPE_SHORT, 0);
		$parentId = $this->questions['parent']->getId();
		$this->questions['a2'] = $this->insertQuestion($formId, Constants::ANSWER_TYPE_SHORT, 2, $parentId, 'branch-a');
		$this->questions['a1'] = $this->insertQuestion($formId, Constants::ANSWER_TYPE_SHORT, 1, $parentId, 'branch-a');
		$this->questions['aDeleted'] = $this->insertQuestion($formId, Constants::ANSWER_TYPE_SHORT, 0, $parentId, 'branch-a');
		$this->questions['b1'] = $this->insertQuestion($formId, Constants::ANSWER_TYPE_SHORT, 1, $parentId, 'branch-b');
	}

	public function tearDown(): void {
		foreach ($this->questions as $question) {
			$this->questionMapper->delete($question);
		}
		parent::tearDown();
	}

	private function insertQuestion(int $formId, string $type, int $order, ?int $parentQuestionId = null, ?string $branchId = null): Question {
		$question = new Question();
		$question->setFormId($formId);
		$question->setType($type);
		$question->setOrder($order);
		$question->setText('Question');
		$question->setDescription('');
		$question->setName('');
		$question->setIsRequired(false);
		$question->setExtraSettings([]);
		$question->setParentQuestionId($parentQuestionId);
		$question->setBranchId($branchId);
		return $this->questionMapper->insert($question);
	}

	/**
	 * @param Question[] $questions
	 * @return list<int>
	 */
	private function ids(array $questions): array {
		return array_map(static fn (Question $question) => $question->getId(), $questions);
	}

	private function id(string $key): int {
		return $this->questions[$key]->getId();
	}

	public function testFindByFormSkipsSubquestions(): void {
		$questions = $this->questionMapper->findByForm($this->testForms[0]['id']);
		$this->assertSame([$this->id('parent')], $this->ids($questions));
	}

	public function testFindByFormWithSubquestionsAndDeleted(): void {
		$questions = $this->questionMapper->findByForm($this->testForms[0]['id'], true, true);
		$this->assertEqualsCanonicalizing(
			[$this->id('parent'), $this->id('deletedTop'), $this->id('a2'), $this->id('a1'), $this->id('aDeleted'), $this->id('b1')],
			$this->ids($questions),
		);
	}

	public function testFindByParentQuestion(): void {
		$questions = $this->questionMapper->findByParentQuestion($this->id('parent'));
		$this->assertEqualsCanonicalizing([$this->id('a1'), $this->id('a2'), $this->id('b1')], $this->ids($questions));

		$withDeleted = $this->questionMapper->findByParentQuestion($this->id('parent'), true);
		$this->assertContains($this->id('aDeleted'), $this->ids($withDeleted));
	}

	public function testFindByBranchIsOrdered(): void {
		$questions = $this->questionMapper->findByBranch($this->id('parent'), 'branch-a');
		$this->assertSame([$this->id('a1'), $this->id('a2')], $this->ids($questions));
	}

	public function testDeleteByFormDeletesOptionsOfSubquestions(): void {
		$optionMapper = \OCP\Server::get(OptionMapper::class);
		$option = new Option();
		$option->setQuestionId($this->id('b1'));
		$option->setText('Option of a subquestion');
		$option->setOrder(1);
		$optionMapper->insert($option);
		$subQuestionId = $this->id('b1');

		$this->questionMapper->deleteByForm($this->testForms[0]['id']);
		// Nothing left to clean up
		$this->questions = [];

		$this->assertSame([], $optionMapper->findByQuestion($subQuestionId));
	}
}
