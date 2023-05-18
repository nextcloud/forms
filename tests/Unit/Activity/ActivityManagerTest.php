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
namespace OCA\Forms\Tests\Unit\Activity;

use OCA\Forms\Activity\ActivityManager;

use OCA\Forms\Db\Form;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class ActivityManagerTest extends TestCase {

	/** @var ActivityManager */
	private $activityManager;

	/** @var IManager|MockObject */
	private $manager;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
		$userSession = $this->createMock(IUserSession::class);

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->activityManager = new ActivityManager('forms', $this->manager, $this->groupManager, $this->logger, $userSession);
	}

	public function testPublishNewShare() {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitely defined.
		$form = new Form();
		$form->setId(5);
		$form->setTitle('TestForm-Title');
		$form->setHash('abcdefg12345');
		$shareeId = 'sharedUser';

		$event = $this->createMock(IEvent::class);
		$this->manager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);
		$event->expects($this->once())->method('setApp')->with('forms')->willReturn($event);
		$event->expects($this->once())->method('setType')->with('forms_newshare')->willReturn($event);
		$event->expects($this->once())->method('setAffectedUser')->with($shareeId)->willReturn($event);
		$event->expects($this->once())->method('setAuthor')->with('currentUser')->willReturn($event);
		$event->expects($this->once())->method('setObject')->with('form', 5)->willReturn($event);
		$event->expects($this->once())->method('setSubject')->with('newshare', [
			'userId' => 'currentUser',
			'formTitle' => 'TestForm-Title',
			'formHash' => 'abcdefg12345'
		])->willReturn($event);

		$this->manager->expects($this->once())
			->method('publish')
			->with($event);

		$this->activityManager->publishNewShare($form, $shareeId);
	}

	public function testPublishNewGroupShare() {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitely defined.
		$form = new Form();
		$form->setId(5);
		$form->setTitle('TestForm-Title');
		$form->setHash('abcdefg12345');
		$groupId = 'sharedGroup';
		
		$group = $this->createMock(IGroup::class);
		$user = $this->createMock(IUser::class);

		$user->expects($this->exactly(3))
			->method('getUID')
			->will($this->onConsecutiveCalls('user1', 'user2', 'user3'));
		$group->expects($this->once())
			->method('getUsers')
			->willReturn([$user, $user, $user]);
		$this->groupManager->expects($this->once())
			->method('get')
			->with($groupId)
			->willReturn($group);

		$event = $this->createMock(IEvent::class);
		$this->manager->expects($this->exactly(3))
			->method('generateEvent')
			->willReturn($event);
		$event->expects($this->exactly(3))->method('setApp')->with('forms')->willReturn($event);
		$event->expects($this->exactly(3))->method('setType')->with('forms_newshare')->willReturn($event);
		$event->expects($this->exactly(3))->method('setAffectedUser')->withConsecutive(['user1'], ['user2'], ['user3'])->willReturn($event);
		$event->expects($this->exactly(3))->method('setAuthor')->with('currentUser')->willReturn($event);
		$event->expects($this->exactly(3))->method('setObject')->with('form', 5)->willReturn($event);
		$event->expects($this->exactly(3))->method('setSubject')->with('newgroupshare', [
			'userId' => 'currentUser',
			'formTitle' => 'TestForm-Title',
			'formHash' => 'abcdefg12345',
			'groupId' => 'sharedGroup'
		])->willReturn($event);

		$this->manager->expects($this->exactly(3))
			->method('publish')
			->with($event);

		$this->activityManager->publishNewGroupShare($form, $groupId);
	}

	public function testPublishNewSubmission() {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitely defined.
		$form = new Form();
		$form->setId(5);
		$form->setTitle('TestForm-Title');
		$form->setHash('abcdefg12345');
		$form->setOwnerId('formOwner');
		$submittorId = 'submittingUser';

		$event = $this->createMock(IEvent::class);
		$this->manager->expects($this->once())
			->method('generateEvent')
			->willReturn($event);
		$event->expects($this->once())->method('setApp')->with('forms')->willReturn($event);
		$event->expects($this->once())->method('setType')->with('forms_newsubmission')->willReturn($event);
		$event->expects($this->once())->method('setAffectedUser')->with('formOwner')->willReturn($event);
		$event->expects($this->once())->method('setAuthor')->with('submittingUser')->willReturn($event);
		$event->expects($this->once())->method('setObject')->with('form', 5)->willReturn($event);
		$event->expects($this->once())->method('setSubject')->with('newsubmission', [
			'userId' => 'submittingUser',
			'formTitle' => 'TestForm-Title',
			'formHash' => 'abcdefg12345'
		])->willReturn($event);

		$this->manager->expects($this->once())
			->method('publish')
			->with($event);

		$this->activityManager->publishNewSubmission($form, $submittorId);
	}
}
