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
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\ConfigService;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IShare;

use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

use Psr\Log\LoggerInterface;

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

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var LoggerInterface|MockObject */
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
		$this->configService = $this->createMock(ConfigService::class);

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
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
			$this->configService,
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
				'submitMultiple' => true,
				'canSubmit' => true,
				'questions' => [
					[
						'id' => 1,
						'formId' => 42,
						'order' => 1,
						'type' => 'dropdown',
						'isRequired' => false,
						'text' => 'Question 1',
						'description' => 'This is our first question.',
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
						'description' => '',
						'options' => []
					]
				],
				'shares' => [
					[
						'id' => 1,
						'formId' => 42,
						'shareType' => 0,
						'shareWith' => 'currentUser',
						'displayName' => 'Current User'
					]
				],
				'permissions' => [
					'submit'
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
		$form->setSubmitMultiple(true);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		// User & Group Formatting
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('Current User');
		$this->userManager->expects($this->once())
			->method('get')
			->with('currentUser')
			->willReturn($user);

		// Questions
		$question1 = new Question();
		$question1->setId(1);
		$question1->setFormId(42);
		$question1->setOrder(1);
		$question1->setType('dropdown');
		$question1->setIsRequired(false);
		$question1->setText('Question 1');
		$question1->setDescription('This is our first question.');
		$question2 = new Question();
		$question2->setId(2);
		$question2->setFormId(42);
		$question2->setOrder(2);
		$question2->setType('short');
		$question2->setIsRequired(true);
		$question2->setText('Question 2');
		$question2->setDescription('');
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

		$share = new Share();
		$share->setId(1);
		$share->setFormId(42);
		$share->setShareType(0);
		$share->setShareWith('currentUser');

		$this->shareMapper->expects($this->exactly(3))
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getForm(42));
	}

	public function dataGetPartialForm() {
		return [
			'onePartialOwnedForm' => [[
				'id' => 42,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'expires' => 0,
				'permissions' => ['edit', 'results', 'submit'],
				'partial' => true
			]]
		];
	}
	/**
	 * @dataProvider dataGetPartialForm
	 *
	 * @param array $expected
	 */
	public function testGetPartialForm(array $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setHash('abcdefg');
		$form->setTitle('Form 1');
		$form->setOwnerId('currentUser');
		$form->setExpires(0);

		$this->formMapper->expects($this->exactly(2))
			->method('findById')
			->with(42)
			->willReturn($form);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getPartialFormArray(42));
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
				'submitMultiple' => true,
				'canSubmit' => true,
				'questions' => [],
				'permissions' => [
					'submit'
				]
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
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setExpires(0);
		$form->setIsAnonymous(false);
		$form->setSubmitMultiple(true);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		// User & Group Formatting
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('Current User');
		$this->userManager->expects($this->once())
			->method('get')
			->with('currentUser')
			->willReturn($user);

		// No Questions here
		$this->questionMapper->expects($this->once())
			->method('findByForm')
			->with(42)
			->willReturn([]);

		// Share exists, but should not be shown in the end.
		$share = new Share();
		$share->setId(1);
		$share->setFormId(42);
		$share->setShareType(0);
		$share->setShareWith('currentUser');

		$this->shareMapper->expects($this->exactly(3))
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getPublicForm(42));
	}

	public function dataGetPermissions() {
		return [
			'ownerHasAllPermissions' => [
				'ownerId' => 'currentUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'expected' => ['edit', 'results', 'submit'],
			],
			'allUsersCanSubmit' => [
				'ownerId' => 'someOtherUser',
				'access' => [
					'permitAllUsers' => true,
					'showToAllUsers' => false,
				],
				'expected' => ['submit'],
			],
			'noPermission' => [
				'ownerId' => 'someOtherUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'expected' => [],
			]
		];
	}
	/**
	 * @dataProvider dataGetPermissions
	 *
	 * @param string $ownerId
	 * @param array $access
	 * @param array $expected
	 */
	public function testGetPermissions(string $ownerId, array $access, array $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId($ownerId);
		$form->setAccess($access);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([]);

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(true);

		$this->assertEquals($expected, $this->formsService->getPermissions(42));
	}

	public function dataCanSubmit() {
		return [
			'allowFormOwner' => [
				'ownerId' => 'currentUser',
				'submitMultiple' => false,
				'participantsArray' => ['currentUser'],
				'expected' => true
			],
			'submitMultipleGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'participantsArray' => ['notCurrentUser'],
				'expected' => true
			],
			'submitMultipleNotGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'participantsArray' => ['notCurrentUser', 'currentUser'],
				'expected' => false
			],
			'submitMultiple' => [
				'ownerId' => 'someUser',
				'submitMultiple' => true,
				'participantsArray' => ['currentUser'],
				'expected' => true
			]
		];
	}
	/**
	 * @dataProvider dataCanSubmit
	 *
	 * @param string $ownerId
	 * @param bool $submitMultiple
	 * @param array $participantsArray
	 * @param bool $expected
	 */
	public function testCanSubmit(string $ownerId, bool $submitMultiple, array $participantsArray, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId($ownerId);
		$form->setSubmitMultiple($submitMultiple);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->submissionMapper->expects($this->any())
			->method('findParticipantsByForm')
			->with(42)
			->willReturn($participantsArray);

		$this->assertEquals($expected, $this->formsService->canSubmit(42));
	}

	/**
	 * Test result, if hasPublicLink returns true due to public link share.
	 */
	public function testPublicCanSubmit() {
		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);

		$this->formMapper->expects($this->any())
		->method('findById')
		->with(42)
		->willReturn($form);

		$share = new Share;
		$share->setShareType(IShare::TYPE_LINK);
		$this->shareMapper->expects($this->once())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		// Make sure, we don't pass the PublicLinkCheck (which would then reach 'getUID')
		$user = $this->createMock(IUser::class);
		$user->expects($this->never())
		->method('getUID');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$formsService = new FormsService(
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->configService,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$userSession
		);

		$this->assertEquals(true, $formsService->canSubmit(42));
	}

	public function dataHasPublicLink() {
		return [
			'legacyLink' => [
				'access' => [
					'legacyLink' => true,
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shareType' => IShare::TYPE_USER,
				'expected' => true,
			],
			'noPublicLink' => [
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shareType' => IShare::TYPE_USER,
				'expected' => false,
			],
			'hasPublicLink' => [
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shareType' => IShare::TYPE_LINK,
				'expected' => true,
			]
		];
	}
	/**
	 * @dataProvider dataHasPublicLink
	 *
	 * @param array $access
	 * @param int $shareType The ShareType used for this test.
	 * @param bool $expected
	 */
	public function testHasPublicLink(array $access, int $shareType, bool $expected) {
		$form = new Form;
		$form->setId(42);
		$form->setAccess($access);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$share = new Share();
		$share->setShareType($shareType);
		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->assertEquals($expected, $this->formsService->hasPublicLink(42));
	}

	public function dataHasUserAccess() {
		return [
			'ownerhasAccess' => [
				'accessArray' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'ownerId' => 'currentUser',
				'expected' => true
			],
			'allUsersPermitted' => [
				'accessArray' => [
					'permitAllUsers' => true,
					'showToAllUsers' => false,
				],
				'ownerId' => 'someOtherUser',
				'expected' => true
			],
			'noAccess' => [
				'accessArray' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'ownerId' => 'someOtherUser',
				'expected' => false
			],
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

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(true);

		$this->assertEquals($expected, $this->formsService->hasUserAccess(42));
	}

	public function testHasUserAccess_DirectShare() {
		$form = new Form();
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId('notCurrentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$share = new Share();
		$share->setShareType(IShare::TYPE_USER);
		$share->setShareWith('currentUser');
		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->assertEquals(true, $this->formsService->hasUserAccess(42));
	}

	public function testHasUserAccess_PermitAllNotAllowed() {
		$form = new Form();
		$form->setAccess([
			'permitAllUsers' => true,
			'showToAllUsers' => true,
		]);
		$form->setOwnerId('notCurrentUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->configService->expects($this->once())
			->method('getAllowPermitAll')
			->willReturn(false);

		$this->assertEquals(false, $this->formsService->hasUserAccess(42));
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
			$this->configService,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$userSession
		);

		$form = new Form();
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId('someOtherUser');

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->assertEquals(false, $formsService->hasUserAccess(42));
	}

	public function dataIsSharedFormShown() {
		return [
			'dontShowToOwner' => [
				'ownerId' => 'currentUser',
				'expires' => 0,
				'access' => [
					'permitAllUsers' => true,
					'showToAllUsers' => true,
				],
				'shareType' => IShare::TYPE_LINK,
				'expected' => false,
			],
			'expiredForm' => [
				'ownerId' => 'notCurrentUser',
				'expires' => 1,
				'access' => [
					'permitAllUsers' => true,
					'showToAllUsers' => true,
				],
				'shareType' => IShare::TYPE_LINK,
				'expected' => false,
			],
			'shownToAll' => [
				'ownerId' => 'notCurrentUser',
				'expires' => 0,
				'access' => [
					'permitAllUsers' => true,
					'showToAllUsers' => true,
				],
				'shareType' => IShare::TYPE_LINK,
				'expected' => true,
			],
			'sharedToUser' => [
				'ownerId' => 'notCurrentUser',
				'expires' => 0,
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shareType' => IShare::TYPE_USER,
				'expected' => true,
			],
			'notShown' => [
				'ownerId' => 'notCurrentUser',
				'expires' => 0,
				'access' => [
					'permitAllUsers' => true,
					'showToAllUsers' => false,
				],
				'shareType' => IShare::TYPE_LINK,
				'expected' => false,
			]
		];
	}
	/**
	 * @dataProvider dataIsSharedFormShown
	 *
	 * @param string $ownerId
	 * @param int $expires
	 * @param array $access
	 * @param int $shareType ShareType used for dummy-share here.
	 * @param bool $expected
	 */
	public function testIsSharedFormShown(string $ownerId, int $expires, array $access, int $shareType, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId($ownerId);
		$form->setExpires($expires);
		$form->setAccess($access);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(true);

		$share = new Share();
		$share->setShareType($shareType);
		$share->setShareWith('currentUser'); // Only relevant, if $shareType is TYPE_USER, otherwise it's just some 'hash'
		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->assertEquals($expected, $this->formsService->isSharedFormShown(42));
	}

	public function testIsSharedFormShown_PermitAllNotAllowed() {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId('notCurrentUser');
		$form->setExpires(false);
		$form->setAccess([
			'permitAllUsers' => true,
			'showToAllUsers' => true,
		]);

		$this->formMapper->expects($this->any())
			->method('findById')
			->with(42)
			->willReturn($form);

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(false);

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([]);

		$this->assertEquals(false, $this->formsService->isSharedFormShown(42));
	}

	public function dataIsSharedToUser() {
		return [
			'sharedToUser' => [
				'shareType' => IShare::TYPE_USER,
				'shareWith' => 'currentUser',
				'expected' => true,
			],
			'sharedToOtherUser' => [
				'shareType' => IShare::TYPE_USER,
				'shareWith' => 'NotcurrentUser',
				'expected' => false,
			],
			'sharedToGroup' => [
				'shareType' => IShare::TYPE_GROUP,
				'shareWith' => 'goodGroup',
				'expected' => true,
			],
			'sharedToOtherGroup' => [
				'shareType' => IShare::TYPE_GROUP,
				'shareWith' => 'wrongGroup',
				'expected' => false,
			],
			'NotSharedToUser' => [
				'shareType' => IShare::TYPE_LINK,
				'shareWith' => 'abcdefg',
				'expected' => false,
			],
		];
	}
	/**
	 * @dataProvider dataIsSharedToUser
	 *
	 * @param int $shareType
	 * @param string $shareWith
	 * @param bool $expected
	 */
	public function testIsSharedToUser(int $shareType, string $shareWith, bool $expected) {
		$share = new Share();
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);
		$this->shareMapper->expects($this->once())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->groupManager->expects($this->any())
			->method('isInGroup')
			->will($this->returnValueMap([
				['currentUser', 'goodGroup', true],
				['currentUser', 'wrongGroup', false],
			]));

		$this->assertEquals($expected, $this->formsService->isSharedToUser(42));
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

	public function dataGetShareDisplayName() {
		return [
			'userShare' => [
				'share' => [
					'shareType' => IShare::TYPE_USER,
					'shareWith' => 'user1',
				],
				'expected' => 'user1 UserDisplayname',
			],
			'groupShare' => [
				'share' => [
					'shareType' => IShare::TYPE_GROUP,
					'shareWith' => 'group1',
				],
				'expected' => 'group1 GroupDisplayname',
			],
			'otherShare' => [
				'share' => [
					'shareType' => IShare::TYPE_LINK,
					'shareWith' => 'abcdefg',
				],
				'expected' => '',
			],
		];
	}
	/**
	 * @dataProvider dataGetShareDisplayName
	 *
	 * @param array $share
	 * @param string $expected
	 */
	public function testGetShareDisplayName(array $share, string $expected) {
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getDisplayName')
			->willReturn($share['shareWith'] . ' UserDisplayname');
		$this->userManager->expects($this->any())
			->method('get')
			->with($share['shareWith'])
			->willReturn($user);

		$group = $this->createMock(IGroup::class);
		$group->expects($this->any())
			->method('getDisplayName')
			->willReturn($share['shareWith'] . ' GroupDisplayname');
		$this->groupManager->expects($this->any())
			->method('get')
			->with($share['shareWith'])
			->willReturn($group);

		$this->assertEquals($expected, $this->formsService->getShareDisplayName($share));
	}

	public function dataNotifyNewShares() {
		return [
			'newUserShare' => [
				'shareType' => IShare::TYPE_USER,
				'shareWith' => 'someUser',
				'expectedMethod' => 'publishNewShare',
			],
			'newGroupShare' => [
				'shareType' => IShare::TYPE_GROUP,
				'shareWith' => 'someGroup',
				'expectedMethod' => 'publishNewGroupShare',
			]
		];
	}
	/**
	 * @dataProvider dataNotifyNewShares
	 *
	 * @param int $shareType
	 * @param string $shareWith
	 * @param string $expectedMethod that will be called on activityManager.
	 */
	public function testNotifyNewShares(int $shareType, string $shareWith, string $expectedMethod) {
		$form = $this->createMock(Form::class);
		$share = new Share();
		$share->setShareType($shareType);
		$share->setShareWith($shareWith);

		$this->activityManager->expects($this->once())
			->method($expectedMethod)
			->with($form, $shareWith);

		$this->formsService->notifyNewShares($form, $share);
	}
}
