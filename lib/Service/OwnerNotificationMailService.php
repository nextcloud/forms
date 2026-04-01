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
use OCP\IURLGenerator;
use OCP\Mail\Headers\AutoSubmitted;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

class OwnerNotificationMailService {
	public function __construct(
		private IMailer $mailer,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private SubmissionPdfService $submissionPdfService,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<string> $recipients
	 * @param array<int, array{question: string, answer: string}> $answerSummaries
	 * @param array<int, array{question: string, answer: string}> $pdfAnswerEntries
	 */
	public function send(Form $form, Submission $submission, array $recipients, array $answerSummaries = [], array $pdfAnswerEntries = []): void {
		$validRecipients = array_values(array_unique(array_filter($recipients, fn (string $recipient): bool => $this->mailer->validateMailAddress($recipient))));
		if ($validRecipients === []) {
			return;
		}

		$formTitle = $form->getTitle();
		$subject = $this->l10n->t('New response to %s', [$formTitle]);
		$resultsUrl = $this->urlGenerator->linkToRouteAbsolute('forms.page.views', [
			'hash' => $form->getHash(),
			'view' => 'results',
		]);

		try {
			$emailTemplate = $this->mailer->createEMailTemplate('forms.OwnerNotificationEmail', [
				'formTitle' => $formTitle,
			]);
			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($this->l10n->t('New form response received'));
			$emailTemplate->addBodyText(
				$this->l10n->t('A new response was submitted to the form %s.', [$formTitle])
			);

			if ($answerSummaries !== []) {
				$emailTemplate->addBodyText($this->l10n->t('Submission summary:'));
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

			$emailTemplate->addBodyButton($this->l10n->t('Open form results'), $resultsUrl);
			$emailTemplate->addFooter(
				$this->l10n->t('This message was sent automatically by %s.', [$this->l10n->t('Forms')])
			);

			$message = $this->mailer->createMessage();
			$message->setAutoSubmitted(AutoSubmitted::VALUE_AUTO_GENERATED);
			$message->setSubject($subject);
			$message->setTo($validRecipients);
			$message->useTemplate($emailTemplate);
			if ($form->getAttachSubmissionPdf()) {
				$entriesForPdf = $pdfAnswerEntries !== [] ? $pdfAnswerEntries : $answerSummaries;
				$message->attach(
					$this->mailer->createAttachment(
						$this->submissionPdfService->createPdf($form, $submission, $entriesForPdf),
						$this->submissionPdfService->createFilename($form, $submission),
						'application/pdf',
					),
				);
			}

			$this->mailer->send($message);
		} catch (\Throwable $e) {
			$this->logger->error('Failed to send owner notification email for submission', [
				'formId' => $form->getId(),
				'submissionId' => $submission->getId(),
				'exception' => $e,
			]);
		}
	}
}
