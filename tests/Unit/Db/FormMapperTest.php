<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Db;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\UploadedFileMapper;
use OCA\Forms\Helper\FilePathHelper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\UploadedFilesShareService;
use OCP\Comments\ICommentsManager;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class FormMapperTest extends TestCase {
	private FormMapper $formMapper;
	private UploadedFilesShareService|MockObject $uploadedFilesShareService;

	protected function setUp(): void {
		parent::setUp();

		$this->uploadedFilesShareService = $this->createMock(UploadedFilesShareService::class);

		$this->formMapper = $this->getMockBuilder(FormMapper::class)
			->setConstructorArgs([
				$this->createMock(IDBConnection::class),
				$this->createMock(QuestionMapper::class),
				$this->createMock(ShareMapper::class),
				$this->createMock(SubmissionMapper::class),
				$this->createMock(ConfigService::class),
				$this->createMock(ICommentsManager::class),
				$this->createMock(FilePathHelper::class),
				$this->createMock(UploadedFileMapper::class),
				$this->createMock(IRootFolder::class),
				$this->uploadedFilesShareService,
				$this->createMock(LoggerInterface::class),
			])
			->onlyMethods(['delete'])
			->getMock();
	}

	public function testDeleteFormCleansUpUploadedFilesShares(): void {
		$form = new Form();
		$form->setId(2);
		$form->setOwnerId('alice');

		$this->uploadedFilesShareService->expects($this->once())
			->method('removeAllForForm')
			->with($form);

		$this->formMapper->deleteForm($form);
	}
}
