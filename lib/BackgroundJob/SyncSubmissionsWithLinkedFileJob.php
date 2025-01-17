<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\BackgroundJob;

use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\NotFoundException;
use OCP\IUserManager;
use OCP\IUserSession;

use Psr\Log\LoggerInterface;
use Throwable;

class SyncSubmissionsWithLinkedFileJob extends QueuedJob {
	public const MAX_ATTEMPTS = 10;

	public function __construct(
		ITimeFactory $time,
		private FormMapper $formMapper,
		private FormsService $formsService,
		private SubmissionService $submissionService,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private IUserSession $userSession,
		private IJobList $jobList,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array $argument
	 */
	public function run($argument): void {
		$oldUser = $this->userSession->getUser();
		$formId = $argument['form_id'];
		$attempt = $argument['attempt'] ?? 1;

		try {
			$form = $this->formMapper->findById($formId);

			$ownerId = $form->getOwnerId();
			$user = $this->userManager->get($ownerId);
			$this->userSession->setUser($user);

			$fileFormat = $form->getFileFormat();
			$filePath = $this->formsService->getFilePath($form);

			$this->submissionService->writeFileToCloud($form, $filePath, $fileFormat, $ownerId);
		} catch (NotFoundException $e) {
			$this->logger->notice('Form {formId} linked to a file that doesn\'t exist anymore', [
				'formId' => $formId
			]);
		} catch (Throwable $e) {
			$this->logger->warning(
				'Failed to synchronize form {formId} with the file (attempt {attempt} of {maxAttempts}), reason: {message}',
				[
					'formId' => $formId,
					'message' => $e->getMessage(),
					'attempt' => $attempt,
					'maxAttempts' => self::MAX_ATTEMPTS,
				]
			);

			if ($attempt < self::MAX_ATTEMPTS) {
				$this->jobList->scheduleAfter(
					SyncSubmissionsWithLinkedFileJob::class,
					$this->nextAttempt($attempt),
					['form_id' => $formId, 'attempt' => $attempt + 1]
				);
			}
		} finally {
			$this->userSession->setUser($oldUser);
		}
	}

	/**
	 * Calculates exponential delay (cubic growth) in seconds.
	 */
	private function nextAttempt(int $numberOfAttempt): int {
		return $this->time->getTime() + pow($numberOfAttempt, 3) * 60;
	}
}
