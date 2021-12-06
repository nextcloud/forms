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
namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\Service\FormsService;

use OCA\Forms\Activity\ActivityManager;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FormsServiceTest extends TestCase {

	/** @var FormsService */
	private $formsService;

	/** @var ActivityManager|MockObject */
	private $activityManager;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var OptionMapper|MockObject */
	private $optionMapper;

	/** @var QuestionMapper|MockObject */
	private $questionMapper;

	/** @var ShareMapper|MockObject */
	private $shareMapper;

	/** @var SubmissionMapper|MockObject */
	private $submissionMapper;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var ILogger|MockObject */
	private $logger;

	/** @var IUserManager|MockObject */
	private $userManager;

	public function setUp(): void {
		parent::setUp();
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->optionMapper = $this->createMock(OptionMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$userSession = $this->createMock(IUserSession::class);

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->formsService = new FormsService(
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$userSession
		);
	}

	public function dataGetForm() {
		return [
			// Just the full form without submissions
			'one-full-form' => [[
				'id' => 42,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'description' => 'Description Text',
				'ownerId' => 'someUser',
				'created' => 123456789,
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'expires' => 0,
				'isAnonymous' => false,
				'submitOnce' => false,
				'canSubmit' => true,
				'questions' => [
					[
						'id' => 1,
						'formId' => 42,
						'order' => 1,
						'type' => 'dropdown',
						'isRequired' => false,
						'text' => 'Question 1',
						'options' => [
							[
								'id' => 1,
								'questionId' => 1,
								'text' => 'Option 1'
							],
							[
								'id' => 2,
								'questionId' => 1,
								'text' => 'Option 2'
							]
						]
					],
					[
						'id' => 2,
						'formId' => 42,
						'order' => 2,
						'type' => 'short',
						'isRequired' => true,
						'text' => 'Question 2',
						'options' => []
					]
				],
				'shares' => [
					[
						'id' => 1,
						'formId' => 42,
						'shareType' => 0,
						'shareWith' => 'user1'
					]
				]
			]]
		];
	}

	/**
	 * @dataProvider dataGetForm
	 *
	 * @param array $expected
	 */
	public function testGetForm(array $expected) {
		// The form
		$form = new Form();
		$form->setId(42);
		$form->setHash('abcdefg');
		$form->setTitle('Form 1');
		$form->setDescription('Description Text');
		$form->setOwnerId('someUser');
		$form->setCreated(123456789);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setExpires(0);
		$form->setIsAnonymous(false);
		$form->setSubmitOnce(false);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		// User & Group Formatting
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('First User');
		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method('getDisplayName')
			->willReturn('First Group');
		$this->userManager->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($user);
		$this->groupManager->expects($this->once())
			->method('get')
			->with('group1')
			->willReturn($group);

		// Questions
		$question1 = new Question();
		$question1->setId(1);
		$question1->setFormId(42);
		$question1->setOrder(1);
		$question1->setType('dropdown');
		$question1->setIsRequired(false);
		$question1->setText('Question 1');
		$question2 = new Question();
		$question2->setId(2);
		$question2->setFormId(42);
		$question2->setOrder(2);
		$question2->setType('short');
		$question2->setIsRequired(true);
		$question2->setText('Question 2');
		$this->questionMapper->expects($this->once())
			->method('findByForm')
			->with(42)
			->willReturn([$question1, $question2]);

		// Options
		$option1 = new Option();
		$option1->setId(1);
		$option1->setQuestionId(1);
		$option1->setText('Option 1');
		$option2 = new Option();
		$option2->setId(2);
		$option2->setQuestionId(1);
		$option2->setText('Option 2');
		$this->optionMapper->expects($this->any())
			->method('findByQuestion')
			->with(1)
			->willReturn([$option1, $option2]);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getForm(42));
	}

	public function dataGetPublicForm() {
		return [
			// Bare form without questions, checking removed access & ownerId
			'one-full-form' => [[
				'id' => 42,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'description' => 'Description Text',
				'created' => 123456789,
				'expires' => 0,
				'isAnonymous' => false,
				'submitOnce' => false,
				'canSubmit' => true,
				'questions' => []
			]]
		];
	}
	/**
	 * @dataProvider dataGetPublicForm
	 *
	 * @param array $expected
	 */
	public function testGetPublicForm(array $expected) {
		// The form
		$form = new Form();
		$form->setId(42);
		$form->setHash('abcdefg');
		$form->setTitle('Form 1');
		$form->setDescription('Description Text');
		$form->setOwnerId('someUser');
		$form->setCreated(123456789);
		$form->setAccess([
			'users' => ['user1'],
			'groups' => ['group1'],
			'type' => 'selected'
		]);
		$form->setExpires(0);
		$form->setIsAnonymous(false);
		$form->setSubmitOnce(false);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		// User & Group Formatting
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('First User');
		$group = $this->createMock(IGroup::class);
		$group->expects($this->once())
			->method('getDisplayName')
			->willReturn('First Group');
		$this->userManager->expects($this->once())
			->method('get')
			->with('user1')
			->willReturn($user);
		$this->groupManager->expects($this->once())
			->method('get')
			->with('group1')
			->willReturn($group);

		// No Questions here
		$this->questionMapper->expects($this->once())
			->method('findByForm')
			->with(42)
			->willReturn([]);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getPublicForm(42));
	}

	public function dataCanSubmit() {
		return [
			'publicForm' => [
				['type' => 'public'],
				'someUser',
				true,
				['currentUser'],
				true
			],
			'allowFormOwner' => [
				['type' => 'registered'],
				'currentUser',
				true,
				['currentUser'],
				true
			],
			'submitOnceGood' => [
				['type' => 'registered'],
				'someUser',
				true,
				['notCurrentUser'],
				true
			],
			'submitOnceNotGood' => [
				['type' => 'registered'],
				'someUser',
				true,
				['currentUser'],
				false
			],
			'simpleAllowed' => [
				['type' => 'registered'],
				'someUser',
				false,
				['currentUser'],
				true
			]
		];
	}
	/**
	 * @dataProvider dataCanSubmit
	 *
	 * @param array $accessArray
	 * @param string $ownerId
	 * @param bool $submitOnce
	 * @param array $participantsArray
	 * @param bool $expected
	 */
	public function testCanSubmit(array $accessArray, string $ownerId, bool $submitOnce, array $participantsArray, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setAccess($accessArray);
		$form->setOwnerId($ownerId);
		$form->setSubmitOnce($submitOnce);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->submissionMapper->expects($this->any())
			->method('findParticipantsByForm')
			->with(42)
			->willReturn($participantsArray);

		$this->assertEquals($expected, $this->formsService->canSubmit(42));
	}

	public function dataHasUserAccess() {
		return [
			'noAccess' => [
				[
					'type' => 'selected',
					'users' => [],
					'groups' => []
				],
				'someOtherUser',
				false
			],
			'publicForm' => [
				[
					'type' => 'public',
					'users' => [],
					'groups' => []
				],
				'someOtherUser',
				true
			],
			'ownerhasAccess' => [
				[
					'type' => 'selected',
					'users' => [],
					'groups' => []
				],
				'currentUser',
				true
			],
			'registeredHasAccess' => [
				[
					'type' => 'registered',
					'users' => [],
					'groups' => []
				],
				'someOtherUser',
				true
			],
			'selectedUser' => [
				[
					'type' => 'selected',
					'users' => ['user1', 'currentUser', 'user2'],
					'groups' => []
				],
				'someOtherUser',
				true
			],
			'userInSelectedGroup' => [
				[
					'type' => 'selected',
					'users' => [],
					'groups' => ['currentUserGroup']
				],
				'someOtherUser',
				true
			]

		];
	}
	/**
	 * @dataProvider dataHasUserAccess
	 *
	 * @param array $accessArray
	 * @param string $ownerId
	 * @param array $expected
	 */
	public function testHasUserAccess(array $accessArray, string $ownerId, bool $expected) {
		$form = new Form();
		$form->setAccess($accessArray);
		$form->setOwnerId($ownerId);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->groupManager->expects($this->any())
			->method('isInGroup')
			->with('currentUser', 'currentUserGroup')
			->willReturn(true);

		$this->assertEquals($expected, $this->formsService->hasUserAccess(42));
	}

	public function testHasUserAccess_NotLoggedIn() {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$formsService = new FormsService(
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$userSession
		);

		$form = new Form();
		$form->setAccess([
			'type' => 'registered',
			'users' => [],
			'groups' => []
		]);
		$form->setOwnerId('someOtherUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->assertEquals(false, $formsService->hasUserAccess(42));
	}

	public function dataHasFormExpired() {
		return [
			'hasExpired' => [time() - 3600, true],
			'hasNotExpired' => [time() + 3600, false],
			'doesNeverExpire' => [0, false]
		];
	}
	/**
	 * @dataProvider dataHasFormExpired
	 *
	 * @param int $expires
	 * @param bool $expected has expired
	 */
	public function testHasFormExpired(int $expires, bool $expected) {
		$form = new Form();
		$form->setExpires($expires);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->assertEquals($expected, $this->formsService->hasFormExpired(42));
	}

	public function dataNotifyNewShares() {
		return [
			'newUsersGroups' => [
				[
					'users' => ['user1'],
					'groups' => ['group1', 'group2']
				],
				[
					'users' => ['user1', 'user2', 'user3'],
					'groups' => ['group1', 'group2', 'group3']
				],
				['user2', 'user3'],
				['group3']
			],
			'noNewShares' => [
				[
					'users' => ['user1'],
					'groups' => ['group1', 'group2']
				],
				[
					'users' => ['user1'],
					'groups' => ['group1', 'group2']
				],
				[],
				[]
			],
			'removeShares' => [
				[
					'users' => ['user1', 'user2', 'user3'],
					'groups' => ['group1', 'group2', 'group3']
				],
				[
					'users' => ['user1'],
					'groups' => ['group1', 'group2']
				],
				[],
				[]
			],
			'noSharesAtAll' => [
				[
					'users' => [],
					'groups' => []
				],
				[
					'users' => [],
					'groups' => []
				],
				[],
				[]
			]
		];
	}
	/**
	 * @dataProvider dataNotifyNewShares
	 *
	 * @param array $oldAccess
	 * @param array $newAccess
	 * @param array $diffUsers
	 * @param array $diffGroups
	 */
	public function testNotifyNewShares(array $oldAccess, array $newAccess, array $diffUsers, array $diffGroups) {
		$form = $this->createMock(Form::class);

		$passedUserList = [];
		$this->activityManager->expects($this->any())
			->method('publishNewShare')
			->will($this->returnCallback(function ($passedForm, $passedUser) use ($form, &$passedUserList) {
				if ($passedForm === $form) {
					// Store List of passed users
					$passedUserList[] = $passedUser;
				}
			}));
		$passedGroupList = [];
		$this->activityManager->expects($this->any())
			->method('publishNewGroupShare')
			->will($this->returnCallback(function ($passedForm, $passedGroup) use ($form, &$passedGroupList) {
				if ($passedForm === $form) {
					// Store List of passed groups
					$passedGroupList[] = $passedGroup;
				}
			}));

		$this->formsService->notifyNewShares($form, $oldAccess, $newAccess);

		// Check List of called Users and Groups
		$this->assertEquals($diffUsers, $passedUserList);
		$this->assertEquals($diffGroups, $passedGroupList);
	}
}
