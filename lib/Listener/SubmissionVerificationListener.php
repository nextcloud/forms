<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Listener;

use OCA\Forms\Constants;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Forms\Service\SubmissionVerificationMailService;
use OCA\Forms\Service\SubmissionVerificationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<FormSubmittedEvent>
 */
class SubmissionVerificationListener implements IEventListener {
	public function __construct(
		private AnswerMapper $answerMapper,
		private QuestionMapper $questionMapper,
		private SubmissionVerificationService $submissionVerificationService,
		private SubmissionVerificationMailService $submissionVerificationMailService,
		private IMailer $mailer,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof FormSubmittedEvent)) {
			return;
		}
		if ($event->getTrigger() !== FormSubmittedEvent::TRIGGER_CREATED) {
			return;
		}

		$form = $event->getForm();
		$submission = $event->getSubmission();
		$emailForVerification = null;
		try {
			$answers = $this->answerMapper->findBySubmission($submission->getId());
		} catch (DoesNotExistException $e) {
			$this->submissionVerificationService->markVerified($submission);
			return;
		}

		foreach ($answers as $answer) {
			try {
				$question = $this->questionMapper->findById($answer->getQuestionId());
			} catch (DoesNotExistException $e) {
				$this->logger->warning('Question missing while preparing submission verification mail', [
					'formId' => $form->getId(),
					'submissionId' => $submission->getId(),
					'questionId' => $answer->getQuestionId(),
				]);
				continue;
			}

			$extraSettings = $question->getExtraSettings();
			$isVerificationQuestion = $question->getType() === Constants::ANSWER_TYPE_SHORT
				&& ($extraSettings['validationType'] ?? null) === 'email'
				&& ($extraSettings['confirmationRecipient'] ?? false) === true
				&& ($extraSettings['requireEmailVerification'] ?? false) === true;

			if (!$isVerificationQuestion) {
				continue;
			}

			$answerText = trim($answer->getText() ?? '');
			if ($answerText !== '') {
				$emailForVerification = $answerText;
				break;
			}
		}

		if ($emailForVerification === null) {
			$this->submissionVerificationService->markVerified($submission);
			return;
		}

		if (!$this->mailer->validateMailAddress($emailForVerification)) {
			$this->logger->warning('Skipping submission verification for invalid email address', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
			]);
			return;
		}

		try {
			$this->submissionVerificationService->markPendingVerification($submission);
			$token = $this->submissionVerificationService->createVerificationToken($submission, $emailForVerification);
			if ($token === null) {
				return;
			}

			$verificationLink = $this->submissionVerificationService->createVerificationLink($token);
			$this->submissionVerificationMailService->send($form, $submission, $emailForVerification, $verificationLink);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to process submission verification', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
				'exception' => $e,
			]);
		}
	}
}
