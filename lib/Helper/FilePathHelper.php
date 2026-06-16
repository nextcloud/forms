<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Helper;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class FilePathHelper {
	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	/**
	 * Normalize a filename by replacing invalid characters
	 */
	public function normalizeFileName(string $fileName): string {
		return trim(str_replace(Constants::FILENAME_INVALID_CHARS, '-', $fileName));
	}

	/**
	 * Get the folder path for uploaded files of a form
	 */
	public function getFormUploadedFilesFolderPath(Form $form): string {
		return implode('/', [
			Constants::FILES_FOLDER,
			$this->normalizeFileName($form->getId() . ' - ' . $form->getTitle()),
		]);
	}

	/**
	 * Get the full file path for a specific uploaded file
	 */
	public function getUploadedFilePath(Form $form, int $submissionId, int $questionId, ?string $questionName, string $questionText): string {
		return implode('/', [
			$this->getFormUploadedFilesFolderPath($form),
			$submissionId,
			$this->normalizeFileName($questionId . ' - ' . ($questionName ?: $questionText))
		]);
	}

	/**
	 * Get all form folders matching the form ID prefix
	 * Useful for cleanup operations when form may have been renamed or deleted
	 * @param int $formId The form ID
	 * @param string $ownerId The owner user ID
	 * @return Folder[]
	 */
	public function getAllFormFoldersById(int $formId, string $ownerId): array {
		$formsFolder = $this->getFormsFolder($ownerId);
		if ($formsFolder === null) {
			return [];
		}

		$formFolderPrefix = $formId . ' - ';
		$matchingFolders = [];

		// Collect all folders matching the form ID prefix
		foreach ($formsFolder->getDirectoryListing() as $node) {
			if (str_starts_with((string)$node->getName(), $formFolderPrefix) && $node instanceof Folder) {
				$matchingFolders[] = $node;
			}
		}

		return $matchingFolders;
	}

	/**
	 * Get the forms folder for a user
	 */
	public function getFormsFolder(string $ownerId): ?Folder {
		try {
			$userFolder = $this->rootFolder->getUserFolder($ownerId);
			$formsFolder = $userFolder->get(Constants::FILES_FOLDER);

			if (!$formsFolder instanceof Folder) {
				return null;
			}

			return $formsFolder;
		} catch (NotFoundException) {
			return null;
		}
	}

	/**
	 * Get the submission folder for a specific submission
	 * Searches across all form folders to handle form renames
	 */
	public function getSubmissionFolder(Form $form, int $submissionId): ?Folder {
		$formFolders = $this->getAllFormFoldersById($form->getId(), $form->getOwnerId());

		// Search for submission folder in all matching form folders
		foreach ($formFolders as $formFolder) {
			try {
				$submissionFolder = $formFolder->get((string)$submissionId);
				if ($submissionFolder instanceof Folder) {
					return $submissionFolder;
				}
			} catch (NotFoundException) {
				// Continue to next form folder
			}
		}

		return null;
	}
}
