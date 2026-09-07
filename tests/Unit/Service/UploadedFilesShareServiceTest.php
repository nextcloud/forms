<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Helper\FilePathHelper;
use OCA\Forms\Service\UploadedFilesShareService;
use OCP\Files\Folder;
use OCP\Files\IFilenameValidator;
use OCP\Files\IRootFolder;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class UploadedFilesShareServiceTest extends TestCase {
	private UploadedFilesShareService $service;
	private ShareMapper|MockObject $shareMapper;
	private IRootFolder|MockObject $rootFolder;
	private IManager|MockObject $shareManager;

	protected function setUp(): void {
		parent::setUp();

		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->shareManager = $this->createMock(IManager::class);
		$fileNameValidator = $this->createMock(IFilenameValidator::class);
		$fileNameValidator->method('sanitizeFilename')->willReturnArgument(0);
		$filePathHelper = new FilePathHelper(
			$this->rootFolder,
			$fileNameValidator,
		);

		$this->service = new UploadedFilesShareService(
			$filePathHelper,
			$this->shareManager,
			$this->shareMapper,
			$this->createMock(LoggerInterface::class),
		);
	}

	/**
	 * @return array{0: Folder&MockObject, 1: Folder&MockObject}
	 */
	private function mockFormsFolderWithChildren(string $ownerId, array $children): array {
		$formsFolder = $this->createMock(Folder::class);
		$formsFolder->method('getDirectoryListing')->willReturn($children);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->with(Constants::FILES_FOLDER)->willReturn($formsFolder);

		$this->rootFolder->method('getUserFolder')->with($ownerId)->willReturn($userFolder);

		return [$userFolder, $formsFolder];
	}

	public function testRemoveAllForFormCleansUpResultsShares(): void {
		$form = new Form();
		$form->setId(2);
		$form->setTitle('test');
		$form->setOwnerId('alice');

		$resultsShare = new Share();
		$resultsShare->setShareType(IShare::TYPE_USER);
		$resultsShare->setShareWith('bob');
		$resultsShare->setPermissions([Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS]);

		$submitOnlyShare = new Share();
		$submitOnlyShare->setShareType(IShare::TYPE_USER);
		$submitOnlyShare->setShareWith('carol');
		$submitOnlyShare->setPermissions([Constants::PERMISSION_SUBMIT]);

		$this->shareMapper->expects($this->once())
			->method('findByForm')
			->with(2)
			->willReturn([$resultsShare, $submitOnlyShare]);

		$folder = $this->createMock(Folder::class);
		$folder->method('getName')->willReturn('2 - test');
		$this->mockFormsFolderWithChildren('alice', [$folder]);

		$fileShare = $this->createMock(IShare::class);
		$fileShare->method('getSharedWith')->willReturn('bob');
		$this->shareManager->expects($this->once())
			->method('getSharesBy')
			->with('alice', IShare::TYPE_USER, $folder, false, -1)
			->willReturn([$fileShare]);
		$this->shareManager->expects($this->once())
			->method('deleteShare')
			->with($fileShare);

		$this->service->removeAllForForm($form);
	}

	public function testRemoveForCollaboratorRevokesSharesOnRenamedFormFolder(): void {
		$form = new Form();
		$form->setId(19);
		$form->setTitle('T2');
		$form->setOwnerId('alice');

		$share = new Share();
		$share->setShareType(IShare::TYPE_USER);
		$share->setShareWith('carol');
		$share->setPermissions([Constants::PERMISSION_RESULTS]);

		$oldTitleFolder = $this->createMock(Folder::class);
		$oldTitleFolder->method('getName')->willReturn('19 - T1');
		$this->mockFormsFolderWithChildren('alice', [$oldTitleFolder]);

		$fileShare = $this->createMock(IShare::class);
		$fileShare->method('getSharedWith')->willReturn('carol');
		$this->shareManager->expects($this->once())
			->method('getSharesBy')
			->with('alice', IShare::TYPE_USER, $oldTitleFolder, false, -1)
			->willReturn([$fileShare]);
		$this->shareManager->expects($this->once())
			->method('deleteShare')
			->with($fileShare);

		$this->service->removeForCollaborator($form, $share);
	}

	public function testRemoveForCollaboratorSkipsShareTypesWithoutFilesShare(): void {
		$form = new Form();
		$form->setId(2);
		$form->setTitle('test');
		$form->setOwnerId('alice');

		$linkShare = new Share();
		$linkShare->setShareType(IShare::TYPE_LINK);
		$linkShare->setPermissions([Constants::PERMISSION_RESULTS]);

		$this->rootFolder->expects($this->never())->method('getUserFolder');
		$this->shareManager->expects($this->never())->method('getSharesBy');

		$this->service->removeForCollaborator($form, $linkShare);
	}

	public function testRemoveForCollaboratorIgnoresMissingUploadedFilesFolder(): void {
		$form = new Form();
		$form->setId(2);
		$form->setTitle('test');
		$form->setOwnerId('alice');

		$share = new Share();
		$share->setShareType(IShare::TYPE_USER);
		$share->setShareWith('bob');
		$share->setPermissions([Constants::PERMISSION_RESULTS]);

		$this->mockFormsFolderWithChildren('alice', []);
		$this->shareManager->expects($this->never())->method('getSharesBy');

		$this->service->removeForCollaborator($form, $share);
	}
}
