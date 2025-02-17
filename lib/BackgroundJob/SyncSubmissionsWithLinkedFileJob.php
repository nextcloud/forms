<?php

/**
 * @copyright Copyright (c) 2024 Andrii Ilkiv <ailkiv@users.noreply.github.com>
 *
 * @author Andrii Ilkiv <ailkiv@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
