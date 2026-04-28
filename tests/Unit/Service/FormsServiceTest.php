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
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\ICacheFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IMemcache;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IEmailValidator;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
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

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IMailer|MockObject */
	private $mailer;

	/** @var IEmailValidator|MockObject */
	private $emailValidator;

	/** @var AnswerMapper|MockObject */
	private $answerMapper;

	/** @var IJobList|MockObject */
	private $jobList;

	/** @var ICacheFactory|MockObject */
	private $cacheFactory;

	/** @var IMemcache|MockObject */
	private $cache;

	public function setUp(): void {
		parent::setUp();
		$this->activityManager = $this->createMock(ActivityManager::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->optionMapper = $this->createMock(OptionMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->emailValidator = $this->createMock(IEmailValidator::class);
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(IMemcache::class);
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
			->will($this->returnCallback(function (string $text, array $params = []) {
				if (!empty($params)) {
					return sprintf($text, ...$params);
				}
				return $text;
			}));
		$this->configService->method('getAllowConfirmationEmail')->willReturn(true);
		$this->cacheFactory->method('createDistributed')->with('forms_confirmation_email')->willReturn($this->cache);
		$this->cache->method('add')->willReturn(true);

		$this->formsService = new FormsService(
			$userSession,
			$this->activityManager,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->configService,
			$this->groupManager,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			$this->logger,
			\OCP\Server::get(IEventDispatcher::class),
			$this->mailer,
			$this->emailValidator,
			$this->answerMapper,
			$this->jobList,
			$this->cacheFactory,
		);
	}

	private function createFormsServiceWithEventDispatcher(IEventDispatcher $eventDispatcher): FormsService {
		return $this->getMockBuilder(FormsService::class)
			->onlyMethods(['getShares', 'getQuestions'])
			->setConstructorArgs([
				$this->createMock(IUserSession::class),
				$this->activityManager,
				$this->formMapper,
				$this->optionMapper,
				$this->questionMapper,
				$this->shareMapper,
				$this->submissionMapper,
				$this->configService,
				$this->groupManager,
				$this->userManager,
				$this->secureRandom,
				$this->circlesService,
				$this->storage,
				$this->l10n,
				$this->logger,
				$eventDispatcher,
				$this->mailer,
				$this->emailValidator,
				$this->answerMapper,
				$this->jobList,
				$this->cacheFactory,
			])
			->getMock();
	}

	public function testGenerateFormHash() {
		$this->secureRandom->expects($this->once())
			->method('generate')
			->with(16, ISecureRandom::CHAR_HUMAN_READABLE)
			->willReturn('testHash');

		$this->assertEquals('testHash', $this->formsService->generateFormHash());
	}

	public static function dataGetForm() {
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
								'optionType' => null,
							],
							[
								'id' => 2,
								'questionId' => 1,
								'text' => 'Option 2',
								'order' => null,
								'optionType' => null,
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
				'allowEditSubmissions' => false,
				'lockedBy' => null,
				'lockedUntil' => null,
				'maxSubmissions' => null,
				'isMaxSubmissionsReached' => false,
				'confirmationEmailEnabled' => false,
				'confirmationEmailSubject' => null,
				'confirmationEmailBody' => null,
				'confirmationEmailQuestionId' => null,
			]]
		];
	}

	#[DataProvider('dataGetForm')]
	public function testGetForm(array $expected) {
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

	public static function dataGetPartialForm() {
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
				'lockedBy' => null,
				'lockedUntil' => null,
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

	public static function dataGetPartialFormShared() {
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
				'lockedBy' => null,
				'lockedUntil' => null,
			]]
		];
	}
	/**
	 * Make sure shared users with results permission also receive the submission count
	 *
	 * @param array $expected
	 */
	#[DataProvider('dataGetPartialFormShared')]
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

	public static function dataGetPublicForm() {
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
				'allowEditSubmissions' => false,
				'lockedBy' => null,
				'lockedUntil' => null,
				'maxSubmissions' => null,
				'isMaxSubmissionsReached' => false,
				'confirmationEmailEnabled' => false,
				'confirmationEmailSubject' => null,
				'confirmationEmailBody' => null,
				'confirmationEmailQuestionId' => null,
			]]
		];
	}

	#[DataProvider('dataGetPublicForm')]
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

	public static function dataGetPermissions() {
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

	#[DataProvider('dataGetPermissions')]
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
			$this->configService,
			$this->groupManager,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			$this->logger,
			\OCP\Server::get(IEventDispatcher::class),
			$this->mailer,
			$this->emailValidator,
			$this->answerMapper,
			$this->jobList,
			$this->cacheFactory,
		);

		$form = new Form();
		$form->setId(42);

		$this->assertEquals([], $formsService->getPermissions($form));
	}

	public static function dataCanSeeResults() {
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

	#[DataProvider('dataCanSeeResults')]
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

	public static function dataCanDeleteResults() {
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

	#[DataProvider('dataCanDeleteResults')]
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

	public static function dataCanSubmit() {
		return [
			'allowFormOwner' => [
				'ownerId' => 'currentUser',
				'submitMultiple' => false,
				'hasFormSubmissionsByUser' => true,
				'expected' => true
			],
			'submitMultipleGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'hasFormSubmissionsByUser' => false,
				'expected' => true
			],
			'submitMultipleNotGood' => [
				'ownerId' => 'someUser',
				'submitMultiple' => false,
				'hasFormSubmissionsByUser' => true,
				'expected' => false
			],
			'submitMultiple' => [
				'ownerId' => 'someUser',
				'submitMultiple' => true,
				'hasFormSubmissionsByUser' => true,
				'expected' => true
			]
		];
	}

	#[DataProvider('dataCanSubmit')]
	public function testCanSubmit(string $ownerId, bool $submitMultiple, bool $hasFormSubmissionsByUser, bool $expected) {
		$form = new Form();
		$form->setId(42);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setOwnerId($ownerId);
		$form->setSubmitMultiple($submitMultiple);

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
			$this->configService,
			$this->groupManager,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			$this->logger,
			\OCP\Server::get(IEventDispatcher::class),
			$this->mailer,
			$this->emailValidator,
			$this->answerMapper,
			$this->jobList,
			$this->cacheFactory,
		);

		$this->assertEquals(true, $formsService->canSubmit($form));
	}

	public static function dataHasUserAccess() {
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

	#[DataProvider('dataHasUserAccess')]
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
			$this->configService,
			$this->groupManager,
			$this->userManager,
			$this->secureRandom,
			$this->circlesService,
			$this->storage,
			$this->l10n,
			$this->logger,
			\OCP\Server::get(IEventDispatcher::class),
			$this->mailer,
			$this->emailValidator,
			$this->answerMapper,
			$this->jobList,
			$this->cacheFactory,
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

	public static function dataHasFormExpired() {
		return [
			'hasExpired' => [time() - 3600, Constants::FORM_STATE_ACTIVE, true],
			'hasNotExpired' => [time() + 3600, Constants::FORM_STATE_ACTIVE, false],
			'doesNeverExpire' => [0, Constants::FORM_STATE_ACTIVE, false],
			'isClosed' => [time() + 3600, Constants::FORM_STATE_CLOSED, true],
			'isArchived' => [time() + 3600, Constants::FORM_STATE_ARCHIVED, true],
		];
	}

	#[DataProvider('dataHasFormExpired')]
	public function testHasFormExpired(int $expires, int $state, bool $expected) {
		$form = new Form();
		$form->setState($state);
		$form->setExpires($expires);

		$this->assertEquals($expected, $this->formsService->hasFormExpired($form));
	}

	public static function dataGetShareDisplayName() {
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

	#[DataProvider('dataGetShareDisplayName')]
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

	public static function dataNotifyNewShares() {
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
	 * @param int $shareType
	 * @param string $shareWith
	 * @param string $expectedMethod that will be called on activityManager.
	 */
	#[DataProvider('dataNotifyNewShares')]
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

	public static function dataNotifyNewSubmission() {
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
	 */
	#[DataProvider('dataNotifyNewSubmission')]
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
				$this->configService,
				$this->groupManager,
				$this->userManager,
				$this->secureRandom,
				$this->circlesService,
				$this->storage,
				$this->l10n,
				$this->logger,
				$eventDispatcher,
				$this->mailer,
				$this->emailValidator,
				$this->answerMapper,
				$this->jobList,
				$this->cacheFactory,
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

	public function testNotifyNewSubmissionDoesNotSendConfirmationEmailIfDisabled(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'confirmationEmailEnabled' => false,
		]);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->getMockBuilder(FormsService::class)
			->onlyMethods(['getShares'])
			->setConstructorArgs([
				$this->createMock(IUserSession::class),
				$this->activityManager,
				$this->formMapper,
				$this->optionMapper,
				$this->questionMapper,
				$this->shareMapper,
				$this->submissionMapper,
				$this->configService,
				$this->groupManager,
				$this->userManager,
				$this->secureRandom,
				$this->circlesService,
				$this->storage,
				$this->l10n,
				$this->logger,
				$eventDispatcher,
				$this->mailer,
				$this->emailValidator,
				$this->answerMapper,
				$this->jobList,
				$this->cacheFactory,
			])
			->getMock();

		$formsService->method('getShares')->willReturn([]);

		$this->jobList->expects($this->never())->method('add');

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionSendsConfirmationEmailWithPlaceholders(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'description' => 'My Desc',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Thanks {name}',
			'confirmationEmailBody' => 'Hello {name}',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			],
			[
				'id' => 2,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Name',
				'name' => 'name',
				'extraSettings' => ['validationType' => 'text'],
			],
		];

		$emailAnswer = new Answer();
		$emailAnswer->setSubmissionId(99);
		$emailAnswer->setQuestionId(1);
		$emailAnswer->setText('respondent@example.com');

		$nameAnswer = new Answer();
		$nameAnswer->setSubmissionId(99);
		$nameAnswer->setQuestionId(2);
		$nameAnswer->setText('Ada');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer, $nameAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('respondent@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('respondent@example.com', $payload['recipient']);
					$this->assertSame('Thanks Ada', $payload['subject']);
					$this->assertStringContainsString('Hello Ada', $payload['body']);
					return true;
				})
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionSendsConfirmationEmailWithFormPlaceholders(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'description' => 'My Desc',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Subject {formTitle}',
			'confirmationEmailBody' => 'Body {formDescription}',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$emailAnswer = new Answer();
		$emailAnswer->setSubmissionId(99);
		$emailAnswer->setQuestionId(1);
		$emailAnswer->setText('respondent@example.com');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('respondent@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				[
					'recipient' => 'respondent@example.com',
					'subject' => 'Subject My Form',
					'body' => 'Body My Desc',
					'formId' => 42,
					'submissionId' => 99,
				]
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionConfirmationEmailUsesConfiguredRecipientQuestion(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'description' => 'My Desc',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Thanks',
			'confirmationEmailBody' => 'Hello',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email 1',
				'name' => 'email1',
				'extraSettings' => ['validationType' => 'email'],
			],
			[
				'id' => 2,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email 2',
				'name' => 'email2',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$emailAnswer1 = new Answer();
		$emailAnswer1->setSubmissionId(99);
		$emailAnswer1->setQuestionId(1);
		$emailAnswer1->setText('first@example.com');

		$emailAnswer2 = new Answer();
		$emailAnswer2->setSubmissionId(99);
		$emailAnswer2->setQuestionId(2);
		$emailAnswer2->setText('second@example.com');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer1, $emailAnswer2]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('first@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('first@example.com', $payload['recipient']);
					return true;
				})
			);

		$logged = [];
		$this->logger->method('debug')
			->willReturnCallback(function (string $message, array $context) use (&$logged): void {
				$logged[] = [$message, $context];
			});

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionPlaceholderUsesQuestionTextWhenNameMissing(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'description' => 'My Desc',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Hello {fullname}',
			'confirmationEmailBody' => 'Body',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			],
			[
				'id' => 2,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Full Name?',
				'name' => '',
				'extraSettings' => ['validationType' => 'text'],
			],
		];

		$emailAnswer = new Answer();
		$emailAnswer->setSubmissionId(99);
		$emailAnswer->setQuestionId(1);
		$emailAnswer->setText('respondent@example.com');

		$nameAnswer = new Answer();
		$nameAnswer->setSubmissionId(99);
		$nameAnswer->setQuestionId(2);
		$nameAnswer->setText('Ada');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer, $nameAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('respondent@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('Hello Ada', $payload['subject']);
					return true;
				})
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionSendsConfirmationEmailWithEmptySubjectAndBody(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'description' => 'My Desc',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => '',
			'confirmationEmailBody' => '',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$emailAnswer = new Answer();
		$emailAnswer->setSubmissionId(99);
		$emailAnswer->setQuestionId(1);
		$emailAnswer->setText('respondent@example.com');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('respondent@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				$this->callback(function (array $payload): bool {
					$this->assertSame('respondent@example.com', $payload['recipient']);
					$this->assertSame('Thank you for your submission', $payload['subject']);
					$this->assertStringContainsString('Thank you for submitting the form "My Form"', $payload['body']);
					$this->assertSame(42, $payload['formId']);
					$this->assertSame(99, $payload['submissionId']);
					return true;
				})
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionSendsConfirmationEmailToExplicitRecipientQuestion(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 2,
			'confirmationEmailSubject' => 'Thanks',
			'confirmationEmailBody' => 'Hello',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Work email',
				'name' => 'workEmail',
				'extraSettings' => ['validationType' => 'email'],
			],
			[
				'id' => 2,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Private email',
				'name' => 'privateEmail',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$workEmailAnswer = new Answer();
		$workEmailAnswer->setSubmissionId(99);
		$workEmailAnswer->setQuestionId(1);
		$workEmailAnswer->setText('work@example.com');

		$privateEmailAnswer = new Answer();
		$privateEmailAnswer->setSubmissionId(99);
		$privateEmailAnswer->setQuestionId(2);
		$privateEmailAnswer->setText('private@example.com');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$workEmailAnswer, $privateEmailAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('private@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				[
					'recipient' => 'private@example.com',
					'subject' => 'Thanks',
					'body' => 'Hello',
					'formId' => 42,
					'submissionId' => 99,
				]
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionDoesNotSendConfirmationEmailWithoutExplicitRecipientWhenMultipleEmailFieldsExist(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'confirmationEmailEnabled' => true,
			'confirmationEmailSubject' => 'Thanks',
			'confirmationEmailBody' => 'Hello',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Work email',
				'name' => 'workEmail',
				'extraSettings' => ['validationType' => 'email'],
			],
			[
				'id' => 2,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Private email',
				'name' => 'privateEmail',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([]);

		$this->emailValidator->expects($this->never())->method('isValid');
		$this->logger->expects($this->once())
			->method('debug')
			->with(
				'No confirmation email recipient question is available',
				[
					'formId' => 42,
					'submissionId' => 99,
				]
			);
		$this->jobList->expects($this->never())->method('add');

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionDoesNotSendConfirmationEmailWhenNoEmailFound(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Thanks',
			'confirmationEmailBody' => 'Hello',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Name',
				'name' => 'name',
				'extraSettings' => ['validationType' => 'text'],
			],
		];

		$nameAnswer = new Answer();
		$nameAnswer->setSubmissionId(99);
		$nameAnswer->setQuestionId(1);
		$nameAnswer->setText('John');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$nameAnswer]);

		$logged = [];
		$this->logger->expects($this->exactly(2))
			->method('debug')
			->willReturnCallback(function (string $message, array $context) use (&$logged): void {
				$logged[] = [$message, $context];
			});

		$this->jobList->expects($this->never())->method('add');

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);

		$this->assertSame([
			[
				'Configured confirmation email recipient question is invalid',
				[
					'formId' => 42,
					'recipientQuestionId' => 1,
				],
			],
			[
				'No confirmation email recipient question is available',
				[
					'formId' => 42,
					'submissionId' => 99,
				],
			],
		], $logged);
	}

	public function testNotifyNewSubmissionQueuesConfirmationEmail(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Thanks',
			'confirmationEmailBody' => 'Hello',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$emailAnswer = new Answer();
		$emailAnswer->setSubmissionId(99);
		$emailAnswer->setQuestionId(1);
		$emailAnswer->setText('respondent@example.com');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('respondent@example.com')
			->willReturn(true);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\Forms\BackgroundJob\SendConfirmationMailJob::class,
				[
					'recipient' => 'respondent@example.com',
					'subject' => 'Thanks',
					'body' => 'Hello',
					'formId' => 42,
					'submissionId' => 99,
				]
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	public function testNotifyNewSubmissionSkipsConfirmationEmailWhenRecipientRateLimitIsReached(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
			'confirmationEmailSubject' => 'Thanks',
			'confirmationEmailBody' => 'Hello',
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_SHORT,
				'text' => 'Email',
				'name' => 'email',
				'extraSettings' => ['validationType' => 'email'],
			],
		];

		$emailAnswer = new Answer();
		$emailAnswer->setSubmissionId(99);
		$emailAnswer->setQuestionId(1);
		$emailAnswer->setText('respondent@example.com');

		$this->answerMapper->expects($this->once())
			->method('findBySubmission')
			->with(99)
			->willReturn([$emailAnswer]);

		$this->emailValidator->expects($this->once())
			->method('isValid')
			->with('respondent@example.com')
			->willReturn(true);

		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(IMemcache::class);
		$this->cacheFactory->expects($this->once())
			->method('createDistributed')
			->with('forms_confirmation_email')
			->willReturn($this->cache);

		$cacheKey = 'email_rl_' . hash('sha256', '42:respondent@example.com');
		$this->cache->expects($this->once())
			->method('add')
			->with($cacheKey, 1, 86400)
			->willReturn(false);
		$this->cache->expects($this->once())
			->method('inc')
			->with($cacheKey)
			->willReturn(4);

		$this->jobList->expects($this->never())->method('add');
		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Per-recipient confirmation email rate limit reached',
				[
					'formId' => 42,
					'submissionId' => 99,
				]
			);

		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$eventDispatcher->expects($this->once())->method('dispatchTyped')->withAnyParameters();

		$formsService = $this->createFormsServiceWithEventDispatcher($eventDispatcher);

		$formsService->method('getShares')->willReturn([]);
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);
	}

	/**
	 * @param array $extraSettings input settings
	 * @param string $questionType question type
	 * @param bool $expected expected return value
	 */
	#[DataProvider('dataAreExtraSettingsValid')]
	public function testAreExtraSettingsValid(array $extraSettings, string $questionType, bool $expected) {
		$this->assertEquals($expected, $this->formsService->areExtraSettingsValid($extraSettings, $questionType));
	}

	public static function dataAreExtraSettingsValid() {
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
				'expected' => false
			],
			'invalid-custom-regex-delimiters' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '/1/2/'
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => false
			],
			'invalid-custom-regex-pattern' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => '/' . '[/'
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => false
			],
			'invalid-custom-regex-type' => [
				'extraSettings' => [
					'validationType' => 'regex',
					'validationRegex' => 112
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => false
			],
			'invalid-custom-missing-regex' => [
				'extraSettings' => [
					'validationType' => 'regex',
				],
				'questionType' => Constants::ANSWER_TYPE_SHORT,
				'expected' => false
			],
			'valid-date-settings' => [
				'extraSettings' => [
					'dateMin' => 1234567890,
					'dateMax' => null,
				],
				'questionType' => Constants::ANSWER_TYPE_DATE,
				'expected' => true
			],
			'invalid-date-settings' => [
				'extraSettings' => [
					'dateMin' => 'today',
					'dateMax2' => null,
				],
				'questionType' => Constants::ANSWER_TYPE_DATE,
				'expected' => false
			],
			'invalid-date-limits' => [
				// max < min
				'extraSettings' => [
					'dateMin' => 1234567890,
					'dateMax' => 1234567889,
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => false
			],
			'valid-time-settings' => [
				'extraSettings' => [
					'timeMin' => '12:34',
					'timeMax' => null,
				],
				'questionType' => Constants::ANSWER_TYPE_TIME,
				'expected' => true
			],
			'invalid-time-settings' => [
				'extraSettings' => [
					'timeMin' => 'today',
					'timeMax2' => null,
				],
				'questionType' => Constants::ANSWER_TYPE_TIME,
				'expected' => false
			],
			'invalid-time-limits' => [
				// max < min
				'extraSettings' => [
					'dateMin' => '12:34',
					'dateMax' => '12:33',
				],
				'questionType' => Constants::ANSWER_TYPE_MULTIPLE,
				'expected' => false
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
			],
			'valid-linearscale-settings' => [
				'extraSettings' => [
					'optionsLowest' => 0,
					'optionsHighest' => 5,
					'optionsLabelLowest' => 'disagree',
					'optionsLabelHighest' => 'agree'
				],
				'questionType' => Constants::ANSWER_TYPE_LINEARSCALE,
				'expected' => true
			],
			'invalid-linearscale-settings' => [
				'extraSettings' => [
					'optionsLowest' => 1,
					'optionsHighest' => 10,
					'optionsLabelLowest' => 'disagree',
					'optionsLabelHighest' => 'agree',
					'someInvalidOption' => true
				],
				'questionType' => Constants::ANSWER_TYPE_LINEARSCALE,
				'expected' => false
			],
			'outofrange-linearscale-settings' => [
				'extraSettings' => [
					'optionsLowest' => 3,
					'optionsHighest' => 11,
				],
				'questionType' => Constants::ANSWER_TYPE_LINEARSCALE,
				'expected' => false
			],
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

	public function testNotifyNewSubmissionConfirmationEmailFailsIfRecipientQuestionIsNoLongerEmail(): void {
		$submission = new Submission();
		$submission->setId(99);
		$submission->setUserId('someUser');

		$form = Form::fromParams([
			'id' => 42,
			'ownerId' => 'ownerUser',
			'title' => 'My Form',
			'confirmationEmailEnabled' => true,
			'confirmationEmailQuestionId' => 1,
		]);

		$questions = [
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_LONG, // Changed from short to long
				'text' => 'Not an email anymore',
				'name' => 'email',
			],
		];

		$this->logger->expects($this->exactly(2))
			->method('debug')
			->willReturnCallback(function (string $message, array $context) use (&$logged): void {
				$logged[] = [$message, $context];
			});

		$this->jobList->expects($this->never())->method('add');

		$formsService = $this->createFormsServiceWithEventDispatcher($this->createMock(IEventDispatcher::class));
		$formsService->method('getQuestions')->with(42)->willReturn($questions);

		$formsService->notifyNewSubmission($form, $submission);

		$this->assertSame([
			[
				'Configured confirmation email recipient question is invalid',
				[
					'formId' => 42,
					'recipientQuestionId' => 1,
				],
			],
			[
				'No confirmation email recipient question is available',
				[
					'formId' => 42,
					'submissionId' => 99,
				],
			],
		], $logged);
	}

	private function createQuestionEntity(array $data): MockObject {
		$questionEntity = $this->createMock(Question::class);
		$questionEntity->method('read')->willReturn($data);
		return $questionEntity;
	}

	public function testValidateConfirmationEmailQuestionIdAllowsNull(): void {
		$form = new Form();
		$this->formsService->validateConfirmationEmailQuestionId($form, null);
		$this->assertTrue(true); // Should not throw exception
	}

	public function testValidateConfirmationEmailQuestionIdRejectsNonInt(): void {
		$form = new Form();
		$this->expectException(\InvalidArgumentException::class);
		$this->formsService->validateConfirmationEmailQuestionId($form, '7');
	}

	public function testValidateConfirmationEmailQuestionIdRejectsNotFoundQuestion(): void {
		$form = new Form();
		$this->questionMapper->method('findById')->willThrowException(new DoesNotExistException(''));
		$this->expectException(\InvalidArgumentException::class);
		$this->formsService->validateConfirmationEmailQuestionId($form, 7);
	}

	public function testValidateConfirmationEmailQuestionIdRejectsMismatchedForm(): void {
		$form = new Form();
		$form->setId(1);
		$question = new Question();
		$question->setFormId(2);
		$this->questionMapper->method('findById')->willReturn($question);
		$this->expectException(\InvalidArgumentException::class);
		$this->formsService->validateConfirmationEmailQuestionId($form, 7);
	}

	public function testValidateConfirmationEmailQuestionIdRejectsDeletedQuestion(): void {
		$form = new Form();
		$form->setId(1);
		$question = new Question();
		$question->setFormId(1);
		$question->setOrder(0);
		$this->questionMapper->method('findById')->willReturn($question);
		$this->expectException(\InvalidArgumentException::class);
		$this->formsService->validateConfirmationEmailQuestionId($form, 7);
	}

	public function testValidateConfirmationEmailQuestionIdRejectsNonEmailQuestion(): void {
		$form = new Form();
		$form->setId(1);
		$question = new Question();
		$question->setFormId(1);
		$question->setOrder(1);
		$question->setType(Constants::ANSWER_TYPE_LONG);
		$this->questionMapper->method('findById')->willReturn($question);
		$this->expectException(\InvalidArgumentException::class);
		$this->formsService->validateConfirmationEmailQuestionId($form, 7);
	}

	public function testValidateConfirmationEmailQuestionIdAllowsValidQuestion(): void {
		$form = new Form();
		$form->setId(1);
		$question = new Question();
		$question->setFormId(1);
		$question->setOrder(1);
		$question->setType(Constants::ANSWER_TYPE_SHORT);
		$question->setExtraSettings(['validationType' => 'email']);
		$this->questionMapper->method('findById')->willReturn($question);
		$this->formsService->validateConfirmationEmailQuestionId($form, 7);
		$this->assertTrue(true); // Should not throw exception
	}
}
