<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Controller;

/**
 * mock is_uploaded_file() function used in services
 * @param string|bool|null $filename the value that should be returned when called
 */
function is_uploaded_file(string|bool|null $filename) {
	static $value;
	if ($filename === false || $filename === true || $filename === null) {
		$value = $filename;

		return false;
	}

	if (is_null($value)) {
		return \is_uploaded_file($filename);
	}

	return $value;
}


namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\BackgroundJob\SyncSubmissionsWithLinkedFileJob;
use OCA\Forms\Constants;
use OCA\Forms\Controller\ApiController;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\UploadedFileMapper;
use OCA\Forms\Exception\NoSuchFormException;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IStorage;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class ApiControllerTest extends TestCase {
	private ApiController $apiController;
	/** @var AnswerMapper|MockObject */
	private $answerMapper;
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
	/** @var FormsService|MockObject */
	private $formsService;
	/** @var SubmissionService|MockObject */
	private $submissionService;
	/** @var LoggerInterface|MockObject */
	private $logger;
	/** @var IRequest|MockObject */
	private $request;
	/** @var IUserManager|MockObject */
	private $userManager;
	/** @var IL10N|MockObject */
	private $l10n;
	/** @var IRootFolder|MockObject */
	private $storage;
	/** @var UploadedFileMapper|MockObject */
	private $uploadedFileMapper;
	/** @var IMimeTypeDetector|MockObject */
	private $mimeTypeDetector;
	/** @var IJobList|MockObject */
	private $jobList;

	public function setUp(): void {
		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->optionMapper = $this->createMock(OptionMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->submissionService = $this->createMock(SubmissionService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($v) {
				return $v;
			});
		$this->storage = $this->createMock(IRootFolder::class);
		$this->uploadedFileMapper = $this->createMock(UploadedFileMapper::class);
		$this->mimeTypeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->apiController = new ApiController(
			'forms',
			$this->request,
			$this->createUserSession(),
			$this->answerMapper,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->configService,
			$this->formsService,
			$this->submissionService,
			$this->l10n,
			$this->logger,
			$this->userManager,
			$this->storage,
			$this->uploadedFileMapper,
			$this->mimeTypeDetector,
			$this->jobList,
		);
	}

	/**
	 * Helper factory to prevent duplicated code
	 */
	protected function createUserSession() {
		$userSession = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		return $userSession;
	}

	/**
	 * Factory to create a validator used to compare forms passed as parameters
	 * Required as the timestamps might differ
	 */
	public static function createFormValidator(array $expected) {
		return function ($form) use ($expected): bool {
			self::assertInstanceOf(Form::class, $form);
			$read = $form->read();
			unset($read['created']);
			self::assertEquals($expected, $read);
			return true;
		};
	}

	/**
	 * Helper function to throw exceptions
	 * Required as PHP 7 does not allow throwing in expressions (e.g. `fn(): foo => throw ...`)
	 */
	public function throwMockedException(string $class) {
		throw $this->createMock($class);
	}

	public function testGetSubmissions_invalidForm() {
		// Simulate the service throwing the correct exception type
		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willThrowException(new NoSuchFormException('Could not find form'));

		$this->expectException(NoSuchFormException::class);
		$this->apiController->getSubmissions(1);
	}

	public function testGetSubmissions_noPermissions() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willThrowException(new NoSuchFormException('The current user has no permission to get the results for this form'));

		$this->expectException(NoSuchFormException::class);
		$this->apiController->getSubmissions(1);
	}

	public function dataGetSubmissions() {
		return [
			'anon' => [
				'submissions' => [
					['userId' => 'anon-user-1']
				],
				'questions' => [['id' => 1, 'name' => 'questions']],
				'expected' => [
					'submissions' => [
						[
							'userId' => 'anon-user-1',
							'userDisplayName' => 'Anonymous response',
						]
					],
					'questions' => [
						[
							'id' => 1,
							'name' => 'questions',
							'extraSettings' => new \stdClass(),
						],
					],
					'filteredSubmissionsCount' => 1,
				]
			],
			'user' => [
				'submissions' => [
					['userId' => 'jdoe']
				],
				'questions' => [['id' => 1, 'name' => 'questions']],
				'expected' => [
					'submissions' => [
						[
							'userId' => 'jdoe',
							'userDisplayName' => 'jdoe',
						]
					],
					'questions' => [
						[
							'id' => 1,
							'name' => 'questions',
							'extraSettings' => new \stdClass(),
						],
					],
					'filteredSubmissionsCount' => 1,
				]
			]
		];
	}

	/**
	 * @dataProvider dataGetSubmissions
	 */
	public function testGetSubmissions(array $submissions, array $questions, array $expected) {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('otherUser');

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$this->submissionService->expects($this->once())
			->method('getSubmissions')
			->with(1)
			->willReturn($submissions);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(1)
			->willReturn($questions);

		$this->submissionMapper->expects($this->once())
			->method('countSubmissions')
			->with(1)
			->willReturn(1);

		$this->assertEquals(new DataResponse($expected), $this->apiController->getSubmissions(1));
	}

	public function testExportSubmissions_invalidForm() {
		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(99, Constants::PERMISSION_RESULTS)
			->willThrowException(new NoSuchFormException('Could not find form'));

		$this->expectException(NoSuchFormException::class);
		$this->apiController->getSubmissions(99, 'csv');
	}

	public function testExportSubmissions_noPermissions() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willThrowException(new NoSuchFormException('The current user has no permission to get the results for this form'));

		$this->expectException(NoSuchFormException::class);
		$this->apiController->getSubmissions(1, 'csv');
	}

	public function testExportSubmissions() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$csv = 'foo,bar';
		$this->submissionService->expects($this->once())
			->method('getSubmissionsData')
			->with($form, 'csv')
			->willReturn($csv);

		$fileName = 'foo.csv';
		$this->formsService->expects($this->once())
			->method('getFileName')
			->with($form, 'csv')
			->willReturn($fileName);

		$this->assertEquals(new DataDownloadResponse($csv, $fileName, 'text/csv'), $this->apiController->getSubmissions(1, fileFormat: 'csv'));
	}

	public function testExportSubmissionsToCloud_invalidForm() {
		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willThrowException(new NoSuchFormException('Could not find form'));
		$this->expectException(NoSuchFormException::class);
		$this->apiController->exportSubmissionsToCloud(1, '');
	}

	public function testCreateNewForm_notAllowed() {
		$this->configService->expects($this->once())
			->method('canCreateForms')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->apiController->newForm();
	}

	public function dataTestCreateNewForm() {
		return [
			'forms' => ['expectedForm' => [
				'id' => 7,
				'state' => 0,
				'hash' => 'formHash',
				'title' => '',
				'description' => '',
				'ownerId' => 'currentUser',
				'access' => [
					'permitAllUsers' => false,
					'showToAllUsers' => false,
				],
				'expires' => 0,
				'isAnonymous' => false,
				'submitMultiple' => false,
				'showExpiration' => false,
				'lastUpdated' => 123456789,
				'submissionMessage' => null,
				'fileId' => null,
				'fileFormat' => null,
				'allowEditSubmissions' => false,
				'lockedBy' => null,
				'lockedUntil' => null,
			]]
		];
	}

	/**
	 * @dataProvider dataTestCreateNewForm()
	 */
	public function testCreateNewForm($expectedForm) {
		$this->configService->expects($this->once())
			->method('canCreateForms')
			->willReturn(true);
		$this->formsService->expects($this->once())
			->method('generateFormHash')
			->willReturn('formHash');
		$expected = $expectedForm;
		$expected['id'] = null;
		// TODO fix test, currently unset because behaviour has changed
		$expected['state'] = null;
		$expected['lastUpdated'] = null;
		$this->formMapper->expects($this->once())
			->method('insert')
			->with(self::callback(self::createFormValidator($expected)))
			->willReturnCallback(function ($form) {
				$form->setId(7);
				return $form;
			});
		$this->assertEquals(new DataResponse([], Http::STATUS_CREATED), $this->apiController->newForm());
	}

	public function dataCloneForm_exceptions() {
		return [
			'disabled' => [
				'canCreate' => false,
				'callback' => fn ($id): Form => new Form(),
				'exception' => OCSForbiddenException::class
			],
			'not found' => [
				'canCreate' => true,
				'callback' => fn ($id): Form => throw new NoSuchFormException('Could not find form'),
				'exception' => NoSuchFormException::class
			],
			'not owned' => [
				'canCreate' => true,
				'callback' => fn ($id): Form => throw new NoSuchFormException('This form is not owned by the current user and user has no `edit` permission'),
				'exception' => NoSuchFormException::class
			]
		];
	}

	/**
	 * @dataProvider dataCloneForm_exceptions()
	 */
	public function testCloneForm_exceptions(bool $canCreate, $callback, string $exception) {
		$this->configService->expects($this->once())
			->method('canCreateForms')
			->willReturn($canCreate);
		$this->formsService->expects($canCreate ? $this->once() : $this->never())
			->method('getFormIfAllowed')
			->with(7, Constants::PERMISSION_EDIT)
			->willReturnCallback($callback);
		$this->expectException($exception);
		$this->apiController->newForm(7);
	}

	public function dataCloneForm() {
		return [
			'works' => [
				'old' => [
					'id' => 7,
					'title' => '',
					'hash' => 'old hash',
					'created' => null,
					'access' => [
						'permitAllUsers' => false,
						'showToAllUsers' => false,
					],
					'ownerId' => 'currentUser',
					'description' => '',
					'expires' => 0,
					'isAnonymous' => false,
					'submitMultiple' => false,
					'showExpiration' => false
				],
				'new' => [
					'id' => 14,
					'title' => ' - Copy',
					'hash' => 'new hash',
					'access' => [
						'permitAllUsers' => false,
						'showToAllUsers' => false,
					],
					'ownerId' => 'currentUser',
					'description' => '',
					'expires' => 0,
					'isAnonymous' => false,
					'submitMultiple' => false,
					'showExpiration' => false
				]
			]
		];
	}

	/**
	 * @dataProvider dataCloneForm()
	 */
	public function testCloneForm($old, $new) {
		$this->configService->expects($this->once())
			->method('canCreateForms')
			->willReturn(true);

		$oldForm = Form::fromParams($old);
		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(7)
			->willReturn($oldForm);

		$this->formsService->expects($this->once())
			->method('generateFormHash')
			->willReturn('new hash');

		$read = $oldForm->read();
		unset($read['id']);
		$this->formMapper->expects($this->once())
			->method('insert')
			->with(self::callback(function ($form) {
				self::assertInstanceOf(Form::class, $form);
				self::assertNull($form->getId());
				self::assertEquals($form->getHash(), 'new hash');
				$form->setId(14);
				return true;
			}));

		$this->questionMapper->expects($this->once())
			->method('findByForm')
			->with(7)
			->willReturn([]);

		$this->assertEquals(new DataResponse([], Http::STATUS_CREATED), $this->apiController->newForm(7));
	}

	private function formAccess(bool $hasUserAccess = true, bool $hasFormExpired = false, bool $canSubmit = true) {
		$this->formsService
			->method('hasUserAccess')
			->willReturn($hasUserAccess);

		$this->formsService
			->method('hasFormExpired')
			->willReturn($hasFormExpired);

		$this->formsService
			->method('canSubmit')
			->willReturn($canSubmit);
	}

	public function testUploadFiles() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('currentUser');
		$question = Question::fromParams(['formId' => 1]);

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->questionMapper->expects($this->once())
			->method('findById')
			->with(10)
			->willReturn($question);

		$this->request->expects($this->once())
			->method('getUploadedFile')
			->with('files')
			->willReturn([
				'size' => [100],
				'tmp_name' => [tempnam('/tmp', 'test')],
				'name' => ['file.txt'],
				'error' => [0],
			]);

		$this->formsService->expects($this->once())
			->method('canSubmit')
			->with($form)
			->willReturn(true);

		\OCA\Forms\Controller\is_uploaded_file(true);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('nodeExists')
			->willReturn(true);

		$storage = $this->createMock(IStorage::class);
		$userFolder->expects($this->once())
			->method('getStorage')
			->willReturn($storage);

		$file = $this->createMock(File::class);
		$file->expects($this->once())
			->method('getId')
			->willReturn(100);

		$folder = $this->createMock(Folder::class);
		$folder->expects($this->once())
			->method('newFile')
			->willReturn($file);

		$userFolder->expects($this->once())
			->method('get')
			->willReturn($folder);

		$this->storage->expects($this->once())
			->method('getUserFolder')
			->with('currentUser')
			->willReturn($userFolder);


		$this->apiController->uploadFiles(1, 10, '');
	}

	public function testNewSubmission_answers() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('admin');
		$form->setFileId(100);
		$form->setFileFormat('xlsx');

		$questions = [
			[
				'id' => 2,
				'type' => Constants::ANSWER_TYPE_MULTIPLE,
				'extraSettings' => ['allowOtherAnswer' => true],
				'options' => [
					['id' => 1, 'text' => 'test id 1'],
					['id' => 2, 'text' => 'test id 2'],
				],
			],
			[
				'id' => 3,
				'type' => Constants::ANSWER_TYPE_SHORT,
			],
			[
				'id' => 1,
				'type' => Constants::ANSWER_TYPE_MULTIPLE,
				'options' => [
					['id' => 3, 'text' => 'test id 3'],
					['id' => 4, 'text' => 'test id 4'],
				],
			],
			[
				'id' => 4,
				'name' => null,
				'text' => 'Dummy file question',
				'type' => Constants::ANSWER_TYPE_FILE,
				'options' => [],
				'extraSettings' => ['maxAllowedFilesCount' => 2],
			],
		];

		$answers = [
			1 => ['3'],
			2 => ['2', '5', Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX . 'other answer'],
			3 => ['short anwer'],
			4 => [['uploadedFileId' => 100]],
			5 => ['ignore unknown question'],
		];

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(1)
			->willReturn($questions);

		$this->formAccess();

		$this->submissionMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function ($submission) {
				$submission->setId(12);
				return true;
			}));

		$this->answerMapper->expects($this->exactly(5))
			->method('insert')
			->with($this->callback(function ($answer) {
				if ($answer->getSubmissionId() !== 12) {
					return false;
				}

				switch ($answer->getQuestionId()) {
					case 1:
						if ($answer->getText() !== 'test id 3') {
							return false;
						}
						break;
					case 2:
						if (!in_array($answer->getText(), ['other answer', 'test id 2'])) {
							return false;
						}
						break;
					case 3:
						if ($answer->getText() !== 'short anwer') {
							return false;
						}
						break;
				}

				return true;
			}));

		$this->formsService->expects($this->once())
			->method('notifyNewSubmission');

		$this->jobList->expects($this->once())
			->method('add')
			->with(SyncSubmissionsWithLinkedFileJob::class, ['form_id' => 1]);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('nodeExists')
			->willReturn(true);

		$file = $this->createMock(File::class);

		$userFolder->expects($this->once())
			->method('getById')
			->willReturn([$file]);

		$folder = $this->createMock(Folder::class);

		$userFolder->expects($this->once())
			->method('get')
			->willReturn($folder);

		$this->storage->expects($this->once())
			->method('getUserFolder')
			->with('admin')
			->willReturn($userFolder);

		$this->apiController->newSubmission(1, $answers, '');
	}

	public function testNewSubmission_formNotFound() {
		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willThrowException(new NoSuchFormException('Could not find form'));
		$this->expectException(NoSuchFormException::class);
		$this->apiController->newSubmission(1, [], '');
	}

	/**
	 * Values for the formsService mock object for the following methods: hasUserAccess, hasFormExpired, canSubmit.
	 */
	public function dataForCheckForbiddenException() {
		return [
			'user_dont_have_access_to_form' => [false, true, true, NoSuchFormException::class],
			'form_expired' => [true, true, true, OCSForbiddenException::class],
			'not_allowed_to_submit' => [true, false, false, OCSForbiddenException::class],
		];
	}

	/**
	 * @dataProvider dataForCheckForbiddenException()
	 */
	public function testNewSubmission_forbiddenException($hasUserAccess, $hasFormExpired, $canSubmit, $exception) {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('admin');

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willThrowException(new $exception);

		$this->formAccess($hasUserAccess, $hasFormExpired, $canSubmit);

		$this->expectException($exception);

		$this->apiController->newSubmission(1, [], '');
	}

	public function testNewSubmission_validateSubmission() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('admin');

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(1)
			->willReturn([]);

		$this->formAccess();

		$this->submissionService
			->method('validateSubmission')
			->willThrowException(new \InvalidArgumentException('error message'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('error message');

		$this->apiController->newSubmission(1, [], '');
	}

	public function testDeleteSubmissionNotFound() {
		$exception = $this->createMock(MapperException::class);

		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		$this->formsService->expects(self::once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS_DELETE)
			->willReturn($form);

		$this->submissionMapper
			->expects($this->once())
			->method('findById')
			->with(42)
			->willThrowException($exception);

		// Not found as this is about the submission, not the form
		$this->expectException(OCSNotFoundException::class);
		$this->apiController->deleteSubmission(1, 42);
	}

	/**
	 * @dataProvider dataTestDeletePermission
	 */
	public function testDeleteSubmissionNoPermission($submissionData, $formData) {
		$submission = Submission::fromParams($submissionData);
		$form = Form::fromParams($formData);

		$this->submissionMapper
			->method('findById')
			->with(42)
			->willReturn($submission);

		$this->formsService
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS_DELETE)
			->willReturn($form);

		$this->expectException(OCSForbiddenException::class);
		$this->apiController->deleteSubmission(1, 42);
	}

	/**
	 * @dataProvider dataTestDeletePermission
	 */
	public function testDeleteSubmission($submissionData, $formData) {
		$submission = Submission::fromParams($submissionData);
		$form = Form::fromParams($formData);

		$this->submissionMapper
			->method('findById')
			->with(42)
			->willReturn($submission);

		$this->formsService
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS_DELETE)
			->willReturn($form);

		$this->formsService
			->expects($this->once())
			->method('getPermissions')
			->with($form)
			->willReturn(Constants::PERMISSION_ALL);

		$this->submissionMapper
			->expects($this->once())
			->method('deleteById')
			->with(42);

		$this->assertEquals(new DataResponse(42), $this->apiController->deleteSubmission(1, 42));
	}

	public function dataTestDeletePermission() {
		return [
			[
				[
					'formId' => 1,
				],
				[
					'id' => 1,
					'title' => 'Name',
					'hash' => 'hash',
					'access' => [
						'permitAllUsers' => false,
						'showToAllUsers' => false,
					],
					'ownerId' => 'currentUser',
					'description' => '',
					'expires' => 0,
					'isAnonymous' => false,
					'submitMultiple' => false,
					'showExpiration' => false
				],
			]
		];
	}

	public function testTransferOwnerNotOwner() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('otherUser');

		$this->formsService
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_EDIT)
			->willReturn($form);

		$this->expectException(OCSForbiddenException::class);
		$this->apiController->updateForm(1, ['ownerId' => 'newOwner']);
	}

	public function testTransferNewOwnerNotFound() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('currentUser');

		$this->formsService
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_EDIT)
			->willReturn($form);

		$this->formsService
			->method('canEditForm')
			->with($form)
			->willReturn(true);

		$this->userManager->expects($this->once())
			->method('get')
			->with('newOwner')
			->willReturn(null);

		$this->expectException(OCSBadRequestException::class);
		$this->apiController->updateForm(1, ['ownerId' => 'newOwner']);
	}

	public function testTransferOwner() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('currentUser');

		$this->formsService
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_EDIT)
			->willReturn($form);

		$this->formsService
			->method('canEditForm')
			->with($form)
			->willReturn(true);

		$newOwner = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('newOwner')
			->willReturn($newOwner);

		$this->assertEquals(new DataResponse('newOwner'), $this->apiController->updateForm(1, ['ownerId' => 'newOwner']));
		$this->assertEquals('newOwner', $form->getOwnerId());
	}

	public function testGetSubmission_invalidForm() {
		$exception = $this->createMock(NoSuchFormException::class);
		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willThrowException($exception);
		$this->expectException(NoSuchFormException::class);
		$this->apiController->getSubmission(1, 42);
	}

	public function testGetSubmission_noPermissions() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willThrowException(new NoSuchFormException('The current user has no permission to get the results for this form'));

		$this->expectException(NoSuchFormException::class);
		$this->apiController->getSubmission(1, 42);
	}

	public function testGetSubmission_notFound() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$this->submissionService->expects($this->once()) // Changed from submissionMapper
			->method('getSubmission')
			->with(42)
			->willReturn(null); // Service returns null when submission not found

		$this->expectException(OCSNotFoundException::class);
		$this->expectExceptionMessage('Submission doesn\'t exist');
		$this->apiController->getSubmission(1, 42);
	}

	public function testGetSubmission_success() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		// Data that submissionService->getSubmission() is expected to return
		$submissionDataFromService = [
			'id' => 42,
			'formId' => 1,
			'userId' => 'jdoe',
			// Add any other fields that SubmissionService::getSubmission would return, e.g., timestamp
			'timestamp' => 1234567890
		];

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$this->submissionService->expects($this->once()) // Changed from submissionMapper
			->method('getSubmission')
			->with(42)
			->willReturn($submissionDataFromService); // Service returns an array

		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn('jdoe');
		$this->userManager->expects($this->once())
			->method('get')
			->with('jdoe')
			->willReturn($user);

		$expectedSubmissionInResponse = $submissionDataFromService;
		$expectedSubmissionInResponse['userDisplayName'] = 'jdoe';
		$this->assertEquals(new DataResponse($expectedSubmissionInResponse), $this->apiController->getSubmission(1, 42));
	}

	public function testGetSubmission_mismatchedFormId() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		// Data that submissionService->getSubmission() is expected to return
		$submissionDataFromService = [
			'id' => 42,
			'formId' => 2, // Mismatched formId
			'userId' => 'jdoe',
			'timestamp' => 1234567890
		];

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$this->submissionService->expects($this->once()) // Changed from submissionMapper
			->method('getSubmission')
			->with(42)
			->willReturn($submissionDataFromService); // Service returns an array

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Submission doesn\'t belong to given form');
		$this->apiController->getSubmission(1, 42);
	}

	public function testGetSubmission_anonymousUser() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		// Data that submissionService->getSubmission() is expected to return
		$submissionDataFromService = [
			'id' => 42,
			'formId' => 1,
			'userId' => 'anon-user-123',
			'timestamp' => 1234567890
		];

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$this->submissionService->expects($this->once()) // Changed from submissionMapper
			->method('getSubmission')
			->with(42)
			->willReturn($submissionDataFromService); // Service returns an array

		$this->l10n->expects($this->once())
			->method('t')
			->with('Anonymous response')
			->willReturn('Anonymous response');

		$expectedSubmissionInResponse = $submissionDataFromService;
		$expectedSubmissionInResponse['userDisplayName'] = 'Anonymous response';
		$this->assertEquals(new DataResponse($expectedSubmissionInResponse), $this->apiController->getSubmission(1, 42));
	}

	public function testGetSubmission_userNotFound() {
		$form = new Form();
		$form->setId(1);
		$form->setOwnerId('currentUser');

		// Data that submissionService->getSubmission() is expected to return
		$submissionDataFromService = [
			'id' => 42,
			'formId' => 1,
			'userId' => 'nonExistentUser',
			'timestamp' => 1234567890
		];

		$this->formsService->expects($this->once())
			->method('getFormIfAllowed')
			->with(1, Constants::PERMISSION_RESULTS)
			->willReturn($form);

		$this->submissionService->expects($this->once()) // Changed from submissionMapper
			->method('getSubmission')
			->with(42)
			->willReturn($submissionDataFromService); // Service returns an array

		$this->userManager->expects($this->once())
			->method('get')
			->with('nonExistentUser')
			->willReturn(null);

		$expectedSubmissionInResponse = $submissionDataFromService;
		$expectedSubmissionInResponse['userDisplayName'] = 'nonExistentUser'; // Fallback to userId
		$this->assertEquals(new DataResponse($expectedSubmissionInResponse), $this->apiController->getSubmission(1, 42));
	}

	public function testUpdateSubmission_success() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];
		$userId = 'currentUser';

		$form = new Form();
		$form->setId($formId);
		$form->setOwnerId('formOwner');
		$form->setAllowEditSubmissions(true);

		$submission = new Submission();
		$submission->setId($submissionId);
		$submission->setFormId($formId);
		$submission->setUserId($userId);

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with($formId)
			->willReturn([['id' => 'q1', 'type' => Constants::ANSWER_TYPE_SHORT, 'options' => []]]);

		$this->submissionService->expects($this->once())
			->method('validateSubmission')
			->with($this->anything(), $answers, 'formOwner'); // Removed ->willReturn(true)

		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with($submissionId)
			->willReturn($submission);

		$this->submissionMapper->expects($this->once())
			->method('update')
			->with($submission);

		$this->answerMapper->expects($this->once())
			->method('deleteBySubmission')
			->with($submissionId);

		$this->answerMapper->expects($this->once())
			->method('insert');

		$this->formsService->expects($this->once())
			->method('notifyNewSubmission')
			->with($form, $submission);

		$response = $this->apiController->updateSubmission($formId, $submissionId, $answers);
		$this->assertEquals(new DataResponse($submissionId), $response);
	}

	public function testUpdateSubmission_formNotEditable() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];

		$form = new Form();
		$form->setId($formId);
		$form->setOwnerId('formOwner');
		$form->setAllowEditSubmissions(false); // Form does not allow edits

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Can only update if allowEditSubmissions is set');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_invalidAnswers() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['invalid_answer_format']];

		$form = new Form();
		$form->setId($formId);
		$form->setOwnerId('formOwner');
		$form->setAllowEditSubmissions(true);

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with($formId)
			->willReturn([['id' => 'q1', 'type' => Constants::ANSWER_TYPE_SHORT, 'options' => []]]);

		$this->submissionService->expects($this->once())
			->method('validateSubmission')
			->willThrowException(new \InvalidArgumentException('Invalid answers'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Invalid answers');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_submissionNotFound() {
		$formId = 1;
		$submissionId = 42; // This submission ID won't be found
		$answers = ['q1' => ['answer1']];

		$form = new Form();
		$form->setId($formId);
		$form->setOwnerId('formOwner');
		$form->setAllowEditSubmissions(true);

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with($formId)
			->willReturn([['id' => 'q1', 'type' => Constants::ANSWER_TYPE_SHORT, 'options' => []]]);

		$this->submissionService->expects($this->once())
			->method('validateSubmission')
			->with($this->anything(), $answers, 'formOwner'); // Removed ->willReturn(true)

		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with($submissionId)
			->willThrowException(new DoesNotExistException('Submission not found'));

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Submission doesn\'t exist');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_mismatchedFormId() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];

		$form = new Form();
		$form->setId($formId);
		$form->setOwnerId('formOwner');
		$form->setAllowEditSubmissions(true);

		$submission = new Submission();
		$submission->setId($submissionId);
		$submission->setFormId(2); // Belongs to a different form
		$submission->setUserId('currentUser');

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with($formId)
			->willReturn([['id' => 'q1', 'type' => Constants::ANSWER_TYPE_SHORT, 'options' => []]]);

		$this->submissionService->expects($this->once())
			->method('validateSubmission')
			->with($this->anything(), $answers, 'formOwner'); // Removed ->willReturn(true)

		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with($submissionId)
			->willReturn($submission);

		$this->expectException(OCSBadRequestException::class);
		$this->expectExceptionMessage('Submission doesn\'t belong to given form');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_notOwnSubmission() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];

		$form = new Form();
		$form->setId($formId);
		$form->setOwnerId('formOwner');
		$form->setAllowEditSubmissions(true);

		$submission = new Submission();
		$submission->setId($submissionId);
		$submission->setFormId($formId);
		$submission->setUserId('anotherUser'); // Submission belongs to another user

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with($formId)
			->willReturn([['id' => 'q1', 'type' => Constants::ANSWER_TYPE_SHORT, 'options' => []]]);

		$this->submissionService->expects($this->once())
			->method('validateSubmission')
			->with($this->anything(), $answers, 'formOwner'); // Removed ->willReturn(true)

		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with($submissionId)
			->willReturn($submission);

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('Can only update your own submissions');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_loadForm_notFound() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willThrowException(new NoSuchFormException('Could not find form'));

		$this->expectException(NoSuchFormException::class);
		$this->expectExceptionMessage('Could not find form');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_loadForm_noAccess_noShare() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];

		$form = new Form();
		$form->setId($formId);

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willThrowException(new NoSuchFormException('Not allowed to access this form'));

		$this->expectException(NoSuchFormException::class);
		$this->expectExceptionMessage('Not allowed to access this form');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}

	public function testUpdateSubmission_loadForm_formExpired() {
		$formId = 1;
		$submissionId = 42;
		$answers = ['q1' => ['answer1']];

		$form = new Form();
		$form->setId($formId);

		$this->formsService->expects($this->once())
			->method('loadFormForSubmission')
			->with(1)
			->willThrowException(new OCSForbiddenException('This form is no longer taking answers'));

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('This form is no longer taking answers');
		$this->apiController->updateSubmission($formId, $submissionId, $answers);
	}
}
