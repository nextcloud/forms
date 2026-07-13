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
		$filePathHelper = new FilePathHelper($this->rootFolder);

		$this->service = new UploadedFilesShareService(
			$this->rootFolder,
			$filePathHelper,
			$this->shareManager,
			$this->shareMapper,
			$this->createMock(LoggerInterface::class),
		);
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
		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('get')
			->with('Forms/2 - test')
			->willReturn($folder);
		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('alice')
			->willReturn($userFolder);

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
}
