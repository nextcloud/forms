<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Listener;

use OCA\Forms\BackgroundJob\UserDeletedJob;

use OCA\Forms\Listener\UserDeletedListener;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class UserDeletedListenerTest extends TestCase {
	/** @var UserDeletedListener */
	private $userDeletedListener;

	/** @var IJobList|MockObject */
	private $jobList;

	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userDeletedListener = new UserDeletedListener($this->jobList, $this->logger);
	}

	public function testHandle() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('someUser');
		$event = $this->createMock(UserDeletedEvent::class);
		$event->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->jobList->expects($this->once())
			->method('add')
			->with(UserDeletedJob::class, ['owner_id' => 'someUser']);

		$this->userDeletedListener->handle($event);
	}

	public function testWrongEvent() {
		$event = $this->createMock(UserCreatedEvent::class);
		$this->jobList->expects($this->never())
			->method('add');

		$this->userDeletedListener->handle($event);
	}
}
