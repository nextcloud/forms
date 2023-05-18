<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
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
