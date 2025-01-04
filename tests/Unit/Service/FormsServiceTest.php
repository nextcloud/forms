<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

/**
 * mock microtime() function used in services
 * @param float|false|null $expected the value that should be returned when called
 */
function microtime(bool|float $asFloat = false) {
	static $value;
	if ($asFloat === -1) {
		$value = null;
	} elseif (is_numeric($asFloat)) {
		$value = $asFloat;
	}
	// Return real time if no mocked value is set
	if (is_null($value)) {
		return \microtime($asFloat);
	}
	return $value;
}

namespace OCA\Forms\Tests\Unit\Service;

use OCA\Circles\Model\Circle;
use OCA\Forms\Activity\ActivityManager;

use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\CirclesService;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;

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

	/** @var AnswerMapper|MockObject */
	private $answerMapper;

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var ISecureRandom|MockObject */
	private $secureRandom;

	/** @var CirclesService|MockObject */
	private $circlesService;

	/** @var IRootFolder|MockObject */
	private $storage;

	/** @var IL10N|MockObject */
	private $l10n;

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

		$this->storage = $this->createMock(IRootFolder::class);

		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->expects($this->any())
			->method('t')
			->will($this->returnCallback(function (string $identity) {
				return $identity;
			}));

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
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			\OCP\Server::get(IEventDispatcher::class),
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
				'state' => 0,
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
								'text' => 'Option 1',
								'order' => null,
							],
							[
								'id' => 2,
								'questionId' => 1,
								'text' => 'Option 2',
								'order' => null,
							]
						],
						'accept' => [],
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
						'options' => [],
						'accept' => [],
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
				'submissionMessage' => null,
				'fileId' => null,
				'fileFormat' => null,
				'permissions' => Constants::PERMISSION_ALL,
				'allowEdit' => false,
			]]
		];
	}

	private function prepareFormTest(bool $withUserMock) {
		// The form
		$form = new Form();
		$form->setId(42);
		$form->setState(0); // default => 0 means active
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
		$form->setShowExpiration(false);
		$form->setLastUpdated(123456789);

		// User & Group Formatting
		$user = $this->createMock(IUser::class);
		if ($withUserMock) {
			$user->expects($this->once())
				->method('getDisplayName')
				->willReturn('Some User');
			$this->userManager->expects($this->once())
				->method('get')
				->with('someUser')
				->willReturn($user);
		}

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

		$answerEntity = new Answer();
		$answerEntity->setId(1);
		$answerEntity->setSubmissionId(12);
		$answerEntity->setFileId(112);
		$answerEntity->setQuestionId(1);
		$answerEntity->setText('Option 1');
		$answer2Entity = new Answer();
		$answer2Entity->setId(2);
		$answer2Entity->setSubmissionId(12);
		$answer2Entity->setQuestionId(2);
		$answer2Entity->setText('London');
		$answers = [ $answerEntity, $answer2Entity ];

		return [$form, $user, $share, $answers];
	}

	/**
	 * @dataProvider dataGetForm
	 *
	 * @param array $expected
	 */
	public function testGetForm(array $expected) {
		[$form, $user, $share, $answers] = $this->prepareFormTest(true);

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

	public function dataGetFormWithAnswers() {
		return [
			// Just the full form with answers for AllowEdit
			'one-full-form-with-answers' => [[
				'id' => 42,
				'state' => 0,
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
				'submitMultiple' => false,
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
								'text' => 'Option 1',
								'order' => null,
							],
							[
								'id' => 2,
								'questionId' => 1,
								'text' => 'Option 2',
								'order' => null,
							]
						],
						'accept' => [],
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
						'options' => [],
						'accept' => [],
					]
				],
				'shares' => [
				],
				'answers' => [
					1 => [ 0 => '1' ],
					2 => [ 0 => 'London' ],
				],
				'submissionMessage' => null,
				'fileId' => null,
				'fileFormat' => null,
				'permissions' => Constants::PERMISSION_ALL,
				'allowEdit' => true,
				'newSubmission' => false,
				'submissionId' => 12,
			]]
		];
	}

	/**
	 * @dataProvider dataGetFormWithAnswers
	 *
	 * @param array $expected
	 */
	public function testGetFormAllowEditWithAnswers(array $expected) {
		[$form, $user, $share, $answers] = $this->prepareFormTest(false);

		// The form, with AllowEdit
		$form->setSubmitMultiple(false);
		$form->setAllowEdit(true);

		$submission = new Submission();
		$submission->setId(12);

		$this->submissionMapper->expects($this->once())
			->method('findByFormAndUser')
			->with(42, 'currentUser')
			->willReturn($submission);

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(12)
			->willReturn($answers);

		$this->submissionMapper->expects($this->once())
			->method('countSubmissions')
			->with(42)
			->willReturn(123);

		// Run the test
		$this->assertEquals($expected, $this->formsService->getForm($form));
	}

	/**
	 * @dataProvider dataGetFormWithAnswers
	 *
	 * @param array $expected
	 */
	public function testGetFormAllowEditWithoutSubmission(array $expected) {
		// drop the submissions and answers for this test
		unset($expected['answers']);
		unset($expected['newSubmission']);
		unset($expected['submissionId']);
		[$form, $user, $share, $answers] = $this->prepareFormTest(false);

		// The form, with AllowEdit
		$form->setSubmitMultiple(false);
		$form->setAllowEdit(true);

		$this->submissionMapper->expects($this->once())
			->method('findByFormAndUser')
			->with(42, 'currentUser')
			->willThrowException(new DoesNotExistException('Test exception'));

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
				'state' => 0,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'expires' => 0,
				'lastUpdated' => 123456789,
				'permissions' => Constants::PERMISSION_ALL,
				'submissionCount' => 123,
				'partial' => true,
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
		$form->setState(0);
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
				'state' => 0,
				'partial' => true,
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
		$form->setState(0);
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
				'state' => 0,
				'hash' => 'abcdefg',
				'title' => 'Form 1',
				'description' => 'Description Text',
				'created' => 123456789,
				'expires' => 0,
				'lastUpdated' => 123456789,
				'isAnonymous' => false,
				'submitMultiple' => true,
				'showExpiration' => false,
				'canSubmit' => true,
				'questions' => [],
				'permissions' => [
					'submit'
				],
				'submissionMessage' => null,
				'allowEdit' => false,
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
		$form->setState(0);
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
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			\OCP\Server::get(IEventDispatcher::class),
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
				'formArchived' => false,
				'expected' => true
			],
			'disallowArchivedForm' => [
				'ownerId' => 'currentUser',
				'sharesArray' => [],
				'formArchived' => true,
				'expected' => false
			],
			'allowShared' => [
				'ownerId' => 'someUser',
				'sharesArray' => [[
					'with' => 'currentUser',
					'type' => 0,
					'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS, Constants::PERMISSION_RESULTS_DELETE],
				]],
				'formArchived' => false,
				'expected' => true
			],
			'disallowNotowned' => [
				'ownerId' => 'someUser',
				'sharesArray' => [],
				'formArchived' => false,
				'expected' => false
			],
			'allowNotShared' => [
				'ownerId' => 'someUser',
				'sharesArray' => [[
					'with' => 'currentUser',
					'type' => 0,
					'permissions' => [Constants::PERMISSION_SUBMIT, Constants::PERMISSION_RESULTS],
				]],
				'formArchived' => false,
				'expected' => false
			]
		];
	}
	/**
	 * @dataProvider dataCanDeleteResults
	 *
	 * @param string $ownerId
	 * @param array $sharesArray
	 * @param bool $formArchived
	 * @param bool $expected
	 */
	public function testCanDeleteResults(string $ownerId, array $sharesArray, bool $formArchived, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId($ownerId);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setState($formArchived?Constants::FORM_STATE_ARCHIVED:Constants::FORM_STATE_ACTIVE);

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
				'hasFormSubmissionsByUser' => true,
				'expected' => true
			],
			'submitMultipleGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'allowEdit' => false,
				'hasFormSubmissionsByUser' => false,
				'expected' => true
			],
			'submitMultipleNotGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'allowEdit' => false,
				'hasFormSubmissionsByUser' => true,
				'expected' => false
			],
			'submitMultiple' => [
				'ownerId' => 'someUser',
				'submitMultiple' => true,
				'allowEdit' => false,
				'hasFormSubmissionsByUser' => true,
				'expected' => true
			],
			'allowEditGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'allowEdit' => true,
				'hasFormSubmissionsByUser' => true,
				'expected' => true
			],
			'allowEditNotGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'allowEdit' => false,
				'hasFormSubmissionsByUser' => true,
				'expected' => false
			],
		];
	}
	/**
	 * @dataProvider dataCanSubmit
	 *
	 * @param string $ownerId
	 * @param bool $submitMultiple
	 * @param bool $hasFormSubmissionsByUser
	 * @param bool $expected
	 */
	public function testCanSubmit(string $ownerId, bool $submitMultiple, bool $allowEdit, bool $hasFormSubmissionsByUser, bool $expected) {
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
			->method('hasFormSubmissionsByUser')
			->with($form, 'currentUser')
			->willReturn($hasFormSubmissionsByUser);

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
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			\OCP\Server::get(IEventDispatcher::class),
		);

		$this->assertEquals(true, $formsService->canSubmit($form));
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
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			\OCP\Server::get(IEventDispatcher::class),
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

	public function dataHasFormExpired() {
		return [
			'hasExpired' => [time() - 3600, Constants::FORM_STATE_ACTIVE, true],
			'hasNotExpired' => [time() + 3600, Constants::FORM_STATE_ACTIVE, false],
			'doesNeverExpire' => [0, Constants::FORM_STATE_ACTIVE, false],
			'isClosed' => [time() + 3600, Constants::FORM_STATE_CLOSED, true],
			'isArchived' => [time() + 3600, Constants::FORM_STATE_ARCHIVED, true],
		];
	}
	/**
	 * @dataProvider dataHasFormExpired
	 *
	 * @param int $expires
	 * @param int $state the form state
	 * @param bool $expected has expired
	 */
	public function testHasFormExpired(int $expires, int $state, bool $expected) {
		$form = new Form();
		$form->setState($state);
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
		$submission = new Submission();
		$submission->setUserId($submitter);

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn(null);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);

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
				$this->answerMapper,
				$this->configService,
				$this->groupManager,
				$this->userManager,
				$this->secureRandom,
				$this->circlesService,
				$this->storage,
				$this->l10n,
				$eventDispatcher,
			])
			->getMock();

		$form = Form::fromParams(['id' => 42, 'ownerId' => $owner]);

		$formsService->method('getShares')->willReturn($shares);

		$this->activityManager->expects($this->once())
			->method('publishNewSubmission')
			->with($form, $submitter);

		$this->activityManager->expects($this->exactly($shareNotifications))
			->method('publishNewSharedSubmission');

		$eventDispatcher->expects($this->exactly(1))->method('dispatchTyped')->withAnyParameters();

		$formsService->notifyNewSubmission($form, $submission);
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
			'valid-options-limit' => [
				'extraSettings' => [
					'optionsLimitMax' => 3,
					'optionsLimitMin' => 2,
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => true
			],
			'valid-options-limit-min' => [
				'extraSettings' => [
					'optionsLimitMin' => 4,
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => true
			],
			'valid-options-limit-max' => [
				'extraSettings' => [
					'optionsLimitMax' => 2,
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => true
			],
			'invalid-options-limit' => [
				// max < min
				'extraSettings' => [
					'optionsLimitMax' => 2,
					'optionsLimitMin' => 4,
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => false
			],
			'invalid-settings-type' => [
				'extraSettings' => [
					'optionsLimitMax' => 'hello',
					'optionsLimitMin' => false,
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => false
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
					'validationRegex' => '/' . '[/'
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

	public function testGetFilePathThrowsAnException() {
		$form = new Form();
		$form->setFileId(100);
		$form->setOwnerId('user1');

		$folder = $this->createMock(Folder::class);
		$folder->expects($this->once())
			->method('getById')
			->with(100)
			->willReturn([]);

		$this->storage->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($folder);

		$this->expectException(NotFoundException::class);
		$this->formsService->getFilePath($form);
	}

	public function testGetFileNameThrowsAnExceptionForInvalidFormat() {
		$form = new Form();

		$this->expectException(\InvalidArgumentException::class);
		$this->formsService->getFileName($form, 'dummy');
	}

	public function testGetFileNameReplacesNewLines() {
		$form = new Form();
		$form->setTitle("Form \n new line");

		$this->assertSame('Form - new line (responses).xlsx', $this->formsService->getFileName($form, 'xlsx'));
	}

	public function testGetFileName() {
		$form = new Form();
		$form->setTitle('Form 1');

		$this->assertSame('Form 1 (responses).xlsx', $this->formsService->getFileName($form, 'xlsx'));
	}

	public function testGetUploadedFilePath() {
		$form = new Form();
		$form->setId(10);
		$form->setTitle('Form 1');

		$this->assertSame('Forms/10 - Form 1/20/30 - question name',
			$this->formsService->getUploadedFilePath($form, 20, 30, 'question name', 'question text'));
	}

	public function testGetTemporaryUploadedFilePath() {
		$form = new Form();
		$form->setId(10);
		$form->setTitle('Form 1');

		$question = new Question();
		$question->setId(30);
		$question->setName('question name');

		\OCA\Forms\Service\microtime(1234567.89);

		$this->assertSame('Forms/unsubmitted/1234567.89/10 - Form 1/30 - question name',
			$this->formsService->getTemporaryUploadedFilePath($form, $question));
	}

	public function testGetQuestionsReturnsEmptyArrayWhenNoQuestions(): void {
		$this->questionMapper->method('findByForm')->willReturn([]);

		$result = $this->formsService->getQuestions(1);

		$this->assertEmpty($result);
	}

	public function testGetQuestionsWithVariousQuestionTypes(): void {
		$questionEntities = [
			$this->createQuestionEntity(['id' => 1, 'type' => 'text']),
			$this->createQuestionEntity(['id' => 2, 'type' => Constants::ANSWER_TYPE_FILE, 'extraSettings' => [
				'allowedFileTypes' => ['image', 'x-office/document'],
				'allowedFileExtensions' => ['pdf']
			]])
		];

		$this->questionMapper->method('findByForm')->willReturn($questionEntities);

		$result = $this->formsService->getQuestions(1);

		$this->assertCount(2, $result);
		$this->assertEquals('text', $result[0]['type']);
		$this->assertEquals(Constants::ANSWER_TYPE_FILE, $result[1]['type']);
		$this->assertEquals(['image/*', 'x-office/document', '.pdf'], $result[1]['accept']);
	}

	public function testGetQuestionsHandlesDoesNotExistException(): void {
		$this->questionMapper->method('findByForm')->willThrowException(new DoesNotExistException('test'));

		$result = $this->formsService->getQuestions(1);

		$this->assertEmpty($result);
	}

	private function createQuestionEntity(array $data): Question {
		$questionEntity = $this->createMock(Question::class);
		$questionEntity->method('read')->willReturn($data);
		return $questionEntity;
	}
}
