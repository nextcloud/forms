<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;
use OCP\IL10N;
use OCP\Mail\Headers\AutoSubmitted;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

class ConfirmationMailService {
	public function __construct(
		private IMailer $mailer,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param array<int, array{question: string, answer: string}> $answerSummaries
	 */
	public function send(Form $form, Submission $submission, string $recipient, array $answerSummaries = []): void {
		if (!$this->mailer->validateMailAddress($recipient)) {
			$this->logger->debug('Skipping confirmation mail, invalid recipient address', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
			]);
			return;
		}

		$formTitle = $form->getTitle();
		$subject = $this->l10n->t('Confirmation for your response to %s', [$formTitle]);

		try {
			// Create styled email template with Nextcloud branding
			$emailTemplate = $this->mailer->createEMailTemplate('forms.ConfirmationEmail', [
				'formTitle' => $formTitle,
			]);

			$emailTemplate->setSubject($subject);

			// Add header with Nextcloud logo
			$emailTemplate->addHeader();

			// Add heading
			$emailTemplate->addHeading($this->l10n->t('Form Submission Confirmed'));

			// Add body text
			$emailTemplate->addBodyText(
				$this->l10n->t('Thank you for submitting the form %s. We have successfully received your response.', [$formTitle])
			);

			// Add submission summary if available
			if ($answerSummaries !== []) {
				$emailTemplate->addBodyText($this->l10n->t('Your responses:'));

				foreach ($answerSummaries as $summary) {
					$emailTemplate->addBodyListItem(
						$summary['answer'],
						$summary['question'],
						'',
						'',
						''
					);
				}
			}

			// Add footer
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
			$this->logger->error('Failed to send confirmation email for submission', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
				'exception' => $e,
			]);
		}
	}
}
