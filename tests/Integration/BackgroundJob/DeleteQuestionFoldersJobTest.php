<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Integration\BackgroundJob;

use OCA\Forms\BackgroundJob\DeleteQuestionFoldersJob;
use OCA\Forms\Constants;
use OCA\Forms\Tests\Integration\IntegrationBase;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

/**
 * @group DB
 */
class DeleteQuestionFoldersJobTest extends IntegrationBase {
	private DeleteQuestionFoldersJob $job;
	private IRootFolder $rootFolder;
	private string $testUserId = 'testuser';

	protected array $users = [
		'testuser' => 'Test User',
	];

	public function setUp(): void {
		parent::setUp();

		$this->rootFolder = \OCP\Server::get(IRootFolder::class);
		$filePathHelper = \OCP\Server::get(\OCA\Forms\Helper\FilePathHelper::class);
		$logger = \OCP\Server::get(\Psr\Log\LoggerInterface::class);
		$timeFactory = \OCP\Server::get(\OCP\AppFramework\Utility\ITimeFactory::class);

		$this->job = new DeleteQuestionFoldersJob(
			$timeFactory,
			$filePathHelper,
			$logger,
		);
	}

	public function tearDown(): void {
		// Clean up any created folders
		$this->cleanupTestFolders();

		parent::tearDown();
	}

	private function cleanupTestFolders(): void {
		try {
			$userFolder = $this->rootFolder->getUserFolder($this->testUserId);
			$formsFolder = $userFolder->get(Constants::FILES_FOLDER);
			if ($formsFolder instanceof \OCP\Files\Folder) {
				$formsFolder->delete();
			}
		} catch (NotFoundException $e) {
			// Folder doesn't exist, nothing to clean up
		}
	}

	private function setupTestFolders(int $formId, int $questionId, int $submissionId, string $questionName): void {
		$userFolder = $this->rootFolder->getUserFolder($this->testUserId);

		// Create Forms folder
		$formsFolder = $userFolder->newFolder(Constants::FILES_FOLDER);

		// Create form folder
		$formFolderName = $formId . ' - Test Form';
		$formFolder = $formsFolder->newFolder($formFolderName);

		// Create submission folder
		$submissionFolder = $formFolder->newFolder((string)$submissionId);

		// Create question folder
		$questionFolderName = $questionId . ' - ' . $questionName;
		$submissionFolder->newFolder($questionFolderName);
	}

	private function setupMultipleFormFolders(int $formId, int $questionId, array $submissionIds, string $questionName): void {
		$userFolder = $this->rootFolder->getUserFolder($this->testUserId);

		// Create Forms folder
		$formsFolder = $userFolder->newFolder(Constants::FILES_FOLDER);

		// Create multiple form folders (simulating form renames)
		$formFolderNames = [
			$formId . ' - Test Form',
			$formId . ' - Renamed Form',
		];

		foreach ($formFolderNames as $formFolderName) {
			$formFolder = $formsFolder->newFolder($formFolderName);

			foreach ($submissionIds as $submissionId) {
				$submissionFolder = $formFolder->newFolder((string)$submissionId);
				$questionFolderName = $questionId . ' - ' . $questionName;
				$submissionFolder->newFolder($questionFolderName);
			}
		}
	}

	private function folderExists(string $path): bool {
		try {
			$userFolder = $this->rootFolder->getUserFolder($this->testUserId);
			$userFolder->get($path);
			return true;
		} catch (NotFoundException) {
			return false;
		}
	}

	public function testRunDeletesQuestionFolders(): void {
		$formId = 1;
		$questionId = 42;
		$submissionId = 100;
		$questionName = 'Test Question';

		// Setup test folders
		$this->setupTestFolders($formId, $questionId, $submissionId, $questionName);

		// Verify folder exists before running job
		$questionFolderPath = Constants::FILES_FOLDER . '/' . $formId . ' - Test Form/' . $submissionId . '/' . $questionId . ' - ' . $questionName;
		$this->assertTrue($this->folderExists($questionFolderPath), 'Question folder should exist before job runs');

		// Run the job
		$this->job->run([
			'formId' => $formId,
			'questionId' => $questionId,
			'ownerId' => $this->testUserId,
		]);

		// Verify folder is deleted
		$this->assertFalse($this->folderExists($questionFolderPath), 'Question folder should be deleted after job runs');
	}

	public function testRunDeletesFromMultipleFormFolders(): void {
		$formId = 1;
		$questionId = 42;
		$submissionIds = [100, 101];
		$questionName = 'Test Question';

		// Setup multiple form folders with submissions
		$this->setupMultipleFormFolders($formId, $questionId, $submissionIds, $questionName);

		// Verify folders exist before running job
		$formFolderNames = [$formId . ' - Test Form', $formId . ' - Renamed Form'];
		foreach ($formFolderNames as $formFolderName) {
			foreach ($submissionIds as $submissionId) {
				$questionFolderPath = Constants::FILES_FOLDER . '/' . $formFolderName . '/' . $submissionId . '/' . $questionId . ' - ' . $questionName;
				$this->assertTrue($this->folderExists($questionFolderPath), "Question folder should exist before job runs: $questionFolderPath");
			}
		}

		// Run the job
		$this->job->run([
			'formId' => $formId,
			'questionId' => $questionId,
			'ownerId' => $this->testUserId,
		]);

		// Verify all question folders are deleted
		foreach ($formFolderNames as $formFolderName) {
			foreach ($submissionIds as $submissionId) {
				$questionFolderPath = Constants::FILES_FOLDER . '/' . $formFolderName . '/' . $submissionId . '/' . $questionId . ' - ' . $questionName;
				$this->assertFalse($this->folderExists($questionFolderPath), "Question folder should be deleted after job runs: $questionFolderPath");
			}
		}
	}

	public function testRunOnlyDeletesMatchingQuestionFolders(): void {
		$formId = 1;
		$questionId = 42;
		$otherQuestionId = 43;
		$submissionId = 100;

		$userFolder = $this->rootFolder->getUserFolder($this->testUserId);
		$formsFolder = $userFolder->newFolder(Constants::FILES_FOLDER);
		$formFolder = $formsFolder->newFolder($formId . ' - Test Form');
		$submissionFolder = $formFolder->newFolder((string)$submissionId);

		// Create two question folders
		$questionFolder1 = $submissionFolder->newFolder($questionId . ' - Question 1');
		$questionFolder2 = $submissionFolder->newFolder($otherQuestionId . ' - Question 2');

		// Run the job to delete only questionId 42
		$this->job->run([
			'formId' => $formId,
			'questionId' => $questionId,
			'ownerId' => $this->testUserId,
		]);

		// Verify questionId 42 folder is deleted
		$questionPath1 = Constants::FILES_FOLDER . '/' . $formId . ' - Test Form/' . $submissionId . '/' . $questionId . ' - Question 1';
		$this->assertFalse($this->folderExists($questionPath1), 'Question folder 42 should be deleted');

		// Verify other question folder still exists
		$questionPath2 = Constants::FILES_FOLDER . '/' . $formId . ' - Test Form/' . $submissionId . '/' . $otherQuestionId . ' - Question 2';
		$this->assertTrue($this->folderExists($questionPath2), 'Question folder 43 should still exist');
	}

	public function testRunHandlesNonExistentFormFolder(): void {
		$formId = 999;
		$questionId = 42;

		// Don't create any folders - job should handle gracefully
		$this->job->run([
			'formId' => $formId,
			'questionId' => $questionId,
			'ownerId' => $this->testUserId,
		]);

		// Should not throw exception
		$this->assertTrue(true, 'Job should handle non-existent form folder gracefully');
	}

	public function testRunHandlesNonExistentFormsFolder(): void {
		$formId = 1;
		$questionId = 42;

		// Don't create Forms folder - job should handle gracefully
		$this->job->run([
			'formId' => $formId,
			'questionId' => $questionId,
			'ownerId' => $this->testUserId,
		]);

		// Should not throw exception
		$this->assertTrue(true, 'Job should handle non-existent Forms folder gracefully');
	}
}
