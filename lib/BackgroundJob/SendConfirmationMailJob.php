<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Defaults;
use OCP\Mail\IMailer;
use OCP\Util;
use Psr\Log\LoggerInterface;

class SendConfirmationMailJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly IMailer $mailer,
		private readonly LoggerInterface $logger,
		private readonly Defaults $defaults,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{recipient: string, subject: string, body: string, formId: int, submissionId: int} $argument
	 */
	public function run($argument): void {
		$recipient = $argument['recipient'];
		$subject = $argument['subject'];
		$plainBody = $argument['body'];
		#Escape html and add html line breaks
		$htmlBody = nl2br(htmlspecialchars($plainBody));
		$formId = $argument['formId'];
		$submissionId = $argument['submissionId'];

		try {
			$emailTemplate = $this->mailer->createEMailTemplate('forms.Confirmation');
			$emailTemplate->setSubject($subject);
			$emailTemplate->addHeader();
			$emailTemplate->addHeading($subject);
			$emailTemplate->addBodyText($htmlBody, $plainBody);
			$emailTemplate->addFooter();

			$message = $this->mailer->createMessage();
			$message->setFrom([Util::getDefaultEmailAddress('noreply') => $this->defaults->getName()]);
			$message->setTo([$recipient]);
			$message->useTemplate($emailTemplate);
			$this->mailer->send($message);
			$this->logger->debug('Confirmation email sent successfully', [
				'formId' => $formId,
				'submissionId' => $submissionId,
			]);
		} catch (\Exception $e) {
			$this->logger->error('Error while sending confirmation email', [
				'exception' => $e,
				'formId' => $formId,
				'submissionId' => $submissionId,
			]);
		}
	}
}
