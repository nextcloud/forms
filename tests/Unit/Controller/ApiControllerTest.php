<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Ferdinand Thiessen <rpm@fthiessen.de>
 *
 * @author Ferdinand Thiessen <rpm@fthiessen.de>
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

namespace OCA\Forms\Controller;

/**
 * mock time() function used in controllers
 * @param int|false|null $expected the value that should be returned when called
 */
function time($expected = null) {
	static $value;
	if ($expected === false) {
		$value = null;
	} elseif (!is_null($expected)) {
		$value = $expected;
	}
	// Return real time if no mocked value is set
	if (is_null($value)) {
		return \time();
	}
	return $value;
}

namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\Constants;
use OCA\Forms\Controller\ApiController;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCA\Forms\Tests\Unit\MockedMapperException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
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
		$exception = $this->createMock(MapperException::class);
		$this->formMapper->expects($this->once())
			->method('findByHash')
			->with('hash')
			->willThrowException($exception);
		$this->expectException(OCSBadRequestException::class);
		$this->apiController->getSubmissions('hash');
	}

	public function testGetSubmissions_noPermissions() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findByHash')
			->with('hash')
			->willReturn($form);
	
		$this->formsService->expects(($this->once()))
			->method('canSeeResults')
			->with($form)
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->apiController->getSubmissions('hash');
	}

	public function dataGetSubmissions() {
		return [
			'anon' => [
				'submissions' => [
					['userId' => 'anon-user-1']
				],
				'questions' => ['questions'],
				'expected' => [
					'submissions' => [
						[
							'userId' => 'anon-user-1',
							'userDisplayName' => 'Anonymous response',
						]
					],
					'questions' => ['questions'],
				]
			],
			'user' => [
				'submissions' => [
					['userId' => 'jdoe']
				],
				'questions' => ['questions'],
				'expected' => [
					'submissions' => [
						[
							'userId' => 'jdoe',
							'userDisplayName' => 'jdoe',
						]
					],
					'questions' => ['questions'],
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
		$form->setHash('hash');
		$form->setOwnerId('otherUser');

		$this->formMapper->expects($this->once())
			->method('findByHash')
			->with('hash')
			->willReturn($form);
	
		$this->formsService->expects(($this->once()))
			->method('canSeeResults')
			->with($form)
			->willReturn(true);

		$this->submissionService->expects($this->once())
			->method('getSubmissions')
			->with(1)
			->willReturn($submissions);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(1)
			->willReturn($questions);
	
		$this->assertEquals(new DataResponse($expected), $this->apiController->getSubmissions('hash'));
	}

	public function testExportSubmissions_invalidForm() {
		$exception = $this->createMock(MapperException::class);
		$this->formMapper->expects($this->once())
			->method('findByHash')
			->with('hash')
			->willThrowException($exception);
		$this->expectException(OCSBadRequestException::class);
		$this->apiController->exportSubmissions('hash');
	}

	public function testExportSubmissions_noPermissions() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findByHash')
			->with('hash')
			->willReturn($form);
	
		$this->formsService->expects(($this->once()))
			->method('canSeeResults')
			->with($form)
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->apiController->exportSubmissions('hash');
	}

	public function testExportSubmissions() {
		$form = new Form();
		$form->setId(1);
		$form->setHash('hash');
		$form->setOwnerId('currentUser');

		$this->formMapper->expects($this->once())
			->method('findByHash')
			->with('hash')
			->willReturn($form);
	
		$this->formsService->expects(($this->once()))
			->method('canSeeResults')
			->with($form)
			->willReturn(true);

		$csv = ['data' => '__data__', 'fileName' => 'some.csv'];
		$this->submissionService->expects($this->once())
			->method('getSubmissionsCsv')
			->with('hash')
			->willReturn($csv);

		$this->assertEquals(new DataDownloadResponse($csv['data'], $csv['fileName'], 'text/csv'), $this->apiController->exportSubmissions('hash'));
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
			"forms" => ['expectedForm' => [
				'id' => 7,
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
				'submissionMessage' => '',
			]]
		];
	}

	/**
	 * @dataProvider dataTestCreateNewForm()
	 */
	public function testCreateNewForm($expectedForm) {
		// Create a partial mock, as we only test newForm and not getForm
		/** @var ApiController|MockObject */
		$apiController = $this->getMockBuilder(ApiController::class)
			 ->onlyMethods(['getForm'])
			 ->setConstructorArgs(['forms',
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
			 ])->getMock();
		// Set the time that should be set for `lastUpdated`
		\OCA\Forms\Controller\time(123456789);

		$this->configService->expects($this->once())
			->method('canCreateForms')
			->willReturn(true);
		$this->formsService->expects($this->once())
			->method('generateFormHash')
			->willReturn('formHash');
		$expected = $expectedForm;
		$expected['id'] = null;
		$this->formMapper->expects($this->once())
			->method('insert')
			->with(self::callback(self::createFormValidator($expected)))
			->willReturnCallback(function ($form) {
				$form->setId(7);
				return $form;
			});
		$apiController->expects($this->once())
			->method('getForm')
			->with(7)
			->willReturn(new DataResponse('succeeded'));
		$this->assertEquals(new DataResponse('succeeded'), $apiController->newForm());
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
				'callback' => fn ($id): Form => $this->throwMockedException(MockedMapperException::class),
				'exception' => OCSBadRequestException::class
			],
			'not owned' => [
				'canCreate' => true,
				'callback' => function ($id): Form {
					$form = new Form();
					$form->setId($id);
					$form->setOwnerId('otherUser');
					return $form;
				},
				'exception' => OCSForbiddenException::class
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
		$this->formMapper->expects($canCreate ? $this->once() : $this->never())
			->method('findById')
			->with(7)
			->willReturnCallback($callback);
		$this->expectException($exception);
		$this->apiController->cloneForm(7);
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
		$this->formMapper->expects($this->once())
			->method('findById')
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

		/** @var ApiController|MockObject */
		$apiController = $this->getMockBuilder(ApiController::class)
			->onlyMethods(['getForm'])
			->setConstructorArgs(['forms',
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
			])
			->getMock();

		$apiController->expects($this->once())
			->method('getForm')
			->with(14)
			->willReturn(new DataResponse('success'));
		$this->assertEquals(new DataResponse('success'), $apiController->cloneForm(7));
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

	public function testInsertSubmission_answers() {
		$form = new Form();
		$form->setId(1);

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
		];

		$answers = [
			1 => ['3'],
			2 => ['2', '5', Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX . 'other answer'],
			3 => ['short anwer'],
			4 => ['ignore unknown question'],
		];

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(1)
			->willReturn($questions);

		$this->formAccess();

		$this->submissionService
			->method('validateSubmission')
			->willReturn(true);

		$this->submissionMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function ($submission) {
				$submission->setId(12);
				return true;
			}));

		$this->answerMapper->expects($this->exactly(4))
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
			->method('setLastUpdatedTimestamp')
			->with(1);

		$this->formsService->expects($this->once())
			->method('notifyNewSubmission')
			->with($form, 'currentUser');

		$this->apiController->insertSubmission(1, $answers, '');
	}

	public function testInsertSubmission_formNotFound() {
		$exception = $this->createMock(MapperException::class);
		$this->formMapper->expects($this->once())
			->method('findById')
			->with(1)
			->willThrowException($exception);
		$this->expectException(OCSBadRequestException::class);
		$this->apiController->insertSubmission(1, [], '');
	}

	/**
	 * Values for the formsService mock object for the following methods: hasUserAccess, hasFormExpired, canSubmit.
	 */
	public function dataForCheckForbiddenException() {
		return [
			'user_dont_have_access_to_form' => [false, true, true],
			'form_expired' => [true, true, true],
			'not_allowed_to_submit' => [true, false, false],
		];
	}

	/**
	 * @dataProvider dataForCheckForbiddenException()
	 */
	public function testInsertSubmission_forbiddenException($hasUserAccess, $hasFormExpired, $canSubmit) {
		$form = new Form();
		$form->setId(1);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(1)
			->willReturn($form);

		$this->submissionService
			->method('validateSubmission')
			->willReturn(true);

		$this->formAccess($hasUserAccess, $hasFormExpired, $canSubmit);

		$this->expectException(OCSForbiddenException::class);

		$this->apiController->insertSubmission(1, [], '');
	}

	public function testInsertSubmission_validateSubmission() {
		$form = new Form();
		$form->setId(1);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with(1)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(1)
			->willReturn([]);

		$this->formAccess();

		$this->submissionService
			->method('validateSubmission')
			->willReturn(false);

		$this->expectException(OCSBadRequestException::class);

		$this->apiController->insertSubmission(1, [], '');
	}

	public function testDeleteSubmissionNotFound() {
		$exception = $this->createMock(MapperException::class);

		$this->submissionMapper
			->expects($this->once())
			->method('findById')
			->with(42)
			->willThrowException($exception);

		$this->expectException(OCSBadRequestException::class);
		$this->apiController->deleteSubmission(42);
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

		$this->formMapper
			->method('findById')
			->with(1)
			->willReturn($form);

		$this->formsService
			->expects($this->once())
			->method('canDeleteResults')
			->with($form)
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);
		$this->apiController->deleteSubmission(42);
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

		$this->formMapper
			->method('findById')
			->with(1)
			->willReturn($form);

		$this->formsService
			->expects($this->once())
			->method('canDeleteResults')
			->with($form)
			->willReturn(true);

		$this->submissionMapper
			->expects($this->once())
			->method('deleteById')
			->with(42);

		$this->formsService
			->expects($this->once())
			->method('setLastUpdatedTimestamp')
			->with($formData['id']);

		$this->assertEquals(new DataResponse(42), $this->apiController->deleteSubmission(42));
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
}
