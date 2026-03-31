<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Listener;

use OCA\Forms\Constants;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Forms\Service\ConfirmationMailService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<FormSubmittedEvent>
 */
class ConfirmationEmailListener implements IEventListener {
	public function __construct(
		private ConfirmationMailService $confirmationMailService,
		private AnswerMapper $answerMapper,
		private QuestionMapper $questionMapper,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof FormSubmittedEvent)) {
			return;
		}
		if (!$event->isNewSubmission()) {
			return;
		}

		$submission = $event->getSubmission();
		$form = $event->getForm();

		$emailAddress = null;
		$answerSummaries = [];
		try {
			$answers = $this->answerMapper->findBySubmission($submission->getId());
		} catch (DoesNotExistException $e) {
			return;
		}
		$hasAmbiguousRecipients = false;

		foreach ($answers as $answer) {
			try {
				$question = $this->questionMapper->findById($answer->getQuestionId());
			} catch (DoesNotExistException $e) {
				$this->logger->warning('Question missing while preparing confirmation mail', [
					'formId' => $form->getId(),
					'submissionId' => $submission->getId(),
					'questionId' => $answer->getQuestionId(),
				]);
				continue;
			}

			$questionType = $question->getType();
			$answerText = trim($answer->getText() ?? '');

			$extraSettings = $question->getExtraSettings();
			$isEmailQuestion = $questionType === Constants::ANSWER_TYPE_SHORT
				&& (($extraSettings['validationType'] ?? null) === 'email');
			$isConfirmationRecipient = ($extraSettings['confirmationRecipient'] ?? false) === true;

			if ($answerText !== '' && $isEmailQuestion && $isConfirmationRecipient) {
				if ($emailAddress !== null && !hash_equals($emailAddress, $answerText)) {
					$hasAmbiguousRecipients = true;
					break;
				}
				$emailAddress = $answerText;
			}

			if (
				$answerText !== ''
				&& in_array($questionType, [Constants::ANSWER_TYPE_SHORT, Constants::ANSWER_TYPE_LONG], true)
			) {
				$answerSummaries[] = [
					'question' => $question->getText(),
					'answer' => $answerText,
				];
			}
		}
		if ($hasAmbiguousRecipients) {
			$this->logger->warning('Skipping confirmation mail because multiple confirmation recipient questions were answered', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
			]);
			return;
		}

		if ($emailAddress === null) {
			return;
		}

		$this->confirmationMailService->send($form, $submission, $emailAddress, $answerSummaries);
	}
}
