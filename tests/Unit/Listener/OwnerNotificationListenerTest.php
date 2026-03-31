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
use OCA\Forms\Listener\OwnerNotificationListener;
use OCA\Forms\Service\OwnerNotificationMailService;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class OwnerNotificationListenerTest extends TestCase {
	/** @var OwnerNotificationMailService|MockObject */
	private $mailService;
	/** @var AnswerMapper|MockObject */
	private $answerMapper;
	/** @var QuestionMapper|MockObject */
	private $questionMapper;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var LoggerInterface|MockObject */
	private $logger;

	private OwnerNotificationListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->mailService = $this->createMock(OwnerNotificationMailService::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new OwnerNotificationListener(
			$this->mailService,
			$this->answerMapper,
			$this->questionMapper,
			$this->userManager,
			$this->logger,
		);
	}

	public function testHandleSendsOwnerNotification(): void {
		$form = $this->createForm();
		$form->setNotifyOwnerOnSubmission(true);

		$submission = $this->createSubmission(11, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getEMailAddress')
			->willReturn('owner@example.com');

		$this->userManager->expects($this->once())
			->method('get')
			->with('owner')
			->willReturn($owner);

		$answer = new Answer();
		$answer->setQuestionId(22);
		$answer->setSubmissionId(11);
		$answer->setText('Short text answer');

		$question = new Question();
		$question->setId(22);
		$question->setFormId($form->getId());
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setText('Question text');
		$question->setDescription('');
		$question->setName('');
		$question->setOrder(1);
		$question->setIsRequired(false);
		$question->setExtraSettings([]);

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(11)
			->willReturn([$answer]);

		$this->questionMapper->expects($this->once())
			->method('findById')
			->with(22)
			->willReturn($question);

		$this->mailService->expects($this->once())
			->method('send')
			->with(
				$this->identicalTo($form),
				$this->identicalTo($submission),
				$this->callback(function (array $recipients): bool {
					return $recipients === ['owner@example.com'];
				}),
				$this->callback(function (array $summaries): bool {
					return count($summaries) === 1
						&& $summaries[0]['question'] === 'Question text'
						&& $summaries[0]['answer'] === 'Short text answer';
				})
			);

		$this->listener->handle($event);
	}

	public function testHandleSkipsUpdatedSubmissions(): void {
		$form = $this->createForm();
		$submission = $this->createSubmission(11, $form->getId());
		$event = new FormSubmittedEvent($form, $submission, FormSubmittedEvent::TRIGGER_UPDATED);

		$this->answerMapper->expects($this->never())
			->method('findBySubmission');
		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	public function testHandleSkipsWhenOwnerHasNoMailAddress(): void {
		$form = $this->createForm();
		$form->setNotifyOwnerOnSubmission(true);

		$submission = $this->createSubmission(11, $form->getId());
		$event = new FormSubmittedEvent($form, $submission);

		$owner = $this->createMock(IUser::class);
		$owner->expects($this->once())
			->method('getEMailAddress')
			->willReturn('');

		$this->userManager->expects($this->once())
			->method('get')
			->with('owner')
			->willReturn($owner);

		$this->answerMapper->expects($this->never())
			->method('findBySubmission');
		$this->mailService->expects($this->never())
			->method('send');

		$this->listener->handle($event);
	}

	private function createForm(): Form {
		$form = new Form();
		$form->setId(1);
		$form->setTitle('Test form');
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
		$form->setNotifyOwnerOnSubmission(false);

		return $form;
	}

	private function createSubmission(int $id, int $formId): Submission {
		$submission = new Submission();
		$submission->setId($id);
		$submission->setFormId($formId);
		$submission->setUserId('submitter');
		$submission->setTimestamp(time());

		return $submission;
	}
}
