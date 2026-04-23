<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
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
use OCA\Forms\Listener\ConfirmationEmailListener;
use OCA\Forms\Service\ConfirmationMailService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ConfirmationEmailListenerTest extends TestCase {
	/** @var ConfirmationMailService|MockObject */
	private $mailService;

	/** @var AnswerMapper|MockObject */
	private $answerMapper;

	/** @var QuestionMapper|MockObject */
	private $questionMapper;

	/** @var LoggerInterface|MockObject */
	private $logger;

	private ConfirmationEmailListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->mailService = $this->createMock(ConfirmationMailService::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new ConfirmationEmailListener(
			$this->mailService,
			$this->answerMapper,
			$this->questionMapper,
			$this->logger,
		);
	}

	public function testHandleSendsConfirmationMail(): void {
		$form = $this->createForm(7, 'Feedback form');
		$submission = $this->createSubmission(12, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$emailAnswer = $this->createAnswer(101, 'user@example.com', $submission->getId());
		$textAnswer = $this->createAnswer(102, 'Looks great!', $submission->getId());

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with($submission->getId())
			->willReturn([$emailAnswer, $textAnswer]);

		$emailQuestion = $this->createQuestion(101, Constants::ANSWER_TYPE_SHORT, 'Email address', [
			'validationType' => 'email',
			'confirmationRecipient' => true,
		]);
		$textQuestion = $this->createQuestion(102, Constants::ANSWER_TYPE_SHORT, 'Comment');

		$this->questionMapper->expects($this->exactly(2))
			->method('findById')
			->willReturnCallback(function (int $questionId) use ($emailQuestion, $textQuestion): Question {
				return match ($questionId) {
					101 => $emailQuestion,
					102 => $textQuestion,
					default => throw new \RuntimeException('Unexpected question id'),
				};
			});

		$this->mailService->expects($this->once())
			->method('send')
			->with(
				$this->identicalTo($form),
				$this->identicalTo($submission),
				'user@example.com',
				$this->callback(function (array $summaries): bool {
					if (count($summaries) !== 2) {
						return false;
					}

					$questions = array_column($summaries, 'question');
					$answers = array_column($summaries, 'answer');

					return in_array('Email address', $questions, true)
						&& in_array('user@example.com', $answers, true)
						&& in_array('Comment', $questions, true)
						&& in_array('Looks great!', $answers, true);
				})
			);

		$this->listener->handle($event);
	}

	public function testHandleWithEmailValidationButWithoutRecipientFlagSkipsMail(): void {
		$form = $this->createForm(21, 'Survey');
		$submission = $this->createSubmission(43, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$emailAnswer = $this->createAnswer(302, 'user@example.com', $submission->getId());

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with($submission->getId())
			->willReturn([$emailAnswer]);

		$emailQuestion = $this->createQuestion(302, Constants::ANSWER_TYPE_SHORT, 'Email', ['validationType' => 'email']);

		$this->questionMapper->expects($this->once())
			->method('findById')
			->with(302)
			->willReturn($emailQuestion);

		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleWithoutEmailSkipsMail(): void {
		$form = $this->createForm(20, 'Survey');
		$submission = $this->createSubmission(42, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$textAnswer = $this->createAnswer(301, 'No email provided', $submission->getId());

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with($submission->getId())
			->willReturn([$textAnswer]);

		$shortQuestion = $this->createQuestion(301, Constants::ANSWER_TYPE_SHORT, 'Comment');

		$this->questionMapper->expects($this->once())
			->method('findById')
			->with(301)
			->willReturn($shortQuestion);

		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleSkipsUpdatedSubmissions(): void {
		$form = $this->createForm(20, 'Survey');
		$submission = $this->createSubmission(42, $form->getId());
		$event = new FormSubmittedEvent($form, $submission, FormSubmittedEvent::TRIGGER_UPDATED);

		$this->answerMapper->expects($this->never())
			->method('findBySubmission');

		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleWithoutStoredAnswersSkipsMail(): void {
		$form = $this->createForm(20, 'Survey');
		$submission = $this->createSubmission(42, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->willThrowException(new DoesNotExistException('No answers'));

		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleWithMultipleRecipientQuestionsSkipsMail(): void {
		$form = $this->createForm(7, 'Feedback form');
		$submission = $this->createSubmission(12, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$firstEmailAnswer = $this->createAnswer(101, 'first@example.com', $submission->getId());
		$secondEmailAnswer = $this->createAnswer(102, 'second@example.com', $submission->getId());

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with($submission->getId())
			->willReturn([$firstEmailAnswer, $secondEmailAnswer]);

		$firstEmailQuestion = $this->createQuestion(101, Constants::ANSWER_TYPE_SHORT, 'Email address', [
			'validationType' => 'email',
			'confirmationRecipient' => true,
		]);
		$secondEmailQuestion = $this->createQuestion(102, Constants::ANSWER_TYPE_SHORT, 'Backup email address', [
			'validationType' => 'email',
			'confirmationRecipient' => true,
		]);

		$this->questionMapper->expects($this->exactly(2))
			->method('findById')
			->willReturnCallback(function (int $questionId) use ($firstEmailQuestion, $secondEmailQuestion): Question {
				return match ($questionId) {
					101 => $firstEmailQuestion,
					102 => $secondEmailQuestion,
					default => throw new \RuntimeException('Unexpected question id'),
				};
			});

		$this->logger->expects($this->once())
			->method('warning');

		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleSkipsPendingVerificationOnCreate(): void {
		$form = $this->createForm(7, 'Feedback form');
		$submission = $this->createSubmission(12, $form->getId());
		$submission->setIsVerified(false);
		$event = new FormSubmittedEvent($form, $submission, FormSubmittedEvent::TRIGGER_CREATED);

		$this->answerMapper->expects($this->never())
			->method('findBySubmission');
		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	private function createForm(int $id, string $title): Form {
		$form = new Form();
		$form->setId($id);
		$form->setTitle($title);
		$form->setDescription('');
		$form->setOwnerId('owner');
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
		$submission->setTimestamp(1);
		$submission->setIsVerified(true);

		return $submission;
	}

	private function createAnswer(int $questionId, string $text, int $submissionId): Answer {
		$answer = new Answer();
		$answer->setQuestionId($questionId);
		$answer->setSubmissionId($submissionId);
		$answer->setText($text);

		return $answer;
	}

	/**
	 * @param array<string, mixed> $extraSettings
	 */
	private function createQuestion(int $id, string $type, string $text, array $extraSettings = []): Question {
		$question = new Question();
		$question->setId($id);
		$question->setFormId(0);
		$question->setOrder(0);
		$question->setType($type);
		$question->setText($text);
		$question->setDescription('');
		$question->setIsRequired(false);
		$question->setExtraSettings($extraSettings);
		$question->setName('');

		return $question;
	}
}
