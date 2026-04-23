<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Listener;

use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Forms\Listener\SubmissionVerificationListener;
use OCA\Forms\Service\SubmissionVerificationMailService;
use OCA\Forms\Service\SubmissionVerificationService;
use OCP\Mail\IMailer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class SubmissionVerificationListenerTest extends TestCase {
	/** @var AnswerMapper|MockObject */
	private $answerMapper;
	/** @var QuestionMapper|MockObject */
	private $questionMapper;
	/** @var SubmissionVerificationService|MockObject */
	private $verificationService;
	/** @var SubmissionVerificationMailService|MockObject */
	private $mailService;
	/** @var IMailer|MockObject */
	private $mailer;
	/** @var LoggerInterface|MockObject */
	private $logger;

	private SubmissionVerificationListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->verificationService = $this->createMock(SubmissionVerificationService::class);
		$this->mailService = $this->createMock(SubmissionVerificationMailService::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new SubmissionVerificationListener(
			$this->answerMapper,
			$this->questionMapper,
			$this->verificationService,
			$this->mailService,
			$this->mailer,
			$this->logger,
		);
	}

	public function testHandleCreatesAndSendsVerificationToken(): void {
		$form = $this->createForm();
		$submission = $this->createSubmission(50, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$answer = new Answer();
		$answer->setQuestionId(9);
		$answer->setSubmissionId($submission->getId());
		$answer->setText('user@example.com');

		$question = new Question();
		$question->setId(9);
		$question->setFormId($form->getId());
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setText('Email');
		$question->setDescription('');
		$question->setName('');
		$question->setOrder(1);
		$question->setIsRequired(true);
		$question->setExtraSettings([
			'validationType' => 'email',
			'confirmationRecipient' => true,
			'requireEmailVerification' => true,
		]);

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with($submission->getId())
			->willReturn([$answer]);

		$this->questionMapper->expects($this->once())
			->method('findById')
			->with(9)
			->willReturn($question);

		$this->mailer->expects($this->once())
			->method('validateMailAddress')
			->with('user@example.com')
			->willReturn(true);

		$this->verificationService->expects($this->once())
			->method('markPendingVerification')
			->with($submission);

		$this->verificationService->expects($this->once())
			->method('createVerificationToken')
			->with($submission, 'user@example.com')
			->willReturn('0123456789abcdef0123456789abcdef0123456789abcdef');

		$this->verificationService->expects($this->once())
			->method('createVerificationLink')
			->with('0123456789abcdef0123456789abcdef0123456789abcdef')
			->willReturn('https://forms.example/verify');

		$this->mailService->expects($this->once())
			->method('send')
			->with($form, $submission, 'user@example.com', 'https://forms.example/verify');

		$this->verificationService->expects($this->never())
			->method('markVerified');

		$this->listener->handle($event);
	}

	public function testHandleWithoutVerificationQuestionMarksSubmissionVerified(): void {
		$form = $this->createForm();
		$submission = $this->createSubmission(51, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$answer = new Answer();
		$answer->setQuestionId(10);
		$answer->setSubmissionId($submission->getId());
		$answer->setText('just text');

		$question = new Question();
		$question->setId(10);
		$question->setFormId($form->getId());
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setText('Comment');
		$question->setDescription('');
		$question->setName('');
		$question->setOrder(1);
		$question->setIsRequired(false);
		$question->setExtraSettings([]);

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->willReturn([$answer]);

		$this->questionMapper->expects($this->once())
			->method('findById')
			->with(10)
			->willReturn($question);

		$this->verificationService->expects($this->once())
			->method('markVerified')
			->with($submission);

		$this->verificationService->expects($this->never())
			->method('markPendingVerification');

		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleSkipsNonCreatedTrigger(): void {
		$form = $this->createForm();
		$submission = $this->createSubmission(52, $form->getId());
		$event = new FormSubmittedEvent($form, $submission, FormSubmittedEvent::TRIGGER_VERIFIED);

		$this->answerMapper->expects($this->never())
			->method('findBySubmission');
		$this->verificationService->expects($this->never())
			->method('markPendingVerification');
		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	private function createForm(): Form {
		$form = new Form();
		$form->setId(3);
		$form->setTitle('Survey');
		$form->setOwnerId('owner');
		$form->setDescription('');
		$form->setFileId(null);
		$form->setFileFormat(null);
		$form->setCreated(0);
		$form->setExpires(0);
		$form->setIsAnonymous(false);
		$form->setSubmitMultiple(false);
		$form->setAllowEditSubmissions(false);
		$form->setShowExpiration(false);
		$form->setLastUpdated(0);
		$form->setState(Constants::FORM_STATE_ACTIVE);
		$form->setLockedBy(null);
		$form->setLockedUntil(null);
		$form->setSubmissionMessage(null);

		return $form;
	}

	private function createSubmission(int $id, int $formId): Submission {
		$submission = new Submission();
		$submission->setId($id);
		$submission->setFormId($formId);
		$submission->setUserId('user');
		$submission->setTimestamp(time());
		$submission->setIsVerified(true);

		return $submission;
	}
}
