<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\UploadedFileMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class SubmissionServiceTest extends TestCase {

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

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IMailer|MockObject */
	private $mailer;

	/** @var ITempManager|MockObject */
	private $tempManager;

	/** @var FormsService|MockObject */
	private $formsService;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var UploadedFileMapper|MockObject */
	private $uploadedFileMapper;

	public function setUp(): void {
		parent::setUp();
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->storage = $this->createMock(IRootFolder::class);
		$this->config = $this->createMock(IConfig::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
		$this->mailer = $this->getMockBuilder(IMailer::class)->getMock();
		$this->userManager = $this->createMock(IUserManager::class);
		$userSession = $this->createMock(IUserSession::class);
		$this->tempManager = $this->createMock(ITempManager::class);

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

		$this->formsService = $this->createMock(FormsService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->uploadedFileMapper = $this->createMock(UploadedFileMapper::class);

		$this->submissionService = new SubmissionService(
			$this->questionMapper,
			$this->submissionMapper,
			$this->answerMapper,
			$this->uploadedFileMapper,
			$this->storage,
			$this->config,
			$this->l10n,
			$this->logger,
			$this->userManager,
			$userSession,
			$this->mailer,
			$this->tempManager,
			$this->formsService,
			$this->urlGenerator,
		);
	}

	public function testGetSubmissions() {
		$submission_1 = new Submission();
		$submission_1->setId(42);
		$submission_1->setFormId(5);
		$submission_1->setUserId('someUser');
		$submission_1->setTimestamp(123456);
		$answer_1 = new Answer();
		$answer_1->setId(35);
		$answer_1->setSubmissionId(42);
		$answer_1->setQuestionId(422);
		$answer_1->setText('Just some Text');
		$answer_2 = new Answer();
		$answer_2->setId(36);
		$answer_2->setSubmissionId(42);
		$answer_2->setQuestionId(423);
		$answer_2->setText('Just some more Text');

		$submission_2 = new Submission();
		$submission_2->setId(43);
		$submission_2->setFormId(5);
		$submission_2->setUserId('someOtherUser');
		$submission_2->setTimestamp(1234);

		$this->submissionMapper->expects($this->once())
			->method('findByForm')
			->with(5)
			->willReturn([$submission_1, $submission_2]);

		$this->submissionMapper->expects($this->once())
			->method('findByFormAndUser')
			->with(5, 'someOtherUser')
			->willReturn([$submission_2]);

		$this->answerMapper->expects($this->any())
			->method('findBySubmission')
			->willReturnMap([
				[42, [$answer_1, $answer_2]],
				[43, []]
			]);

		$expected = [
			[
				'id' => 42,
				'formId' => 5,
				'userId' => 'someUser',
				'timestamp' => 123456,
				'answers' => [
					[
						'id' => 35,
						'submissionId' => 42,
						'questionId' => 422,
						'text' => 'Just some Text',
						'fileId' => null,
					],
					[
						'id' => 36,
						'submissionId' => 42,
						'questionId' => 423,
						'text' => 'Just some more Text',
						'fileId' => null,
					]
				]
			],
			[
				'id' => 43,
				'formId' => 5,
				'userId' => 'someOtherUser',
				'timestamp' => 1234,
				'answers' => []
			]
		];

		// All submissions
		$this->assertEquals($expected, $this->submissionService->getSubmissions(5));
		// Only submissions for a single user
		$this->assertEquals([$expected[1]], $this->submissionService->getSubmissions(5, 'someOtherUser'));
	}

	public function testGetSubmission() {
		$submission_1 = new Submission();
		$submission_1->setId(42);
		$submission_1->setFormId(5);
		$submission_1->setUserId('someUser');
		$submission_1->setTimestamp(123456);
		$answer_1 = new Answer();
		$answer_1->setId(35);
		$answer_1->setSubmissionId(42);
		$answer_1->setQuestionId(422);
		$answer_1->setText('Just some Text');
		$answer_2 = new Answer();
		$answer_2->setId(36);
		$answer_2->setSubmissionId(42);
		$answer_2->setQuestionId(423);
		$answer_2->setText('Just some more Text');

		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($submission_1);

		$this->answerMapper->expects($this->any())
			->method('findBySubmission')
			->willReturnMap([
				[42, [$answer_1, $answer_2]]
			]);

		$expected = [
			'id' => 42,
			'formId' => 5,
			'userId' => 'someUser',
			'timestamp' => 123456,
			'answers' => [
				[
					'id' => 35,
					'submissionId' => 42,
					'questionId' => 422,
					'text' => 'Just some Text',
					'fileId' => null,
				],
				[
					'id' => 36,
					'submissionId' => 42,
					'questionId' => 423,
					'text' => 'Just some more Text',
					'fileId' => null,
				]
			]
		];

		$this->assertEquals($expected, $this->submissionService->getSubmission(42));
	}

	public function testGetSubmissionNotFound() {
		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with(999)
			->willThrowException(new DoesNotExistException('Submission not found'));

		$this->assertNull($this->submissionService->getSubmission(999));
	}

	public function dataWriteFileToCloud() {
		return [
			'rootFolder' => ['Some nice Form Title', '', Folder::class, 'csv', 'Some nice Form Title (responses).csv', false],
			'subFolder' => ['Some nice Form Title', '/folder path', Folder::class, 'csv', 'Some nice Form Title (responses).csv', false],
			'csv-file' => ['Some nice Form Title', '/fileName.csv', File::class, 'csv', 'fileName.csv', true],
			'invalidFormTitle' => ['Form 1 / 2', '', Folder::class, 'csv', 'Form 1 - 2 (responses).csv', false],
		];
	}

	/**
	 * @dataProvider dataWriteFileToCloud
	 *
	 * @param string $formTitle Given form title
	 * @param string $path Selected user-path (from frontend)
	 * @param string $pathClass Type of $path - Folder or File
	 * @param string $pathExtension Extension of the given file within path
	 * @param string $expectedFileName
	 * @param bool $fileExists If the file to write into does exist already.
	 */
	public function testWriteFileToCloud(string $formTitle, string $path, string $pathClass, string $pathExtension, string $expectedFileName, bool $fileExists) {
		// Simple default Form Data here, details are tested in testGetSubmissionsCsv
		$dataExpectation = $this->setUpSimpleCsvTest($formTitle);

		$fileNode = $this->createMock(File::class);
		$fileNode->expects($this->once())
			->method('putContent')
			->with($dataExpectation);

		$fileNode->expects($this->once())
			->method('getContent')
			->willReturn('');

		$folderNode = $this->createMock(Folder::class);
		if ($fileExists) {
			$folderNode->expects($this->once())
				->method('get')
				->with($expectedFileName)
				->willReturn($fileNode);
		} else {
			$folderNode->expects($this->exactly(2))
				->method('get')
				->with($expectedFileName)
				->will($this->onConsecutiveCalls(
					$this->throwException(new NotFoundException('File not found')),
					$fileNode
				));
			$folderNode->expects($this->once())
				->method('newFile')
				->with($expectedFileName);
		}

		if ($pathClass === File::class) {
			$pathNode = $this->createMock(File::class);
			$pathNode->expects($this->once())
				->method('getExtension')
				->willReturn($pathExtension);
			$pathNode->expects($this->any())
				->method('getName')
				->willReturn($expectedFileName);
			$pathNode->expects($this->once())
				->method('getParent')
				->willReturn($folderNode);
		} elseif ($pathClass === Folder::class) {
			$pathNode = $folderNode;
		}

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('get')
			->with($path)
			->willReturn($pathNode);
		$this->storage->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);

		$this->tempManager->expects($this->once())
			->method('getTemporaryFile')
			->willReturn('/tmp/abcdefg.csv');

		$form = $this->formMapper->findByHash('abcdefg');

		$this->formsService->expects($this->once())
			->method('getFileName')
			->with($form, $pathExtension)
			->willReturn($expectedFileName);

		$this->submissionService->writeFileToCloud($form, $path, 'csv');
	}

	public function testWriteFileToCloudThrowsExceptionOnInvalidFormat() {
		$form = $this->formMapper->findByHash('abcdefg');

		$this->expectException(\InvalidArgumentException::class);
		$this->submissionService->writeFileToCloud($form, '', 'invalid');
	}

	// Data for SubmissionCsv
	public function dataGetSubmissionsData() {
		return [
			'two-basic-submissions' => [
				// Questions
				[
					['id' => 1, 'text' => 'Question 1'],
					['id' => 2, 'text' => 'Question 2'],
				],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'user1',
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
							['questionId' => 2, 'text' => 'Q2A1']
						]
					],
					[
						'id' => 2,
						'userId' => 'user2',
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A2'],
							['questionId' => 2, 'text' => 'Q2A2']
						]
					],
				],
				// Expected CSV-Result
				'
				"User ID","User display name","Timestamp","Question 1","Question 2"
				"user1","User 1","1973-11-29T22:33:09+01:00","Q1A2","Q2A2"
				"user2","User 2","1973-11-29T22:33:09+01:00","Q1A1","Q2A1"
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
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
							['questionId' => 1, 'text' => 'Q1A2'],
							['questionId' => 1, 'text' => 'Q1A3'],
						]
					],
				],
				// Expected CSV-Result
				'
				"User ID","User display name","Timestamp","Question 1"
				"user1","User 1","1973-11-29T22:33:09+01:00","Q1A1; Q1A2; Q1A3"
				'
			],
			'file-multi-answers' => [
				// Questions
				[
					['id' => 1, 'type' => 'file', 'text' => 'Question 1']
				],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'user1',
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 1, 'text' => 'file1.txt', 'fileId' => 1],
							['questionId' => 1, 'text' => 'file2.txt', 'fileId' => 2],
						]
					],
				],
				// Expected CSV-Result
				'
				"User ID","User display name","Timestamp","Question 1"
				"user1","User 1","1973-11-29T22:33:09+01:00","file1.txt; ' . '
file2.txt"
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
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
						]
					],
				],
				// Expected CSV-Result
				'
				"User ID","User display name","Timestamp","Question 1"
				"","Anonymous user","1973-11-29T22:33:09+01:00","Q1A1"
				'
			],
			'questions-not-answered' => [
				// Questions
				[
					['id' => 1, 'text' => 'Question 1'],
					['id' => 2, 'text' => 'Question 2'],
					['id' => 3, 'text' => 'Question 3']
				],
				// Array of Submissions incl. Answers
				[
					[
						'id' => 1,
						'userId' => 'user1',
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 2, 'text' => 'Q2A1']
						]
					],
				],
				// Expected CSV-Result
				'
				"User ID","User display name","Timestamp","Question 1","Question 2","Question 3"
				"user1","User 1","1973-11-29T22:33:09+01:00","","Q2A1",""
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
				"User ID","User display name","Timestamp","Question 1"
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
						'timestamp' => 123456789,
						'answers' => [
							['questionId' => 1, 'text' => 'Q1A1'],
						]
					],
				],
				// Expected CSV-Result
				'
				"User ID","User display name","Timestamp"
				"","Anonymous user","1973-11-29T22:33:09+01:00"
				'
			],
		];
	}
	/**
	 * @dataProvider dataGetSubmissionsData
	 *
	 * @param array $questions
	 * @param array $submissions
	 * @param string $csvText
	 */
	public function testGetSubmissionsData(array $questions, array $submissions, string $csvText) {
		$dataExpectation = $this->setUpCsvTest($questions, $submissions, $csvText, 'Some nice Form Title');
		$form = $this->formMapper->findByHash('abcdefg');

		$this->tempManager->expects($this->once())
			->method('getTemporaryFile')
			->willReturn('/tmp/abcdefg.csv');

		$this->assertEquals($dataExpectation, $this->submissionService->getSubmissionsData($form, 'csv'));
	}

	public function testGetSubmissionsDataThrowsExceptionOnInvalidFormat() {
		$form = $this->formMapper->findByHash('abcdefg');

		$this->expectException(\InvalidArgumentException::class);
		$this->submissionService->getSubmissionsData($form, 'invalid');
	}

	/**
	 * Setting up a very simple default CsvTest
	 */
	private function setUpSimpleCsvTest(string $formTitle = 'Some nice Form Title'): string {
		return $this->setUpCsvTest(
			[
				// Single Question
				['id' => 1, 'text' => 'Question 1']
			],
			[
				//Single Submission
				[
					'id' => 1,
					'userId' => 'user1',
					'timestamp' => 123456789,
					'answers' => [
						['questionId' => 1, 'text' => 'Q1A1']
					]
				]
			],
			// Expected CSV-Result
			'
			"User ID","User display name","Timestamp","Question 1"
			"user1","User 1","1973-11-29T22:33:09+01:00","Q1A1"
			',
			// Form title
			$formTitle
		);
	}

	/**
	 * Setting up all the mock-data for a full Form incl. Submissions
	 */
	private function setUpCsvTest(array $questions, array $submissions, string $csvText, string $formTitle): string {
		$form = new Form();
		$form->setId(5);
		$form->setHash('abcdefg');
		$form->setTitle($formTitle);
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

		$this->config->expects($this->once())
			->method('getSystemValueString')
			->with('default_timezone', 'UTC')
			->willReturn('Europe/Berlin');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('currentUser', 'core', 'timezone', 'Europe/Berlin')
			->willReturn('Europe/Berlin');

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->will($this->onConsecutiveCalls('user1', 'user2'));
		$user->expects($this->any())
			->method('getDisplayName')
			->will($this->onConsecutiveCalls('User 1', 'User 2'));
		$this->userManager->expects($this->any())
			->method('get')
			->willReturnMap([
				['user1', $user],
				['user2', $user],
				['unknown', null]
			]);

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
		$dataExpectation = chr(239) . chr(187) . chr(191) . ltrim(preg_replace('/\t+/', '', $csvText));

		return $dataExpectation;
	}

	// Data for validation of Submissions
	public function dataValidateSubmission() {
		return [
			'required-not-answered' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'text' => 'q1', 'isRequired' => true]
				],
				// Answers
				[],
				// Expected Result
				'Question "q1" is required.',
			],
			'required-not-answered-string' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'text' => 'q1', 'isRequired' => true]
				],
				// Answers
				[
					'1' => ['']
				],
				// Expected Result
				'Question "q1" is required.',
			],
			'required-empty-other-answer' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple_unique', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['allowOtherAnswer' => true], 'options' => [
						['id' => 3]
					]]
				],
				// Answers
				[
					'1' => [Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX]
				],
				// Expected Result
				'Question "q1" is required.',
			],
			'more-than-allowed' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple_unique', 'text' => 'q1', 'isRequired' => false, 'options' => [
						['id' => 3],
						['id' => 5]
					]]
				],
				// Answers
				[
					'1' => [3,5]
				],
				// Expected Result
				'Question "q1" can only have one answer.'
			],
			'option-not-known' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple', 'text' => 'q1', 'isRequired' => false, 'options' => [
						['id' => 3],
						['id' => 5]
					]],
				],
				// Answers
				[
					'1' => [3,10]
				],
				// Expected Result
				'Answer "10" for question "q1" is not a valid option.',
			],
			'other-answer-not-allowed' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple', 'text' => 'q1', 'isRequired' => false, 'options' => [
						['id' => 3],
						['id' => 5]
					]],
				],
				// Answers
				[
					'1' => [3, Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX . 'other answer']
				],
				// Expected Result
				'Answer "system-other-answer:other answer" for question "q1" is not a valid option.',
			],
			'question-not-known' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'text' => 'q1', 'isRequired' => false]
				],
				// Answers
				[
					'2' => ['answer']
				],
				// Expected Result
				'Answer for non-existent question with ID 2.',
			],
			'invalid-multiple-too-many-answers' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['optionsLimitMax' => 2], 'options' => [
						['id' => 3],
						['id' => 5],
						['id' => 7],
						['id' => 9],
					]]
				],
				// Answers
				[
					'1' => [3, 5, 7]
				],
				// Expected Result
				'Question "q1" requires at most 2 answers.',
			],
			'invalid-multiple-too-few-answers' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['optionsLimitMin' => 2], 'options' => [
						['id' => 3],
						['id' => 5],
						['id' => 7],
						['id' => 9],
					]]
				],
				// Answers
				[
					'1' => [3]
				],
				// Expected Result
				'Question "q1" requires at least 2 answers.',
			],
			'valid-multiple-with-limits' => [
				// Questions
				[
					['id' => 1, 'type' => 'multiple', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['optionsLimitMin' => 2, 'optionsLimitMax' => 3], 'options' => [
						['id' => 3],
						['id' => 5],
						['id' => 7],
						['id' => 9],
					]]
				],
				// Answers
				[
					'1' => [3,9]
				],
				// Expected Result
				null,
			],
			'invalid-short-phone' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['validationType' => 'phone']]
				],
				// Answers
				[
					'1' => ['0800 NEXTCLOUD']
				],
				// Expected Result
				'Invalid input for question "q1".',
			],
			'invalid-short-regex-not-matching' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['validationType' => 'regex', 'validationRegex' => '/[a-z]{4}/']]
				],
				// Answers
				[
					'1' => ['abc']
				],
				// Expected Result
				'Invalid input for question "q1".',
			],
			'invalid-short-number' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['validationType' => 'number']]
				],
				// Answers
				[
					'1' => ['11i']
				],
				// Expected Result
				'Invalid input for question "q1".',
			],
			'invalid-date-question' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => false]
				],
				// Answers
				[
					'1' => ['31.12.2022']
				],
				// Expected Result
				'Invalid date/time format for question "q1".',
			],
			'date-out-of-range-question' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['dateMin' => 1742860800]]
				],
				// Answers
				[
					'1' => ['2025-03-24']
				],
				// Expected Result
				'Date/time is not in the allowed range for question "q1".',
			],
			'valid-date-range' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['dateRange' => true]]
				],
				// Answers
				[
					'1' => ['2023-01-01', '2023-12-31']
				],
				// Expected Result
				null,
			],
			'invalid-date-range-single-date' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['dateRange' => true]]
				],
				// Answers
				[
					'1' => ['2023-01-01']
				],
				// Expected Result
				'Question "q1" can only have two answers.',
			],
			'invalid-date-range-wrong-order' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['dateRange' => true]]
				],
				// Answers
				[
					'1' => ['2023-12-31', '2023-01-01']
				],
				// Expected Result
				'Date/time values for question "q1" must be in ascending order.',
			],
			'valid-single-date' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => true]
				],
				// Answers
				[
					'1' => ['2023-01-01']
				],
				// Expected Result
				null,
			],
			'invalid-single-date-multiple-dates' => [
				// Questions
				[
					['id' => 1, 'type' => 'date', 'text' => 'q1', 'isRequired' => true]
				],
				// Answers
				[
					'1' => ['2023-01-01', '2023-12-31']
				],
				// Expected Result
				'Question "q1" can only have one answer.',
			],
			'invalid-time-question' => [
				// Questions
				[
					['id' => 1, 'type' => 'time', 'text' => 'q1', 'isRequired' => false]
				],
				// Answers
				[
					'1' => ['12:34am']
				],
				// Expected Result
				'Invalid date/time format for question "q1".',
			],
			'time-out-of-range-question' => [
				// Questions
				[
					['id' => 1, 'type' => 'time', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['timeMin' => '12:34']]
				],
				// Answers
				[
					'1' => ['12:33']
				],
				// Expected Result
				'Date/time is not in the allowed range for question "q1".',
			],
			'invalid-time-range-single-time' => [
				// Questions
				[
					['id' => 1, 'type' => 'time', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['timeRange' => true]]
				],
				// Answers
				[
					'1' => ['12:34']
				],
				// Expected Result
				'Question "q1" can only have two answers.',
			],
			'invalid-time-range-wrong-order' => [
				// Questions
				[
					['id' => 1, 'type' => 'time', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['timeRange' => true]]
				],
				// Answers
				[
					'1' => ['12:34', '12:33']
				],
				// Expected Result
				'Date/time values for question "q1" must be in ascending order.',
			],
			'invalid-single-time-multiple-times' => [
				// Questions
				[
					['id' => 1, 'type' => 'time', 'text' => 'q1', 'isRequired' => true]
				],
				// Answers
				[
					'1' => ['12:33', '12:34']
				],
				// Expected Result
				'Question "q1" can only have one answer.',
			],
			'invalid-linearcale-question' => [
				// Questions
				[
					['id' => 1, 'type' => 'linearscale', 'text' => 'q1', 'isRequired' => false, 'extraSettings' => ['optionsLowest' => 0]]
				],
				// Answers
				[
					'1' => ['6']
				],
				// Expected Result
				'The answer for question "q1" must be an integer between 0 and 5.',
			],
			'full-good-submission' => [
				// Questions
				[
					['id' => 1, 'type' => 'short', 'isRequired' => false],
					['id' => 2, 'type' => 'long', 'isRequired' => true],
					['id' => 3, 'type' => 'date', 'isRequired' => true],
					['id' => 4, 'type' => 'datetime', 'isRequired' => false],
					['id' => 5, 'type' => 'multiple', 'isRequired' => false, 'options' => [
						['id' => 1],
						['id' => 2]
					]],
					['id' => 6, 'type' => 'multiple_unique', 'isRequired' => false, 'options' => [
						['id' => 3],
						['id' => 4]
					]],
					['id' => 7, 'type' => 'dropdown', 'isRequired' => true, 'options' => [
						['id' => 5],
						['id' => 6]
					]],
					['id' => 8, 'type' => 'time', 'isRequired' => false],
					['id' => 9, 'type' => 'multiple_unique', 'isRequired' => true, 'extraSettings' => [
						'allowOtherAnswer' => true,
					], 'options' => [
						['id' => 3]
					]],
					['id' => 10, 'type' => 'short', 'isRequired' => false, 'extraSettings' => ['validationType' => 'email']],
					['id' => 11, 'type' => 'short', 'isRequired' => false, 'extraSettings' => ['validationType' => 'number']],
					['id' => 12, 'type' => 'short', 'isRequired' => false, 'extraSettings' => ['validationType' => 'phone']],
					['id' => 13, 'type' => 'short', 'isRequired' => false, 'extraSettings' => ['validationType' => 'regex', 'validationRegex' => '/[a-z]{3}[0-9]{3}/']],
					['id' => 14, 'type' => 'linearscale', 'isRequired' => false, 'extraSettings' => ['optionsLowest' => 0]],
					['id' => 15, 'type' => 'date', 'isRequired' => false, 'extraSettings' => [
						'dateMin' => 1742860800,
						'dateMax' => 1743033600]
					],
					// time limits
					['id' => 17, 'type' => 'time', 'isRequired' => false, 'extraSettings' => [
						'timeMin' => '12:30',
						'timeMax' => '12:34']
					],
					// time range
					['id' => 18, 'type' => 'time', 'text' => 'q1', 'isRequired' => true, 'extraSettings' => ['timeRange' => true]],
				],
				// Answers
				[
					'1' => ['answer'],
					'2' => ['answerABitLonger'],
					'3' => ['2021-04-28'],
					'4' => ['2021-04-30 04:40'],
					'5' => [1,2],
					'6' => [4],
					'7' => [5],
					'8' => ['17:45'],
					'9' => [Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX . 'other answer'],
					'10' => ['some.name+context@example.com'],
					'11' => ['100.45'],
					'12' => ['+49 711 25 24 28 90'],
					'13' => ['abc123'],
					'14' => ['3'],
					'15' => ['2025-03-26'],
					// valid time in limits
					'17' => ['12:33'],
					// valid time range
					'18' => ['12:33', '12:34'],
				],
				// Expected Result
				null,
			]
		];
	}

	/**
	 * @dataProvider dataValidateSubmission
	 *
	 * @param array $questions
	 * @param array $answers
	 * @param null|string $expected
	 */
	public function testValidateSubmission(array $questions, array $answers, ?string $expected) {
		$this->mailer->method('validateMailAddress')->willReturnCallback(function ($mail) {
			return $mail === 'some.name+context@example.com';
		});

		if ($expected !== null) {
			$this->expectException(\InvalidArgumentException::class);
			$this->expectExceptionMessage($expected);
		}

		$this->submissionService->validateSubmission($questions, $answers, 'admin');
		$this->assertTrue(true);
	}
};
