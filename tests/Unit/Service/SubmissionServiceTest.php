<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\Service\SubmissionService;

use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FilterTest extends TestCase {

	/** @var SubmissionService */
	private $submissionService;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var QuestionMapper|MockObject */
	private $questionMapper;

	/** @var SubmissionMapper|MockObject */
	private $submissionMapper;

	/** @var AnswerMapper|MockObject */
	private $answerMapper;

	/** @var IRootFolder|MockObject */
	private $storage;

	/** @var IConfig|MockObject */
	private $config;

	/** @var IDateTimeFormatter|MockObject */
	private $dateTimeFormatter;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var ILogger|MockObject */
	private $logger;

	/** @var IUserManager|MockObject */
	private $userManager;

	public function setUp(): void {
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->storage = $this->createMock(IRootFolder::class);
		$this->config = $this->createMock(IConfig::class);
		$this->dateTimeFormatter = $this->createMock(IDateTimeFormatter::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$userSession = $this->createMock(IUserSession::class);

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->l10n->expects($this->any())
			->method('t')
			->will($this->returnCallback(function (string $identity) {
				return $identity;
			}));

		$this->submissionService = new SubmissionService(
			$this->formMapper,
			$this->questionMapper,
			$this->submissionMapper,
			$this->answerMapper,
			$this->storage,
			$this->config,
			$this->dateTimeFormatter,
			$this->l10n,
			$this->logger,
			$this->userManager,
			$userSession
		);
	}

	// Data for SubmissionCsv
	public function dataGetSubmissionsCsv() {
		return [
			'two-basic-submissions' => [
				// Questions
				[
					['id' => 1, 'text' => 'Question 1'],
					['id' => 2, 'text' => 'Question 2']
				],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'user1',
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
							['questionId' => 2, 'text' => 'Q2A1']
						]
					],
					[
						'id' => 2,
						'userId' => 'user2',
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A2'],
							['questionId' => 2, 'text' => 'Q2A2']
						]
					],
				],
				// Expected CSV-Result
				'
				"User display name","Timestamp","Question 1","Question 2"
				"User 1","01.01.01, 01:01","Q1A1","Q2A1"
				"User 2","01.01.01, 01:01","Q1A2","Q2A2"
				'
			],
			'checkbox-multi-answers' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple', 'text' => 'Question 1']
				],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'user1',
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
							['questionId' => 1, 'text' => 'Q1A2'],
							['questionId' => 1, 'text' => 'Q1A3'],
						]
					],
				],
				// Expected CSV-Result
				'
				"User display name","Timestamp","Question 1"
				"User 1","01.01.01, 01:01","Q1A1; Q1A2; Q1A3"
				'
			],
			'anonymous-user' => [
				// Questions
				[
					['id' => 1, 'text' => 'Question 1']
				],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'anon-user-xyz',
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
						]
					],
				],
				// Expected CSV-Result
				'
				"User display name","Timestamp","Question 1"
				"Anonymous user","01.01.01, 01:01","Q1A1"
				'
			],
			/* No submissions, but request via api */
			'no-submission' => [
				// Questions
				[
					['id' => 1, 'text' => 'Question 1']
				],
				// Array of Submissions incl. Answers
				[],
				// Expected CSV-Result
				'
				"User display name","Timestamp","Question 1"
				'
			],
			/* All Questions e.g. got deleted */
			'no-questions' => [
				// Questions
				[],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'anon-user-xyz',
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
						]
					],
				],
				// Expected CSV-Result
				'
				"User display name","Timestamp"
				"Anonymous user","01.01.01, 01:01"
				'
			],
		];
	}
	/**
	 * @dataProvider dataGetSubmissionsCsv
	 *
	 * @param array $questions
	 * @param array $submissions
	 * @param string $csvText
	 */
	public function testGetSubmissionsCsv(array $questions, array $submissions, string $csvText) {
		$form = new Form();
		$form->setId(5);
		$form->setHash('abcdefg');
		$form->setTitle('Some nice Form Title');
		$this->formMapper->expects($this->any())
			->method('findByHash')
			->with('abcdefg')
			->willReturn($form);

		$this->submissionMapper->expects($this->once())
			->method('findByForm')
			->with(5)
			// Return SubmissionObjects for given Submissions
			->will($this->returnCallback(function (int $formId) use ($submissions) {
				$submissionEntities = array_map(function ($submission) {
					unset($submission['answers']);
					return Submission::fromParams($submission);
				}, $submissions);

				return $submissionEntities;
			}));

		$this->questionMapper->expects($this->once())
		->method('findByForm')
		->with(5)
		// Return QuestionObjects for given Questions
		->will($this->returnCallback(function (int $formId) use ($questions) {
			$questionEntities = array_map(function ($question) {
				return Question::fromParams($question);
			}, $questions);

			return $questionEntities;
		}));

		date_default_timezone_set('Europe/Berlin');
		$this->config->expects($this->once())
			->method('getUserValue')
			->with('core', 'timezone', 'currentUser', 'Europe/Berlin')
			->willReturn('Europe/Berlin');

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getDisplayName')
			->will($this->onConsecutiveCalls('User 1', 'User 2'));
		$this->userManager->expects($this->any())
			->method('get')
			->will($this->returnValueMap([
				['user1', $user],
				['user2', $user],
				['unknown', null]
			]));

		// Just using any timestamp here
		$this->dateTimeFormatter->expects($this->any())
			->method('formatDateTime')
			->willReturn('01.01.01, 01:01');

		$this->answerMapper->expects($this->any())
		->method('findBySubmission')
		// Return AnswerObjects for corresponding submission
		->will($this->returnCallback(function (int $submissionId) use ($submissions) {
			$matchingSubmission = array_filter($submissions, function ($submission) use ($submissionId) {
				return $submission['id'] === $submissionId;
			});

			$answerEntities = array_map(function ($answer) {
				return Answer::fromParams($answer);
			}, current($matchingSubmission)['answers']);

			return $answerEntities;
		}));

		// Prepend BOM-Sequence as Writer does and remove formatting-artefacts of dataProvider.
		$dataExpectation = chr(239).chr(187).chr(191) . ltrim(preg_replace('/\t+/', '', $csvText));

		$this->assertEquals([
			'fileName' => 'Some nice Form Title (responses).csv',
			'data' => $dataExpectation,
		], $this->submissionService->getSubmissionsCsv('abcdefg'));
	}
};
