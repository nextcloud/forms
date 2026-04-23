<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;
use OCP\IL10N;
use OCP\Mail\Headers\AutoSubmitted;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

class SubmissionVerificationMailService {
	public function __construct(
		private IMailer $mailer,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	public function send(Form $form, Submission $submission, string $recipient, string $verificationLink): void {
		if (!$this->mailer->validateMailAddress($recipient)) {
			$this->logger->debug('Skipping submission verification mail, invalid recipient address', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
			]);
			return;
		}

		$formTitle = $form->getTitle();
		$subject = $this->l10n->t('Verify your email for %s', [$formTitle]);

		try {
			$emailTemplate = $this->mailer->createEMailTemplate('forms.SubmissionVerificationEmail', [
				'formTitle' => $formTitle,
			]);

			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($this->l10n->t('Verify your email address'));
			$emailTemplate->addBodyText(
				$this->l10n->t('A response was submitted to %s using this email address.', [$formTitle])
			);
			$emailTemplate->addBodyText(
				$this->l10n->t('Please verify your email address to confirm ownership of this submission.')
			);
			$emailTemplate->addBodyButton($this->l10n->t('Verify email address'), $verificationLink);
			$emailTemplate->addFooter(
				$this->l10n->t('This message was sent automatically by %s.', [$this->l10n->t('Forms')])
			);

			$message = $this->mailer->createMessage();
			$message->setAutoSubmitted(AutoSubmitted::VALUE_AUTO_GENERATED);
			$message->setSubject($subject);
			$message->setTo([$recipient]);
			$message->useTemplate($emailTemplate);

			$this->mailer->send($message);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to send submission verification email', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
				'exception' => $e,
			]);
		}
	}
}
