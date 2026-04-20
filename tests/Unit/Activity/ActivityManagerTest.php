<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Activity;

use OCA\Forms\Activity\ActivityManager;
use OCA\Forms\Db\Form;
use OCA\Forms\Service\CirclesService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ActivityManagerTest extends TestCase {

	private ActivityManager $activityManager;
	private IManager&MockObject $manager;
	private IGroupManager&MockObject $groupManager;
	private CirclesService&MockObject $circlesService;

	public function setUp(): void {
		parent::setUp();
		$this->manager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->circlesService = $this->createMock(CirclesService::class);

		$this->activityManager = new ActivityManager('forms', 'currentUser', $this->manager, $this->groupManager, $this->circlesService);
	}

	public function testPublishNewShare() {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitly defined.
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
		// Can't mock the DB-Classes, as their Property-Methods are not explicitly defined.
		$form = new Form();
		$form->setId(5);
		$form->setTitle('TestForm-Title');
		$form->setHash('abcdefg12345');
		$groupId = 'sharedGroup';

		$group = $this->createMock(IGroup::class);
		$users = [
			$this->createMock(IUser::class),
			$this->createMock(IUser::class),
			$this->createMock(IUser::class)
		];

		$users[0]->method('getUID')->willReturn('user1');
		$users[1]->method('getUID')->willReturn('user2');
		$users[2]->method('getUID')->willReturn('user3');
		$group->expects($this->once())
			->method('getUsers')
			->willReturn($users);
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
		$event->expects($this->exactly(3))->method('setAuthor')->with('currentUser')->willReturn($event);
		$event->expects($this->exactly(3))->method('setObject')->with('form', 5)->willReturn($event);
		$event->expects($this->exactly(3))->method('setSubject')->with('newgroupshare', [
			'userId' => 'currentUser',
			'formTitle' => 'TestForm-Title',
			'formHash' => 'abcdefg12345',
			'groupId' => 'sharedGroup'
		])->willReturn($event);

		$affectedUsers = [];
		$event->expects($this->exactly(3))
			->method('setAffectedUser')
			->willReturnCallback(function ($userId) use (&$affectedUsers, &$event) {
				$affectedUsers[] = $userId;
				return $event;
			});

		$this->manager->expects($this->exactly(3))
			->method('publish')
			->with($event);

		$this->activityManager->publishNewGroupShare($form, $groupId);
		$this->assertEquals(['user1', 'user2', 'user3'], $affectedUsers);
	}

	public function testPublishNewCircleShare() {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitly defined.
		$form = new Form();
		$form->setId(5);
		$form->setTitle('TestForm-Title');
		$form->setHash('abcdefg12345');
		$circleId = 'sharedCircle';

		$this->circlesService->expects($this->once())
			->method('getCircleUsers')
			->with($circleId)
			->willReturn(['userId', 'user1', 'user2', 'user3']);
		$event = $this->createMock(IEvent::class);
		$this->manager->expects($this->exactly(4))
			->method('generateEvent')
			->willReturn($event);
		$event->expects($this->exactly(4))->method('setApp')->with('forms')->willReturn($event);
		$event->expects($this->exactly(4))->method('setType')->with('forms_newshare')->willReturn($event);
		$event->expects($this->exactly(4))->method('setAuthor')->with('currentUser')->willReturn($event);
		$event->expects($this->exactly(4))->method('setObject')->with('form', 5)->willReturn($event);
		$event->expects($this->exactly(4))->method('setSubject')->with('newcircleshare', [
			'userId' => 'currentUser',
			'formTitle' => 'TestForm-Title',
			'formHash' => 'abcdefg12345',
			'circleId' => $circleId
		])->willReturn($event);
		$affectedUsers = [];
		$event->expects($this->exactly(4))
			->method('setAffectedUser')
			->willReturnCallback(function (string $userId) use (&$affectedUsers, &$event) {
				$affectedUsers[] = $userId;
				return $event;
			});

		$this->manager->expects($this->exactly(4))
			->method('publish')
			->with($event);

		$this->activityManager->publishNewCircleShare($form, $circleId);
		$this->assertEquals(['userId', 'user1', 'user2', 'user3'], $affectedUsers);
	}

	public function testPublishNewCircleShare_circlesDisabled() {
		$form = $this->createMock(Form::class);

		$this->circlesService->expects($this->once())
			->method('getCircleUsers')
			->with('circle')
			->willReturn([]);

		$this->manager->expects($this->never())
			->method('generateEvent');

		$this->activityManager->publishNewCircleShare($form, 'circle');
	}

	public function testPublishNewSubmission() {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitly defined.
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

	public static function dataPublishNewSharedSubmission() {
		return [
			'user-share' => [
				'shareType' => IShare::TYPE_USER,
				'shareWith' => 'sharedUser',
				'expected' => [['sharedUser']],
				'sharedUsers' => ['sharedUser'],
			],
			'group-share' => [
				IShare::TYPE_GROUP,
				'sharedGroup',
				[['user1'], ['user2']],
				['user1', 'user2'],
			],
			'circle-share' => [
				IShare::TYPE_CIRCLE,
				'sharedCircle',
				[['user1'], ['user2']],
				['user1', 'user2'],
			],
		];
	}

	/**
	 * Test notify shared results
	 */
	#[DataProvider('dataPublishNewSharedSubmission')]
	public function testPublishNewSharedSubmission(int $shareType, string $shareWith, array $expected, ?array $sharedUsers = null) {
		// Can't mock the DB-Classes, as their Property-Methods are not explicitely defined.
		$form = new Form();
		$form->setId(5);
		$form->setTitle('TestForm-Title');
		$form->setHash('abcdefg12345');
		$form->setOwnerId('formOwner');
		$submitterId = 'submittingUser';

		if ($shareType === IShare::TYPE_CIRCLE) {
			$this->circlesService->expects($this->once())
				->method('getCircleUsers')
				->with('sharedCircle')
				->willReturn($sharedUsers);
		} elseif ($shareType === IShare::TYPE_GROUP) {
			$users = array_map(function ($name) {
				$user = $this->createMock(IUser::class);
				$user->expects($this->once())->method('getUID')->willReturn($name);
				return $user;
			}, $sharedUsers);
			$group = $this->createMock(IGroup::class);
			$group->expects($this->once())->method('getUsers')->willReturn($users);
			$this->groupManager->expects($this->once())->method('get')->willReturn($group);
		}

		$event = $this->createMock(IEvent::class);
		$this->manager->expects($this->exactly(count($expected)))
			->method('generateEvent')
			->willReturn($event);
		$event->expects($this->exactly(count($expected)))->method('setApp')->with('forms')->willReturn($event);
		$event->expects($this->exactly(count($expected)))->method('setType')->with('forms_newsharedsubmission')->willReturn($event);
		$event->expects($this->exactly(count($expected)))->method('setAuthor')->with('submittingUser')->willReturn($event);
		$event->expects($this->exactly(count($expected)))->method('setObject')->with('form', 5)->willReturn($event);
		$event->expects($this->exactly(count($expected)))->method('setSubject')->with('newsubmission', [
			'userId' => 'submittingUser',
			'formTitle' => 'TestForm-Title',
			'formHash' => 'abcdefg12345'
		])->willReturn($event);
		$affectedUsers = [];
		$event->expects($this->exactly(count($sharedUsers)))
			->method('setAffectedUser')
			->willReturnCallback(function (string $userId) use (&$affectedUsers, &$event) {
				$affectedUsers[] = $userId;
				return $event;
			});

		$this->manager->expects($this->exactly(count($expected)))
			->method('publish')
			->with($event);

		$this->activityManager->publishNewSharedSubmission($form, $shareType, $shareWith, $submitterId);
		$this->assertEquals($sharedUsers, $affectedUsers);
	}
}
