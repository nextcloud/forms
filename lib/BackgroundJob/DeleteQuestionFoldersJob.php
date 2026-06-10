<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\BackgroundJob;

use OCA\Forms\Helper\FilePathHelper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class DeleteQuestionFoldersJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly FilePathHelper $filePathHelper,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{formId: int, questionId: int, ownerId: string} $argument
	 */
	public function run($argument): void {
		$formId = $argument['formId'];
		$questionId = $argument['questionId'];
		$ownerId = $argument['ownerId'];

		try {
			$this->logger->debug('Deleting question folders for question {questionId} in form {formId}', [
				'questionId' => $questionId,
				'formId' => $formId,
			]);

			$formFolders = $this->filePathHelper->getAllFormFoldersById($formId, $ownerId);
			if (empty($formFolders)) {
				$this->logger->notice('Form folder not found, nothing to delete', [
					'formId' => $formId,
				]);
				return;
			}

			$questionFolderPrefix = $questionId . ' - ';
			$deletedCount = 0;

			// Iterate through all form folders (handles form renames)
			foreach ($formFolders as $formFolder) {
				// Iterate through submission folders and delete matching question folders
				foreach ($formFolder->getDirectoryListing() as $submissionFolder) {
					if (!$submissionFolder instanceof Folder) {
						continue;
					}
					foreach ($submissionFolder->getDirectoryListing() as $questionFolder) {
						if (str_starts_with((string)$questionFolder->getName(), $questionFolderPrefix)) {
							$questionFolder->delete();
							$deletedCount++;
						}
					}
				}
			}

			$this->logger->info('Deleted {count} question folders for question {questionId}', [
				'count' => $deletedCount,
				'questionId' => $questionId,
				'formId' => $formId,
			]);
		} catch (NotFoundException) {
			// Folder doesn't exist, do nothing
			$this->logger->notice('Question folder not found, nothing to delete', [
				'questionId' => $questionId,
				'formId' => $formId,
			]);
		} catch (\Throwable $e) {
			$this->logger->warning('Failed to delete question folders: {error}', [
				'error' => $e->getMessage(),
				'questionId' => $questionId,
				'formId' => $formId,
			]);
		}
	}
}
