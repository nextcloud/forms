<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Helper;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class FilePathHelper {
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IFilenameValidator $fileNameValidator,
	) {
	}

	/**
	 * Normalize a filename by replacing invalid characters
	 */
	public function normalizeFileName(string $fileName): string {
		return $this->fileNameValidator->sanitizeFilename($fileName, '-');
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
	 * Rename the upload folder to the form's current title.
	 * If that name already contains files, move the other folder's contents into it and delete the other folder.
	 */
	public function syncFormUploadFolderName(Form $form): void {
		$formsFolder = $this->getFormsFolder($form->getOwnerId());
		if ($formsFolder === null) {
			return;
		}

		$newName = $this->normalizeFileName($form->getId() . ' - ' . $form->getTitle());
		$folders = $this->getAllFormFoldersById($form->getId(), $form->getOwnerId());
		if ($folders === []) {
			return;
		}

		$source = $folders[0];
		foreach ($folders as $candidate) {
			if ($candidate->getDirectoryListing() !== []) {
				$source = $candidate;
				break;
			}
		}

		if ($source->getName() !== $newName) {
			if ($formsFolder->nodeExists($newName)) {
				$existing = $formsFolder->get($newName);
				if ($existing instanceof Folder && $existing->getId() !== $source->getId()) {
					if ($existing->getDirectoryListing() === []) {
						$existing->delete();
					} else {
						foreach ($source->getDirectoryListing() as $node) {
							$node->move($existing->getPath() . '/' . $node->getName());
						}
						$source->delete();
					}
				}
			}
			if (!$formsFolder->nodeExists($newName)) {
				$source->move($formsFolder->getPath() . '/' . $newName);
			}
		}

		foreach ($this->getAllFormFoldersById($form->getId(), $form->getOwnerId()) as $leftover) {
			if ($leftover->getName() !== $newName && $leftover->getDirectoryListing() === []) {
				$leftover->delete();
			}
		}
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
