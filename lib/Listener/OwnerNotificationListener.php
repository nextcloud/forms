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
use OCA\Forms\Service\OwnerNotificationMailService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<FormSubmittedEvent>
 */
class OwnerNotificationListener implements IEventListener {
	public function __construct(
		private OwnerNotificationMailService $ownerNotificationMailService,
		private AnswerMapper $answerMapper,
		private QuestionMapper $questionMapper,
		private IUserManager $userManager,
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

		$form = $event->getForm();
		$submission = $event->getSubmission();
		$recipients = $form->getNotificationRecipients();

		if ($form->getNotifyOwnerOnSubmission()) {
			$owner = $this->userManager->get($form->getOwnerId());
			$ownerMail = $owner?->getEMailAddress();
			if (is_string($ownerMail) && trim($ownerMail) !== '') {
				$recipients[] = trim($ownerMail);
			}
		}

		$normalizedRecipients = [];
		foreach ($recipients as $recipient) {
			$trimmedRecipient = trim($recipient);
			if ($trimmedRecipient === '') {
				continue;
			}

			$normalizedRecipients[strtolower($trimmedRecipient)] = $trimmedRecipient;
		}

		if ($normalizedRecipients === []) {
			return;
		}

		$answerSummaries = [];
		try {
			$answers = $this->answerMapper->findBySubmission($submission->getId());
		} catch (DoesNotExistException $e) {
			return;
		}
		foreach ($answers as $answer) {
			try {
				$question = $this->questionMapper->findById($answer->getQuestionId());
			} catch (DoesNotExistException $e) {
				$this->logger->warning('Question missing while preparing owner notification mail', [
					'formId' => $form->getId(),
					'submissionId' => $submission->getId(),
					'questionId' => $answer->getQuestionId(),
				]);
				continue;
			}

			$questionType = $question->getType();
			$answerText = trim($answer->getText() ?? '');
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

		$this->ownerNotificationMailService->send(
			$form,
			$submission,
			array_values($normalizedRecipients),
			$answerSummaries,
		);
	}
}
