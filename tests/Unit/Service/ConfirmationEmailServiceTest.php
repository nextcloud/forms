<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\BackgroundJob\SendConfirmationMailJob;
use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\ConfirmationEmailService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IL10N;
use OCP\IMemcache;
use OCP\Mail\IEmailValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ConfirmationEmailServiceTest extends TestCase {
	/** @var ConfirmationEmailService */
	private $service;

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var AnswerMapper|MockObject */
	private $answerMapper;

	/** @var QuestionMapper|MockObject */
	private $questionMapper;

	/** @var IEmailValidator|MockObject */
	private $emailValidator;

	/** @var IJobList|MockObject */
	private $jobList;

	/** @var ICacheFactory|MockObject */
	private $cacheFactory;

	/** @var IMemcache|MockObject */
	private $cache;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->configService = $this->createMock(ConfigService::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->emailValidator = $this->createMock(IEmailValidator::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(IMemcache::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l10n->method('t')->willReturnCallback(
			fn (string $text, array $params = []) => $params ? vsprintf($text, $params) : $text
		);

		$this->cacheFactory->method('createDistributed')
			->with('forms_confirmation_email')
			->willReturn($this->cache);

		$this->service = new ConfirmationEmailService(
			$this->configService,
			$this->answerMapper,
			$this->questionMapper,
			$this->emailValidator,
			$this->jobList,
			$this->cacheFactory,
			$this->l10n,
			$this->logger,
		);
	}

	private function createQuestionEntity(array $data): MockObject {
		$entity = $this->createMock(Question::class);
		$entity->method('read')->willReturn($data);
		return $entity;
	}

	private function makeEmailAnswer(int $submissionId, int $questionId, string $email): Answer {
		$answer = new Answer();
		$answer->setSubmissionId($submissionId);
		$answer->setQuestionId($questionId);
		$answer->setText($email);
		return $answer;
	}

	public function testSendDoesNothingIfConfirmationEmailDisabled(): void {
		$form = Form::fromParams(['id' => 1, 'confirmationEmailEnabled' => false]);
		$submission = new Submission();
		$submission->setId(10);

		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendDoesNothingIfAdminDisabled(): void {
		$form = Form::fromParams(['id' => 1, 'confirmationEmailEnabled' => true]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(false);
		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendDoesNothingIfNoRecipientQuestion(): void {
		$form = Form::fromParams([
			'id' => 1,
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => null,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->questionMapper->method('findByForm')->willReturn([]);
		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendDoesNothingIfRecipientQuestionChangedType(): void {
		$form = Form::fromParams([
			'id' => 1,
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity(['id' => 5, 'type' => Constants::ANSWER_TYPE_LONG]),
		]);
		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendDoesNothingIfNoEmailAnswer(): void {
		$form = Form::fromParams([
			'id' => 1,
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity([
				'id' => 5,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'extraSettings' => ['validationType' => 'email'],
			]),
		]);
		$this->answerMapper->method('findBySubmission')->willReturn([]);
		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendDoesNothingIfEmailAnswerIsInvalid(): void {
		$form = Form::fromParams([
			'id' => 1,
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity([
				'id' => 5,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'extraSettings' => ['validationType' => 'email'],
			]),
		]);
		$this->answerMapper->method('findBySubmission')->willReturn([
			$this->makeEmailAnswer(10, 5, 'not-an-email'),
		]);
		$this->emailValidator->method('isValid')->with('not-an-email')->willReturn(false);
		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendQueuesJobWithDefaultSubjectAndBody(): void {
		$form = Form::fromParams([
			'id' => 1,
			'title' => 'My Form',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->configService->method('getConfirmationEmailRateLimit')->willReturn(3);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity([
				'id' => 5,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'extraSettings' => ['validationType' => 'email'],
			]),
		]);
		$this->answerMapper->method('findBySubmission')->willReturn([
			$this->makeEmailAnswer(10, 5, 'user@example.com'),
		]);
		$this->emailValidator->method('isValid')->with('user@example.com')->willReturn(true);

		$this->cache->method('add')->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('user@example.com', $payload['recipient']);
					$this->assertSame('Thank you for your submission', $payload['subject']);
					$this->assertStringContainsString('My Form', $payload['body']);
					$this->assertSame(1, $payload['formId']);
					$this->assertSame(10, $payload['submissionId']);
					return true;
				})
			);

		$this->service->send($form, $submission);
	}

	public function testSendQueuesJobWithCustomSubjectAndBodyAndPlaceholders(): void {
		$form = Form::fromParams([
			'id' => 1,
			'title' => 'Survey',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
			'confirmationEmailSubject' => 'Hi {name}',
			'confirmationEmailBody' => 'Your answer: {email}',
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->configService->method('getConfirmationEmailRateLimit')->willReturn(3);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity([
				'id' => 3,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Name',
				'name' => 'name',
			]),
			$this->createQuestionEntity([
				'id' => 5,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			]),
		]);

		$nameAnswer = new Answer();
		$nameAnswer->setSubmissionId(10);
		$nameAnswer->setQuestionId(3);
		$nameAnswer->setText('Alice');

		$emailAnswer = $this->makeEmailAnswer(10, 5, 'alice@example.com');

		$this->answerMapper->method('findBySubmission')->willReturn([$nameAnswer, $emailAnswer]);
		$this->emailValidator->method('isValid')->willReturn(true);

		$this->cache->method('add')->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('Hi Alice', $payload['subject']);
					$this->assertSame('Your answer: alice@example.com', $payload['body']);
					return true;
				})
			);

		$this->service->send($form, $submission);
	}

	public function testSendReplacesPlaceholdersOfSubquestions(): void {
		$form = Form::fromParams([
			'id' => 1,
			'title' => 'Survey',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
			'confirmationEmailBody' => 'Your pet: {pet}',
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->configService->method('getConfirmationEmailRateLimit')->willReturn(3);
		$this->questionMapper->expects($this->once())
			->method('findByForm')
			->with(1, false, true)
			->willReturn([
				$this->createQuestionEntity([
					'id' => 5,
					'type' => Constants::ANSWER_TYPE_SHORT,
					'text' => 'Email',
					'name' => 'email',
					'extraSettings' => ['validationType' => 'email'],
				]),
				$this->createQuestionEntity([
					'id' => 7,
					'type' => Constants::ANSWER_TYPE_SHORT,
					'text' => 'Pet name',
					'name' => 'pet',
					'parentQuestionId' => 6,
					'branchId' => 'branch-1',
				]),
			]);

		$petAnswer = new Answer();
		$petAnswer->setSubmissionId(10);
		$petAnswer->setQuestionId(7);
		$petAnswer->setText('Rex');

		$this->answerMapper->method('findBySubmission')->willReturn([
			$this->makeEmailAnswer(10, 5, 'alice@example.com'),
			$petAnswer,
		]);
		$this->emailValidator->method('isValid')->willReturn(true);
		$this->cache->method('add')->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('Your pet: Rex', $payload['body']);
					return true;
				})
			);

		$this->service->send($form, $submission);
	}

	public function testSendDoesNothingWhenRateLimitExceeded(): void {
		$form = Form::fromParams([
			'id' => 1,
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->configService->method('getConfirmationEmailRateLimit')->willReturn(3);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity([
				'id' => 5,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'extraSettings' => ['validationType' => 'email'],
			]),
		]);
		$this->answerMapper->method('findBySubmission')->willReturn([
			$this->makeEmailAnswer(10, 5, 'user@example.com'),
		]);
		$this->emailValidator->method('isValid')->willReturn(true);

		$this->cache->method('add')->willReturn(false);
		$this->cache->method('inc')->willReturn(4); // exceeds limit of 3

		$this->jobList->expects($this->never())->method('add');

		$this->service->send($form, $submission);
	}

	public function testSendSkipsRateLimitWhenCacheIsNotIMemcache(): void {
		$plainCache = $this->createMock(ICache::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cacheFactory->method('createDistributed')->willReturn($plainCache);

		$service = new ConfirmationEmailService(
			$this->configService,
			$this->answerMapper,
			$this->questionMapper,
			$this->emailValidator,
			$this->jobList,
			$this->cacheFactory,
			$this->l10n,
			$this->logger,
		);

		$form = Form::fromParams([
			'id' => 1,
			'title' => 'Test',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 5,
		]);
		$submission = new Submission();
		$submission->setId(10);

		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->questionMapper->method('findByForm')->willReturn([
			$this->createQuestionEntity([
				'id' => 5,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'extraSettings' => ['validationType' => 'email'],
			]),
		]);
		$this->answerMapper->method('findBySubmission')->willReturn([
			$this->makeEmailAnswer(10, 5, 'user@example.com'),
		]);
		$this->emailValidator->method('isValid')->willReturn(true);

		$this->jobList->expects($this->once())->method('add');

		$service->send($form, $submission);
	}

	public function testValidateRecipientQuestionIdAllowsNull(): void {
		$form = new Form();
		$this->service->validateRecipientQuestionId($form, null);
		$this->assertTrue(true);
	}

	public function testValidateRecipientQuestionIdRejectsNonInt(): void {
		$form = new Form();
		$this->expectException(\InvalidArgumentException::class);
		$this->service->validateRecipientQuestionId($form, '7');
	}

	public function testValidateRecipientQuestionIdRejectsNotFoundQuestion(): void {
		$form = new Form();
		$this->questionMapper->method('findById')
			->willThrowException(new DoesNotExistException(''));
		$this->expectException(\InvalidArgumentException::class);
		$this->service->validateRecipientQuestionId($form, 7);
	}

	public function testValidateRecipientQuestionIdRejectsMismatchedForm(): void {
		$form = new Form();
		$form->setId(1);

		$question = new Question();
		$question->setFormId(2);
		$question->setOrder(1);
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setExtraSettings(['validationType' => 'email']);

		$this->questionMapper->method('findById')->willReturn($question);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->validateRecipientQuestionId($form, 7);
	}

	public function testValidateRecipientQuestionIdRejectsDeletedQuestion(): void {
		$form = new Form();
		$form->setId(1);

		$question = new Question();
		$question->setFormId(1);
		$question->setOrder(0);
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setExtraSettings(['validationType' => 'email']);

		$this->questionMapper->method('findById')->willReturn($question);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->validateRecipientQuestionId($form, 7);
	}

	public function testValidateRecipientQuestionIdRejectsNonEmailQuestion(): void {
		$form = new Form();
		$form->setId(1);

		$question = new Question();
		$question->setFormId(1);
		$question->setOrder(1);
		$question->setType(Constants::ANSWER_TYPE_LONG);

		$this->questionMapper->method('findById')->willReturn($question);
		$this->expectException(\InvalidArgumentException::class);
		$this->service->validateRecipientQuestionId($form, 7);
	}

	public function testValidateRecipientQuestionIdAllowsValidQuestion(): void {
		$form = new Form();
		$form->setId(1);

		$question = new Question();
		$question->setFormId(1);
		$question->setOrder(1);
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setExtraSettings(['validationType' => 'email']);

		$this->questionMapper->method('findById')->willReturn($question);
		$this->service->validateRecipientQuestionId($form, 7);
		$this->assertTrue(true);
	}
}
