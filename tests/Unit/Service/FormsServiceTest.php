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

use OCA\Circles\Model\Circle;
use OCA\Forms\Activity\ActivityManager;

use OCA\Forms\Constants;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\CirclesService;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
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

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var ISecureRandom|MockObject */
	private $secureRandom;

	/** @var CirclesService|MockObject */
	private $circlesService;

	public function setUp(): void {
		parent::setUp();
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->optionMapper = $this->createMock(OptionMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->configService = $this->createMock(ConfigService::class);

		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->circlesService = $this->createMock(CirclesService::class);
		$userSession = $this->createMock(IUserSession::class);

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->formsService = new FormsService(
			$userSession,
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->answerMapper,
			$this->configService,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService
		);
	}

	public function testGenerateFormHash() {
		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(16, ISecureRandom::CHAR_HUMAN_READABLE)
			->willReturn('testHash');

		$this->assertEquals('testHash', $this->formsService->generateFormHash());
	}

	public function dataGetForm() {
		return [
			// Just the full form without submissions
			'one-full-form' => [[
				'id' => 42,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'description' => 'Description Text',
				'ownerId' => 'currentUser',
				'created' => 123456789,
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'expires' => 0,
				'isAnonymous' => false,
				'submitMultiple' => true,
				'allowEdit' => false,
				'showExpiration' => false,
				'lastUpdated' => 123456789,
				'canSubmit' => true,
				'submissionCount' => 123,
				'questions' => [
					[
						'id' => 1,
						'formId' => 42,
						'order' => 1,
						'type' => 'dropdown',
						'isRequired' => false,
						'extraSettings' => ['shuffleOptions' => true],
						'text' => 'Question 1',
						'description' => 'This is our first question.',
						'name' => '',
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
						'extraSettings' => [],
						'text' => 'Question 2',
						'description' => '',
						'name' => 'city',
						'options' => []
					]
				],
				'shares' => [
					[
						'id' => 1,
						'formId' => 42,
						'shareType' => 0,
						'shareWith' => 'someUser',
						'permissions' => [Constants::PERMISSION_SUBMIT],
						'displayName' => 'Some User'
					]
				],
				'submissionMessage' => '',
				'permissions' => Constants::PERMISSION_ALL
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
		$form->setOwnerId('currentUser');
		$form->setCreated(123456789);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setExpires(0);
		$form->setIsAnonymous(false);
		$form->setSubmitMultiple(true);
		$form->setAllowEdit(false);
		$form->setShowExpiration(false);
		$form->setLastUpdated(123456789);

		// User & Group Formatting
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getDisplayName')
			->willReturn('Some User');
		$this->userManager->expects($this->once())
			->method('get')
			->with('someUser')
			->willReturn($user);

		// Questions
		$question1 = new Question();
		$question1->setId(1);
		$question1->setFormId(42);
		$question1->setOrder(1);
		$question1->setType('dropdown');
		$question1->setIsRequired(false);
		$question1->setExtraSettings([
			'shuffleOptions' => true
		]);
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
		$question2->setName('city');
		$question2->setExtraSettings([]);
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
		$share->setShareWith('someUser');
		$share->setPermissions([Constants::PERMISSION_SUBMIT]);

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->submissionMapper->expects($this->once())
			->method('countSubmissions')
			->with(42)
			->willReturn(123);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getForm($form));
	}

	public function dataGetPartialForm() {
		return [
			'onePartialOwnedForm' => [[
				'id' => 42,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'expires' => 0,
				'lastUpdated' => 123456789,
				'permissions' => Constants::PERMISSION_ALL,
				'submissionCount' => 123,
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
		$form->setLastUpdated(123456789);

		$this->submissionMapper->expects($this->once())
			->method('countSubmissions')
			->with(42)
			->willReturn(123);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getPartialFormArray($form));
	}

	public function dataGetPartialFormShared() {
		return [
			'onePartialOwnedForm' => [[
				'id' => 42,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'expires' => 0,
				'lastUpdated' => 123456789,
				'permissions' => ['results', 'submit'],
				'submissionCount' => 123,
				'partial' => true
			]]
		];
	}
	/**
	 * Make sure shared users with results permission also receive the submission count
	 * @dataProvider dataGetPartialFormShared
	 *
	 * @param array $expected
	 */
	public function testGetPartialFormShared(array $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setHash('abcdefg');
		$form->setTitle('Form 1');
		$form->setOwnerId('otherUser');
		$form->setExpires(0);
		$form->setLastUpdated(123456789);

		$share = new Share();
		$share->setFormId(42);
		$share->setPermissions([Constants::PERMISSION_RESULTS, Constants::PERMISSION_SUBMIT]);
		$share->setShareType(IShare::TYPE_USER);
		$share->setShareWith('currentUser');

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->submissionMapper->expects($this->once())
			->method('countSubmissions')
			->with(42)
			->willReturn(123);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getPartialFormArray($form));
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
				'lastUpdated' => 123456789,
				'isAnonymous' => false,
				'submitMultiple' => true,
				'allowEdit' => false,
				'showExpiration' => false,
				'canSubmit' => true,
				'questions' => [],
				'permissions' => [
					'submit'
				],
				'submissionMessage' => '',
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
		$form->setLastUpdated(123456789);
		$form->setIsAnonymous(false);
		$form->setSubmitMultiple(true);
		$form->setAllowEdit(false);
		$form->setShowExpiration(false);

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
		$this->assertEquals($expected, $this->formsService->getPublicForm($form));
	}

	public function dataGetPermissions() {
		return [
			'ownerHasAllPermissions' => [
				'ownerId' => 'currentUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shares' => [],
				'expected' => Constants::PERMISSION_ALL,
			],
			'allUsersCanSubmit' => [
				'ownerId' => 'someOtherUser',
				'access' => [
					'permitAllUsers' => true,
					'showToAllUsers' => false,
				],
				'shares' => [],
				'expected' => ['submit'],
			],
			'noPermission' => [
				'ownerId' => 'someOtherUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shares' => [],
				'expected' => [],
			],
			'submitByShare' => [
				'ownerId' => 'someOtherUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shares' => [[
					'permissions' => ['submit']
				]],
				'expected' => ['submit'],
			],
			'submitResultsByShare' => [
				'ownerId' => 'someOtherUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'shares' => [
					['permissions' => ['submit']],
					['permissions' => ['submit', 'results']]
				],
				'expected' => ['submit', 'results'],
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
	public function testGetPermissions(string $ownerId, array $access, array $shares, array $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId($ownerId);
		$form->setAccess($access);

		$sharesEntities = [];
		$shareId = 0;
		foreach ($shares as $share) {
			$shareEntity = new Share();
			$shareEntity->setId($shareId++);
			$shareEntity->setFormId(42);
			$shareEntity->setShareType(IShare::TYPE_USER);
			$shareEntity->setShareWith('currentUser');
			$shareEntity->setPermissions($share['permissions']);
			$sharesEntities[] = $shareEntity;
		}

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn($sharesEntities);

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(true);

		$this->assertEquals($expected, $this->formsService->getPermissions($form));
	}

	// No currentUser on public views.
	public function testGetPermissions_NotLoggedIn() {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$formsService = new FormsService(
			$userSession,
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->answerMapper,
			$this->configService,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService
		);

		$form = new Form();
		$form->setId(42);

		$this->assertEquals([], $formsService->getPermissions($form));
	}

	public function dataCanSeeResults() {
		return [
			'allowFormOwner' => [
				'ownerId' => 'currentUser',
				'sharesArray' => [],
				'expected' => true
			],
			'allowShared' => [
				'ownerId' => 'someUser',
				'sharesArray' => [[
					'with' => 'currentUser',
					'type' => 0,
					'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS],
				]],
				'expected' => true
			],
			'disallowNotowned' => [
				'ownerId' => 'someUser',
				'sharesArray' => [],
				'expected' => false
			],
			'allowNotShared' => [
				'ownerId' => 'someUser',
				'sharesArray' => [[
					'with' => 'currentUser',
					'type' => 0,
					'permissions' => [Constants::PERMISSION_SUBMIT],
				]],
				'expected' => false
			]
		];
	}
	/**
	 * @dataProvider dataCanSeeResults
	 *
	 * @param string $ownerId
	 * @param array $sharesArray
	 * @param bool $expected
	 */
	public function testCanSeeResults(string $ownerId, array $sharesArray, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId($ownerId);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);

		$shares = [];
		foreach ($sharesArray as $id => $share) {
			$shareEntity = new Share();
			$shareEntity->setId($id);
			$shareEntity->setFormId(42);
			$shareEntity->setShareType($share['type']);
			$shareEntity->setShareWith($share['with']);
			$shareEntity->setPermissions($share['permissions']);
			$shares[] = $shareEntity;
		}

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn($shares);

		$this->assertEquals($expected, $this->formsService->canSeeResults($form));
	}

	public function dataCanDeleteResults() {
		return [
			'allowFormOwner' => [
				'ownerId' => 'currentUser',
				'sharesArray' => [],
				'expected' => true
			],
			'allowShared' => [
				'ownerId' => 'someUser',
				'sharesArray' => [[
					'with' => 'currentUser',
					'type' => 0,
					'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS, Constants::PERMISSION_RESULTS_DELETE],
				]],
				'expected' => true
			],
			'disallowNotowned' => [
				'ownerId' => 'someUser',
				'sharesArray' => [],
				'expected' => false
			],
			'allowNotShared' => [
				'ownerId' => 'someUser',
				'sharesArray' => [[
					'with' => 'currentUser',
					'type' => 0,
					'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS],
				]],
				'expected' => false
			]
		];
	}
	/**
	 * @dataProvider dataCanDeleteResults
	 *
	 * @param string $ownerId
	 * @param array $sharesArray
	 * @param bool $expected
	 */
	public function testCanDeleteResults(string $ownerId, array $sharesArray, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId($ownerId);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);

		$shares = [];
		foreach ($sharesArray as $id => $share) {
			$shareEntity = new Share();
			$shareEntity->setId($id);
			$shareEntity->setFormId(42);
			$shareEntity->setShareType($share['type']);
			$shareEntity->setShareWith($share['with']);
			$shareEntity->setPermissions($share['permissions']);
			$shares[] = $shareEntity;
		}

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn($shares);

		$this->assertEquals($expected, $this->formsService->canDeleteResults($form));
	}

	public function dataCanSubmit() {
		return [
			'allowFormOwner' => [
				'ownerId' => 'currentUser',
				'submitMultiple' => false,
				'allowEdit' => false,
				'participantsArray' => ['currentUser'],
				'expected' => true
			],
			'submitMultipleGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'allowEdit' => false,
				'participantsArray' => ['notCurrentUser'],
				'expected' => true
			],
			'submitMultipleNotGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'allowEdit' => false,
				'participantsArray' => ['notCurrentUser', 'currentUser'],
				'expected' => false
			],
			'submitMultiple' => [
				'ownerId' => 'someUser',
				'submitMultiple' => true,
				'allowEdit' => false,
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
	 * @param bool $allowEdit
	 * @param array $participantsArray
	 * @param bool $expected
	 */
	public function testCanSubmit(string $ownerId, bool $submitMultiple, bool $allowEdit, array $participantsArray, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId($ownerId);
		$form->setSubmitMultiple($submitMultiple);
		$form->setAllowEdit($allowEdit);

		$this->submissionMapper->expects($this->any())
			->method('findParticipantsByForm')
			->with(42)
			->willReturn($participantsArray);

		$this->assertEquals($expected, $this->formsService->canSubmit($form));
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
			$userSession,
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->answerMapper,
			$this->configService,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService
		);

		$this->assertEquals(true, $formsService->canSubmit($form));
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

		$share = new Share();
		$share->setShareType($shareType);
		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->assertEquals($expected, $this->formsService->hasPublicLink($form));
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
		$form->setId(42);
		$form->setAccess($accessArray);
		$form->setOwnerId($ownerId);

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(true);

		$this->assertEquals($expected, $this->formsService->hasUserAccess($form));
	}

	public function testHasUserAccess_DirectShare() {
		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId('notCurrentUser');

		$share = new Share();
		$share->setShareType(IShare::TYPE_USER);
		$share->setShareWith('currentUser');
		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->assertEquals(true, $this->formsService->hasUserAccess($form));
	}

	public function testHasUserAccess_PermitAllNotAllowed() {
		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => true,
			'showToAllUsers' => true,
		]);
		$form->setOwnerId('notCurrentUser');

		$this->configService->expects($this->once())
			->method('getAllowPermitAll')
			->willReturn(false);

		$this->assertEquals(false, $this->formsService->hasUserAccess($form));
	}

	public function testHasUserAccess_NotLoggedIn() {
		$userSession = $this->createMock(IUserSession::class);
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$formsService = new FormsService(
			$userSession,
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->answerMapper,
			$this->configService,
			$this->groupManager,
			$this->logger,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService
		);

		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId('someOtherUser');

		$this->assertEquals(false, $formsService->hasUserAccess($form));
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

		$this->assertEquals($expected, $this->formsService->isSharedFormShown($form));
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

		$this->configService->expects($this->any())
			->method('getAllowPermitAll')
			->willReturn(false);

		$this->shareMapper->expects($this->any())
			->method('findByForm')
			->with(42)
			->willReturn([]);

		$this->assertEquals(false, $this->formsService->isSharedFormShown($form));
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
			'sharedToCircle' => [
				'shareType' => IShare::TYPE_CIRCLE,
				'shareWith' => 'goodCircle',
				'expected' => true,
			],
			'sharedToOtherCircle' => [
				'shareType' => IShare::TYPE_CIRCLE,
				'shareWith' => 'wrongCircle',
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

		$this->groupManager->expects($shareType === IShare::TYPE_GROUP ? $this->once() : $this->never())
			->method('isInGroup')
			->will($this->returnValueMap([
				['currentUser', 'goodGroup', true],
				['currentUser', 'wrongGroup', false],
			]));

		$this->circlesService->expects($shareType === IShare::TYPE_CIRCLE ? $this->once() : $this->never())
			->method('isUserInCircle')
			->willReturnCallback(function ($id, $user) {
				return $id === 'goodCircle';
			});

		$this->assertEquals($expected, $this->formsService->isSharedToUser(42));
	}

	public function testIsSharedToCircle_circlesDisabled() {
		$this->circlesService->expects($this->once())
			->method('isUserInCircle')
			->with('circle1', 'currentUser')
			->willReturn(false);

		$share = new Share();
		$share->setShareType(IShare::TYPE_CIRCLE);
		$share->setShareWith('circle1');
		$this->shareMapper->expects($this->once())
			->method('findByForm')
			->with(42)
			->willReturn([$share]);

		$this->assertEquals(false, $this->formsService->isSharedToUser(42));
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

		$this->assertEquals($expected, $this->formsService->hasFormExpired($form));
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
			'circleShare' => [
				'share' => [
					'shareType' => IShare::TYPE_CIRCLE,
					'shareWith' => 'circle1',
				],
				'expected' => 'circle1 CircleDisplayname',
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
		$user->expects($share['shareType'] === IShare::TYPE_USER ? $this->once() : $this->never())
			->method('getDisplayName')
			->willReturn($share['shareWith'] . ' UserDisplayname');
		$this->userManager->expects($share['shareType'] === IShare::TYPE_USER ? $this->once() : $this->never())
			->method('get')
			->with($share['shareWith'])
			->willReturn($user);

		$group = $this->createMock(IGroup::class);
		$group->expects($share['shareType'] === IShare::TYPE_GROUP ? $this->once() : $this->never())
			->method('getDisplayName')
			->willReturn($share['shareWith'] . ' GroupDisplayname');
		$this->groupManager->expects($share['shareType'] === IShare::TYPE_GROUP ? $this->once() : $this->never())
			->method('get')
			->with($share['shareWith'])
			->willReturn($group);

		$circle = $this->createMock(Circle::class);
		$circle->expects($share['shareType'] === IShare::TYPE_CIRCLE ? $this->once() : $this->never())
			->method('getDisplayName')
			->willReturn($share['shareWith'] . ' CircleDisplayname');
		$this->circlesService->expects($share['shareType'] === IShare::TYPE_CIRCLE ? $this->once() : $this->never())
			->method('getCircle')
			->with($share['shareWith'])
			->willReturn($circle);

		$this->assertEquals($expected, $this->formsService->getShareDisplayName($share));
	}

	public function testGetCircleShareDisplayName_circlesDisabled() {
		$this->circlesService->expects($this->once())
			->method('getCircle')
			->willReturn(null);

		$share = [
			'shareType' => IShare::TYPE_CIRCLE,
			'shareWith' => 'circle1',
		];

		$this->assertEquals('', $this->formsService->getShareDisplayName($share));
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
			],
			'newCircleShare' => [
				'shareType' => IShare::TYPE_CIRCLE,
				'shareWith' => 'someCircle',
				'expectedMethod' => 'publishNewCircleShare',
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

	public function dataNotifyNewSubmission() {
		return [
			'no-shares' => [
				[],
				0
			],
			'one-share' => [
				[[
					'shareWith' => 'user',
					'shareType' => IShare::TYPE_USER,
					'permissions' => [ Constants::PERMISSION_RESULTS ]
				]],
				1
			],
			'one-invalid-share' => [
				[[
					'shareWith' => 'user',
					'shareType' => IShare::TYPE_USER,
					'permissions' => [ Constants::PERMISSION_SUBMIT ]
				]],
				0
			],
			'mixed-shares' => [
				[
					[
						'shareWith' => 'user',
						'shareType' => IShare::TYPE_USER,
						'permissions' => [ Constants::PERMISSION_SUBMIT ]
					],
					[
						'shareWith' => 'user2',
						'shareType' => IShare::TYPE_USER,
						'permissions' => [ Constants::PERMISSION_RESULTS ]
					]],
				1
			],
		];
	}

	/**
	 * Test creating notifications for new submissions
	 *
	 * @dataProvider dataNotifyNewSubmission
	 */
	public function testNotifyNewSubmission($shares, $shareNotifications) {
		$owner = 'ownerUser';
		$submitter = 'someUser';

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$formsService = $this->getMockBuilder(FormsService::class)
			->onlyMethods(['getShares'])
			->setConstructorArgs([
				$userSession,
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
				$this->secureRandom,
				$this->circlesService,
			])
			->getMock();

		$form = Form::fromParams(['id' => 42, 'ownerId' => $owner]);

		$formsService->method('getShares')->willReturn($shares);
		
		$this->activityManager->expects($this->once())
			->method('publishNewSubmission')
			->with($form, $submitter);

		$this->activityManager->expects($this->exactly($shareNotifications))
			->method('publishNewSharedSubmission');

		$formsService->notifyNewSubmission($form, $submitter);
	}

	/**
	 * @dataProvider dataAreExtraSettingsValid
	 *
	 * @param array $extraSettings input settings
	 * @param string $questionType question type
	 * @param bool $expected expected return value
	 *
	 */
	public function testAreExtraSettingsValid(array $extraSettings, string $questionType, bool $expected) {
		$this->assertEquals($expected, $this->formsService->areExtraSettingsValid($extraSettings, $questionType));
	}

	public function dataAreExtraSettingsValid() {
		return [
			'empty-extra-settings' => [
				'extraSettings' => [],
				'questionType' => Constants::ANSWER_TYPE_LONG,
				'expected' => true
			],
			'invalid key' => [
				'extraSettings' => [
					'some' => 'value'
				],
				'questionType' => Constants::ANSWER_TYPE_LONG,
				'expected' => false
			],
			'valid key' => [
				'extraSettings' => [
					'shuffleOptions' => true
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => true
			],
			'invalid subtype' => [
				'extraSettings' => [
					'validationType' => 'iban',
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => false
			],
			'valid-custom-regex' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '/^[a-z]{3,}/m'
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => true
			],
			'valid-custom-empty-regex' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => ''
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => true
			],
			'invalid-custom-regex-modifier' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '/^[a-z]{3,}/gm'
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => false
			],
			'invalid-custom-regex' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '['
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'rval' => false
			],
			'invalid-custom-regex-delimiters' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '/1/2/'
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'rval' => false
			],
			'invalid-custom-regex-pattern' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '/'.'[/'
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'rval' => false
			],
			'invalid-custom-regex-type' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => 112
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'rval' => false
			],
			'invalid-custom-missing-regex' => [
				'extraSettings' => [
					'validationType' => 'regex',
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'rval' => false
			],
			'valid-dropdown-settings' => [
				'extraSettings' => [
					'shuffleOptions' => false,
				],
				'questionType' => Constants::ANSWER_TYPE_DROPDOWN,
				'expected' => true
			],
			'invalid-dropdown-settings' => [
				'extraSettings' => [
					'shuffleOptions' => true,
					'someInvalidOption' => true
				],
				'questionType' => Constants::ANSWER_TYPE_DROPDOWN,
				'expected' => false
			]
		];
	}
}
