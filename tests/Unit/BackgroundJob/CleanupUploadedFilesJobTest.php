<?php

declare(strict_types=1);
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
namespace OCA\Forms\Tests\Unit\BackgroundJob;

use OCA\Forms\BackgroundJob\CleanupUploadedFilesJob;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\UploadedFile;
use OCA\Forms\Db\UploadedFileMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CleanupUploadedFilesJobTest extends TestCase {
	/** @var IRootFolder|MockObject */
	private $rootFolder;

	/** @var CleanupUploadedFilesJob */
	private $cleanupUploadedFilesJob;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var UploadedFileMapper|MockObject */
	private $uploadedFileMapper;

	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->uploadedFileMapper = $this->createMock(UploadedFileMapper::class);
		$time = $this->createMock(ITimeFactory::class);
		$this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
		$this->cleanupUploadedFilesJob = new CleanupUploadedFilesJob(
			$this->rootFolder,
			$this->formMapper,
			$this->uploadedFileMapper,
			$this->logger,
			$time,
		);
	}

	public function testHandle() {
		$form = new Form();
		$form->setOwnerId('someUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->willReturn($form);

		$uploadedFile = new UploadedFile();
		$uploadedFile->setOriginalFileName('test.txt');
		$uploadedFile->setFormId(1);
		$uploadedFile->setFileId(127);
		$uploadedFile->setCreated(1711996822);

		$this->uploadedFileMapper->expects($this->once())
			->method('findUploadedEarlierThan')
			->willReturn([$uploadedFile]);

		$this->rootFolder->expects($this->atLeastOnce())
			->method('getUserFolder')
			->willReturn($this->rootFolder);

		$this->cleanupUploadedFilesJob->run([]);
	}
}
