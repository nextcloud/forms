<?php

/**
 * @copyright Copyright (c) 2024 Kostiantyn Miakshyn <molodchick@gmail.com>
 *
 * @author Kostiantyn Miakshyn <molodchick@gmail.com>
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

use OCA\Forms\Constants;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\UploadedFileMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use Psr\Log\LoggerInterface;

class CleanupUploadedFilesJob extends TimedJob {
	private const FILE_LIFETIME = '-1 hour';

	public function __construct(
		private IRootFolder $rootFolder,
		private FormMapper $formMapper,
		private UploadedFileMapper $uploadedFileMapper,
		private LoggerInterface $logger,
		ITimeFactory $time,
	) {
		parent::__construct($time);

		$this->setInterval(60 * 60);
	}

	/**
	 * @param array $argument
	 */
	public function run($argument): void {
		$dateTime = new \DateTimeImmutable(self::FILE_LIFETIME);

		$this->logger->info('Deleting files that were uploaded before {before} and still not submitted.', [
			'before' => $dateTime->format(\DateTimeImmutable::ATOM),
		]);

		$uploadedFiles = $this->uploadedFileMapper->findUploadedEarlierThan($dateTime);

		$deleted = 0;
		$usersToCleanup = [];
		foreach ($uploadedFiles as $uploadedFile) {
			$this->logger->info('Deleting uploaded file "{originalFileName}" for form {formId}.', [
				'originalFileName' => $uploadedFile->getOriginalFileName(),
				'formId' => $uploadedFile->getFormId(),
			]);

			$form = $this->formMapper->findById($uploadedFile->getFormId());
			$usersToCleanup[$form->getOwnerId()] = true;
			$userFolder = $this->rootFolder->getUserFolder($form->getOwnerId());

			$nodes = $userFolder->getById($uploadedFile->getFileId());

			if (!empty($nodes)) {
				$node = $nodes[0];
				$node->delete();
			} else {
				$this->logger->warning('Could not find uploaded file "{fileId}" for deletion.', [
					'fileId' => $uploadedFile->getFileId(),
				]);
			}

			$this->uploadedFileMapper->delete($uploadedFile);

			$deleted++;
		}

		$this->logger->info('Deleted {deleted} uploaded files.', ['deleted' => $deleted]);

		// now delete empty folders in user folders
		$deleted = 0;
		foreach (array_keys($usersToCleanup) as $userId) {
			$this->logger->info('Cleaning up empty folders for user {userId}.', ['userId' => $userId]);
			$userFolder = $this->rootFolder->getUserFolder($userId);

			$unsubmittedFilesFolder = $userFolder->get(Constants::UNSUBMITTED_FILES_FOLDER);
			if (!$unsubmittedFilesFolder instanceof Folder) {
				continue;
			}

			foreach ($unsubmittedFilesFolder->getDirectoryListing() as $node) {
				if ($node->getName() < $dateTime->getTimestamp()) {
					$node->delete();
					$deleted++;
				}
			}
		}

		$this->logger->info('Deleted {deleted} folders.', ['deleted' => $deleted]);
	}
}
