<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\SubmissionVerification;
use OCA\Forms\Db\SubmissionVerificationMapper;
use OCA\Forms\Events\FormSubmittedEvent;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class SubmissionVerificationService {
	private const TOKEN_VALIDITY_SECONDS = 172800;

	public function __construct(
		private SubmissionVerificationMapper $submissionVerificationMapper,
		private SubmissionMapper $submissionMapper,
		private FormMapper $formMapper,
		private IEventDispatcher $eventDispatcher,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	public function markPendingVerification(Submission $submission): void {
		if ($submission->getIsVerified() === false) {
			return;
		}

		$submission->setIsVerified(false);
		$this->submissionMapper->update($submission);
	}

	public function markVerified(Submission $submission): void {
		if ($submission->getIsVerified() === true) {
			return;
		}

		$submission->setIsVerified(true);
		$this->submissionMapper->update($submission);
	}

	public function createVerificationToken(Submission $submission, string $emailAddress): ?string {
		$normalizedRecipientHash = $this->hashRecipient($emailAddress);
		$currentTimestamp = time();

		$verification = null;
		try {
			$verification = $this->submissionVerificationMapper->findBySubmissionId($submission->getId());
		} catch (DoesNotExistException $e) {
			// Fresh token, no pending verification for this submission yet.
		}

		if ($verification !== null
			&& $verification->getUsed() === null
			&& $verification->getExpires() >= $currentTimestamp
			&& hash_equals($verification->getRecipientEmailHash(), $normalizedRecipientHash)
		) {
			// Avoid duplicate verification mails for unchanged pending verification.
			return null;
		}

		$token = bin2hex(random_bytes(24));
		$tokenHash = hash('sha256', $token);

		if ($verification === null) {
			$verification = new SubmissionVerification();
			$verification->setSubmissionId($submission->getId());
		}

		$verification->setRecipientEmailHash($normalizedRecipientHash);
		$verification->setTokenHash($tokenHash);
		$verification->setExpires($currentTimestamp + self::TOKEN_VALIDITY_SECONDS);
		$verification->setUsed(null);

		if ($verification->getId() === null) {
			$this->submissionVerificationMapper->insert($verification);
		} else {
			$this->submissionVerificationMapper->update($verification);
		}

		return $token;
	}

	public function createVerificationLink(string $token): string {
		return $this->urlGenerator->linkToRouteAbsolute('forms.page.verifySubmissionEmail', [
			'token' => $token,
		]);
	}

	public function verifyToken(string $token): bool {
		$tokenHash = hash('sha256', $token);

		try {
			$verification = $this->submissionVerificationMapper->findByTokenHash($tokenHash);
		} catch (DoesNotExistException $e) {
			return false;
		}

		$currentTimestamp = time();
		if ($verification->getUsed() !== null || $verification->getExpires() < $currentTimestamp) {
			return false;
		}

		try {
			$submission = $this->submissionMapper->findById($verification->getSubmissionId());
		} catch (DoesNotExistException $e) {
			$this->logger->warning('Submission missing while verifying submission email', [
				'submissionId' => $verification->getSubmissionId(),
			]);
			return false;
		}

		if ($submission->getIsVerified() === false) {
			$submission->setIsVerified(true);
			$this->submissionMapper->update($submission);
		}

		$verification->setUsed($currentTimestamp);
		$this->submissionVerificationMapper->update($verification);
		try {
			$form = $this->formMapper->findById($submission->getFormId());
			$this->eventDispatcher->dispatchTyped(new FormSubmittedEvent($form, $submission, FormSubmittedEvent::TRIGGER_VERIFIED));
		} catch (DoesNotExistException $e) {
			$this->logger->warning('Form missing while dispatching verification-completed submission event', [
				'formId' => $submission->getFormId(),
				'submissionId' => $submission->getId(),
			]);
		}

		return true;
	}

	private function hashRecipient(string $emailAddress): string {
		return hash('sha256', strtolower(trim($emailAddress)));
	}
}
