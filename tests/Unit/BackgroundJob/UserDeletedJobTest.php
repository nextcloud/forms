<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\BackgroundJob;

use OCA\Forms\BackgroundJob\UserDeletedJob;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class UserDeletedJobTest extends TestCase {
	/** @var UserDeletedJob */
	private $userDeletedJob;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->formMapper = $this->createMock(FormMapper::class);
		$time = $this->createMock(ITimeFactory::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userDeletedJob = new UserDeletedJob($this->formMapper, $time, $this->logger);
	}

	public function testHandle() {
		$form = $this->createMock(Form::class);
		$this->formMapper->expects($this->once())
			->method('findAllByOwnerId')
			->willReturn([$form, $form, $form]);
		$this->formMapper->expects($this->exactly(3))
			->method('deleteForm')
			->with($form);

		$this->userDeletedJob->run(['owner_id' => 'someUser']);
	}
}
