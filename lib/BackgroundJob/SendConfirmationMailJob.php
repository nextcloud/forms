<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

class SendConfirmationMailJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private IMailer $mailer,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{recipient: string, subject: string, body: string, formId: int, submissionId: int} $argument
	 */
	public function run($argument): void {
		$recipient = $argument['recipient'];
		$subject = $argument['subject'];
		$body = $argument['body'];
		$formId = $argument['formId'];
		$submissionId = $argument['submissionId'];

		try {
			$message = $this->mailer->createMessage();
			$message->setSubject($subject);
			$message->setPlainBody($body);
			$message->setTo([$recipient]);
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
