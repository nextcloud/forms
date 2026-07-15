<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Helper;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Helper\FilePathHelper;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FilePathHelperTest extends TestCase {
	private FilePathHelper $filePathHelper;
	private IFilenameValidator|MockObject $filenameValidator;
	private IRootFolder|MockObject $rootFolder;

	public function setUp(): void {
		parent::setUp();
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->filenameValidator = $this->createMock(IFilenameValidator::class);
		$this->filenameValidator->method('sanitizeFilename')
			->willReturnCallback(static fn (string $fileName, string $replacement): string => str_replace(['/', '\\'], $replacement, trim($fileName)));
		$this->filePathHelper = new FilePathHelper($this->rootFolder, $this->filenameValidator);
	}

	public function testNormalizeFileName() {
		$this->assertEquals('test-file-name', $this->filePathHelper->normalizeFileName('test/file/name'));
		$this->assertEquals('test-file-name', $this->filePathHelper->normalizeFileName('test\file\name'));
		$this->assertEquals('test file name', $this->filePathHelper->normalizeFileName('test file name'));
		$this->assertEquals('test', $this->filePathHelper->normalizeFileName('  test  '));
	}

	public function testGetFormUploadedFilesFolderPath() {
		$form = new Form();
		$form->setId(42);
		$form->setTitle('My Form');

		$expected = implode('/', [
			Constants::FILES_FOLDER,
			'42 - My Form',
		]);

		$this->assertEquals($expected, $this->filePathHelper->getFormUploadedFilesFolderPath($form));
	}

	public function testGetUploadedFilePath() {
		$form = new Form();
		$form->setId(42);
		$form->setTitle('My Form');

		$expected = implode('/', [
			implode('/', [
				Constants::FILES_FOLDER,
				'42 - My Form',
			]),
			123,
			'10 - Question Name',
		]);

		$this->assertEquals($expected, $this->filePathHelper->getUploadedFilePath($form, 123, 10, 'Question Name', 'Question Text'));
	}

	public function testGetUploadedFilePathWithoutQuestionName() {
		$form = new Form();
		$form->setId(42);
		$form->setTitle('My Form');

		$expected = implode('/', [
			implode('/', [
				Constants::FILES_FOLDER,
				'42 - My Form',
			]),
			123,
			'10 - Question Text',
		]);

		$this->assertEquals($expected, $this->filePathHelper->getUploadedFilePath($form, 123, 10, null, 'Question Text'));
	}

	public function testGetFormsFolderReturnsFolder() {
		$userFolder = $this->createMock(Folder::class);
		$formsFolder = $this->createMock(Folder::class);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willReturn($formsFolder);

		$result = $this->filePathHelper->getFormsFolder('user1');
		$this->assertSame($formsFolder, $result);
	}

	public function testGetFormsFolderReturnsNullWhenNotFound() {
		$userFolder = $this->createMock(Folder::class);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willThrowException(new NotFoundException());

		$result = $this->filePathHelper->getFormsFolder('user1');
		$this->assertNull($result);
	}

	public function testGetFormsFolderReturnsNullWhenNotFolder() {
		$userFolder = $this->createMock(Folder::class);
		$notAFolder = $this->createMock(\OCP\Files\File::class);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willReturn($notAFolder);

		$result = $this->filePathHelper->getFormsFolder('user1');
		$this->assertNull($result);
	}

	public function testGetAllFormFoldersById() {
		$formsFolder = $this->createMock(Folder::class);
		$matchingFolder1 = $this->createMock(Folder::class);
		$matchingFolder2 = $this->createMock(Folder::class);
		$nonMatchingFolder = $this->createMock(Folder::class);

		$matchingFolder1->method('getName')->willReturn('42 - Form Title');
		$matchingFolder2->method('getName')->willReturn('42 - Another Title');
		$nonMatchingFolder->method('getName')->willReturn('43 - Different Form');

		$formsFolder->method('getDirectoryListing')->willReturn([
			$matchingFolder1,
			$matchingFolder2,
			$nonMatchingFolder,
		]);

		// Mock getFormsFolder to return our formsFolder
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willReturn($formsFolder);

		$result = $this->filePathHelper->getAllFormFoldersById(42, 'user1');
		$this->assertCount(2, $result);
		$this->assertContains($matchingFolder1, $result);
		$this->assertContains($matchingFolder2, $result);
		$this->assertNotContains($nonMatchingFolder, $result);
	}

	public function testGetAllFormFoldersByIdReturnsEmptyWhenFormsFolderNull() {
		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willThrowException(new NotFoundException());

		$result = $this->filePathHelper->getAllFormFoldersById(42, 'user1');
		$this->assertEmpty($result);
	}

	public function testGetSubmissionFolder() {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId('user1');

		$formsFolder = $this->createMock(Folder::class);
		$formFolder = $this->createMock(Folder::class);
		$submissionFolder = $this->createMock(Folder::class);

		$formFolder->method('getName')->willReturn('42 - Form Title');
		$formsFolder->method('getDirectoryListing')->willReturn([$formFolder]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willReturn($formsFolder);

		$formFolder->expects($this->once())
			->method('get')
			->with('123')
			->willReturn($submissionFolder);

		$result = $this->filePathHelper->getSubmissionFolder($form, 123);
		$this->assertSame($submissionFolder, $result);
	}

	public function testGetSubmissionFolderReturnsNullWhenNotFound() {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId('user1');

		$formsFolder = $this->createMock(Folder::class);
		$formFolder = $this->createMock(Folder::class);

		$formFolder->method('getName')->willReturn('42 - Form Title');
		$formsFolder->method('getDirectoryListing')->willReturn([$formFolder]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willReturn($formsFolder);

		$formFolder->expects($this->once())
			->method('get')
			->with('123')
			->willThrowException(new NotFoundException());

		$result = $this->filePathHelper->getSubmissionFolder($form, 123);
		$this->assertNull($result);
	}

	public function testGetSubmissionFolderReturnsNullWhenNotFolder() {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId('user1');

		$formsFolder = $this->createMock(Folder::class);
		$formFolder = $this->createMock(Folder::class);
		$notAFolder = $this->createMock(\OCP\Files\File::class);

		$formFolder->method('getName')->willReturn('42 - Form Title');
		$formsFolder->method('getDirectoryListing')->willReturn([$formFolder]);

		$userFolder = $this->createMock(Folder::class);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$userFolder->expects($this->once())
			->method('get')
			->with(Constants::FILES_FOLDER)
			->willReturn($formsFolder);

		$formFolder->expects($this->once())
			->method('get')
			->with('123')
			->willReturn($notAFolder);

		$result = $this->filePathHelper->getSubmissionFolder($form, 123);
		$this->assertNull($result);
	}
}
