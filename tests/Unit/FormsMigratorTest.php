<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit;

use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\FormsMigrator;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class FormsMigratorTest extends TestCase {

	private FormsMigrator $formsMigrator;
	private AnswerMapper|MockObject $answerMapper;
	private FormMapper|MockObject $formMapper;
	private OptionMapper|MockObject $optionMapper;
	private QuestionMapper|MockObject $questionMapper;
	private SubmissionMapper|MockObject $submissionMapper;
	private FormsService|MockObject $formsService;
	private SubmissionService|MockObject $submissionService;
	private IL10N|MockObject $l10n;
	private IUserManager|MockObject $userManager;

	public function setUp(): void {
		parent::setUp();

		$this->answerMapper = $this->createMock(AnswerMapper::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->optionMapper = $this->createMock(OptionMapper::class);
		$this->questionMapper = $this->createMock(QuestionMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->submissionService = $this->createMock(SubmissionService::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->formsMigrator = new FormsMigrator(
			$this->answerMapper,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->submissionMapper,
			$this->formsService,
			$this->submissionService,
			$this->l10n,
			$this->userManager
		);
	}

	public static function dataExport() {
		return [
			'exactlyOneOfEach' => [
				'expectedJson' => <<<'JSON'
[
  {
    "title": "Link",
    "description": "",
    "fileId": null,
    "fileFormat": null,
    "created": 1646251830,
    "access": {
      "permitAllUsers": false,
      "showToAllUsers": false
    },
    "expires": 0,
    "opens": 0,
	"state": 0,
	"lockedBy": null,
	"lockedUntil": null,
	"maxSubmissions": null,
    "isAnonymous": false,
    "submitMultiple": false,
    "allowEditSubmissions": false,
    "showExpiration": false,
    "lastUpdated": 123456789,
	"submissionMessage": "Back to website",
	"confirmationEmailEnabled": false,
	"confirmationEmailSubject": "Thanks",
	"confirmationEmailBody": "Body",
	"confirmationEmailQuestionId": null,
	"allowComments": false,
	"questions": [
      {
        "id": 14,
        "order": 2,
        "type": "multiple",
        "isRequired": false,
        "text": "checkbox",
        "description": "huhu",
        "extraSettings": {},
        "options": [
          {
            "id": 35,
            "text": "ans1"
          }
        ]
      }
    ],
    "submissions": [
      {
        "userId": "anyUser@localhost",
        "timestamp": 1651354059,
        "answers": [
          {
            "questionId": 14,
            "text": "ans1"
          }
        ]
      }
    ]
  }
]
JSON
			]
		];
	}

	/**
	 * @dataProvider dataExport
	 *
	 * @param string $expectedJson
	 */
	public function testExport(string $expectedJson) {
		$user = $this->createMock(IUser::class);
		$exportDestination = $this->createMock(IExportDestination::class);
		$output = $this->createMock(OutputInterface::class);

		$output->expects($this->once())
			->method('writeln');

		$user->expects($this->once())
			->method('getUID')
			->willReturn('someUser');

		$form = new Form();
		$form->setId(42);
		$form->setState(0);
		$form->setLockedBy(null);
		$form->setLockedBy(null);
		$form->setHash('abcdefg');
		$form->setTitle('Link');
		$form->setDescription('');
		$form->setOwnerId('someUser');
		$form->setCreated(1646251830);
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false
		]);
		$form->setExpires(0);
		$form->setIsAnonymous(false);
		$form->setSubmitMultiple(false);
		$form->setAllowEditSubmissions(false);
		$form->setShowExpiration(false);
		$form->setLastUpdated(123456789);
		$form->setSubmissionMessage('Back to website');
		$form->setConfirmationEmailEnabled(false);
		$form->setConfirmationEmailSubject('Thanks');
		$form->setConfirmationEmailBody('Body');
		$form->setConfirmationEmailQuestionId(null);
		$form->setAllowComments(false);

		$this->formsService->expects($this->once())
			->method('getQuestions')
			->with(42)
			->willReturn([
				[
					'id' => 14,
					'formId' => 42,
					'order' => 2,
					'type' => 'multiple',
					'isRequired' => false,
					'text' => 'checkbox',
					'description' => 'huhu',
					'extraSettings' => (object)[],
					'options' => [
						[
							'id' => 35,
							'questionId' => 14,
							'text' => 'ans1'
						]
					]
				]
			]);
		$this->submissionService->expects($this->once())
			->method('getSubmissions')
			->with(42)
			->willReturn([
				[
					'id' => 28,
					'formId' => 42,
					'userId' => 'anyUser',
					'timestamp' => 1651354059,
					'answers' => [
						[
							'id' => 35,
							'submissionId' => 28,
							'questionId' => 14,
							'text' => 'ans1'
						]
					]
				]
			]);

		$this->formMapper->expects($this->once())
			->method('findAllByOwnerId')
			->with('someUser')
			->willReturn([$form]);

		$any_user = $this->createMock(IUser::class);
		$any_user->expects($this->once())
			->method('getCloudId')
			->willReturn('anyUser@localhost');

		$this->userManager->expects($this->once())
			->method('get')
			->with('anyUser')
			->willReturn($any_user);

		$exportDestination->expects($this->once())
			->method('addFileContents')
			->will($this->returnCallback(function ($path, $jsonData) use ($expectedJson): void {
				$this->assertJsonStringEqualsJsonString($expectedJson, $jsonData);
			}));
		$this->formsMigrator->export($user, $exportDestination, $output);
	}

	public static function dataImport() {
		$base = '[{"title":"Link","description":"","created":1646251830,"access":{"permitAllUsers":false,"showToAllUsers":false},"expires":0,"state":0,"lockedBy":null,"lockedUntil":null,"maxSubmissions":null,"isAnonymous":false,"submitMultiple":false,"allowEditSubmissions":false,"showExpiration":false,"lastUpdated":123456789,"confirmationEmailEnabled":true,"confirmationEmailSubject":"Thanks","confirmationEmailBody":"Body","confirmationEmailQuestionId":null,"allowComments":false,"questions":[{"id":14,"order":2,"type":"multiple","isRequired":false,"text":"checkbox","description":"huhu","extraSettings":{},"options":[{"text":"ans1"}]}],"submissions":[{"userId":"anyUser@localhost","timestamp":1651354059,"answers":[{"questionId":14,"text":"ans1"}]}]}]';
		$withOpeningDate = json_decode($base, true);
		$withOpeningDate[0]['opens'] = 1700000000;
		$withOpeningDateAfterExpiration = json_decode($base, true);
		$withOpeningDateAfterExpiration[0]['opens'] = 1700003600;
		$withOpeningDateAfterExpiration[0]['expires'] = 1700000000;

		return [
			'exactlyOneOfEach' => [
				'inputJson' => $base,
				'expectedOpens' => 0,
			],
			'withOpeningDate' => [
				'inputJson' => json_encode($withOpeningDate),
				'expectedOpens' => 1700000000,
			],
			'withOpeningDateAfterExpiration' => [
				'inputJson' => json_encode($withOpeningDateAfterExpiration),
				'expectedOpens' => 0,
			],
		];
	}

	/**
	 * @param string $inputJson JsonString to input
	 * @param int $expectedOpens Opening date the imported form is expected to have
	 */
	#[DataProvider('dataImport')]
	public function testImport(string $inputJson, int $expectedOpens) {
		$user = $this->createMock(IUser::class);
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(OutputInterface::class);

		$importSource->expects($this->once())
			->method('getMigratorVersion')
			->with('forms')
			->willReturn(1);
		$importSource->expects($this->once())
			->method('getFileContents')
			->willReturn($inputJson);

		$user->expects($this->once())
			->method('getUID')
			->willReturn('someUser');

		$this->formsService->expects($this->once())
			->method('generateFormHash')
			->willReturn('abcdefg');

		$this->formMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Form $form) use ($expectedOpens) {
				$this->assertSame($expectedOpens, $form->getOpens());
				$this->assertTrue($form->getConfirmationEmailEnabled());
				$this->assertSame('Thanks', $form->getConfirmationEmailSubject());
				$this->assertSame('Body', $form->getConfirmationEmailBody());
				return $form;
			});
		$this->questionMapper->expects($this->once())->method('insert');
		$this->optionMapper->expects($this->once())->method('insert');
		$this->submissionMapper->expects($this->once())->method('insert');
		$this->answerMapper->expects($this->once())->method('insert');

		$this->formsMigrator->import($user, $importSource, $output);
	}

	public function testExportListsSubquestionsAsQuestions(): void {
		$form = new Form();
		$form->setId(42);
		$form->setOwnerId('someUser');
		$form->setAccess(['permitAllUsers' => false, 'showToAllUsers' => false]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('someUser');
		$this->formMapper->method('findAllByOwnerId')->willReturn([$form]);
		$this->submissionService->method('getSubmissions')->willReturn([]);
		$this->formsService->method('getQuestions')->with(42)->willReturn([[
			'id' => 14,
			'formId' => 42,
			'type' => 'multiple_unique',
			'extraSettings' => ['branches' => [[
				'id' => 'branch-1',
				'conditions' => [['optionId' => 35]],
				'subQuestions' => [['id' => 15, 'formId' => 42, 'parentQuestionId' => 14, 'branchId' => 'branch-1', 'options' => []]],
			]]],
			'options' => [['id' => 35, 'questionId' => 14, 'text' => 'Yes']],
		]]);

		$exportDestination = $this->createMock(IExportDestination::class);
		$exportDestination->expects($this->once())
			->method('addFileContents')
			->willReturnCallback(function ($path, $jsonData): void {
				$questions = json_decode($jsonData, true)[0]['questions'];
				$this->assertSame([14, 15], array_column($questions, 'id'));
				$this->assertSame([['id' => 'branch-1', 'conditions' => [['optionId' => 35]]]], $questions[0]['extraSettings']['branches']);
				$this->assertSame([['id' => 35, 'text' => 'Yes']], $questions[0]['options']);
				$this->assertSame(14, $questions[1]['parentQuestionId']);
				$this->assertSame('branch-1', $questions[1]['branchId']);
			});

		$this->formsMigrator->export($user, $exportDestination, $this->createMock(OutputInterface::class));
	}

	public function testImportQuestionWithBranches(): void {
		$inputJson = '[{"title":"Link","description":"","created":1646251830,"access":{"permitAllUsers":false,"showToAllUsers":false},"expires":0,"state":0,"isAnonymous":false,"submitMultiple":false,"allowEditSubmissions":false,"showExpiration":false,"lastUpdated":123456789,'
			. '"questions":['
			. '{"id":14,"order":1,"type":"multiple_unique","isRequired":false,"text":"Pets?","description":"","extraSettings":{"branches":[{"id":"branch-1","conditions":[{"optionId":35}]}]},"options":[{"id":35,"text":"Yes"}]},'
			. '{"id":15,"order":1,"type":"short","isRequired":false,"text":"Name?","description":"","extraSettings":{},"options":[],"parentQuestionId":14,"branchId":"branch-1"}'
			. '],'
			. '"submissions":[{"userId":"anyUser@localhost","timestamp":1651354059,"answers":[{"questionId":14,"text":"Yes"},{"questionId":15,"text":"Rex"}]}]}]';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('someUser');
		$importSource = $this->createMock(IImportSource::class);
		$importSource->method('getMigratorVersion')->willReturn(1);
		$importSource->method('getFileContents')->willReturn($inputJson);

		$this->formMapper->method('insert')->willReturnCallback(function (Form $form) {
			$form->setId(7);
			return $form;
		});
		$questions = [];
		$this->questionMapper->expects($this->exactly(2))
			->method('insert')
			->willReturnCallback(function ($question) use (&$questions) {
				$question->setId(100 + count($questions));
				$questions[] = $question;
				return $question;
			});
		$this->optionMapper->method('insert')->willReturnCallback(function ($option) {
			$option->setId(500);
			return $option;
		});
		// The branch conditions are pointed to the imported option
		$this->questionMapper->expects($this->once())
			->method('update')
			->willReturnCallback(function ($question) {
				$this->assertSame(100, $question->getId());
				$this->assertSame([['id' => 'branch-1', 'conditions' => [['optionId' => 500]]]], $question->getExtraSettings()['branches']);
				return $question;
			});
		$answerQuestionIds = [];
		$this->answerMapper->method('insert')->willReturnCallback(function ($answer) use (&$answerQuestionIds) {
			$answerQuestionIds[] = $answer->getQuestionId();
			return $answer;
		});

		$this->formsMigrator->import($user, $importSource, $this->createMock(OutputInterface::class));

		$this->assertSame(100, $questions[1]->getParentQuestionId());
		$this->assertSame('branch-1', $questions[1]->getBranchId());
		$this->assertSame([100, 101], $answerQuestionIds);
	}

	public function testImportSkipsAnswersOfQuestionsNotExported(): void {
		$inputJson = '[{"title":"Link","description":"","created":1646251830,"access":{"permitAllUsers":false,"showToAllUsers":false},"expires":0,"state":0,"isAnonymous":false,"submitMultiple":false,"allowEditSubmissions":false,"showExpiration":false,"lastUpdated":123456789,'
			. '"questions":[{"id":14,"order":1,"type":"short","isRequired":false,"text":"Name?","description":"","extraSettings":{},"options":[]}],'
			. '"submissions":[{"userId":"anyUser@localhost","timestamp":1651354059,"answers":[{"questionId":14,"text":"Ann"},{"questionId":13,"text":"Answer of a deleted question"}]}]}]';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('someUser');
		$importSource = $this->createMock(IImportSource::class);
		$importSource->method('getMigratorVersion')->willReturn(1);
		$importSource->method('getFileContents')->willReturn($inputJson);
		$this->questionMapper->method('insert')->willReturnCallback(function ($question) {
			$question->setId(100);
			return $question;
		});

		$this->answerMapper->expects($this->once())
			->method('insert')
			->willReturnCallback(function ($answer) {
				$this->assertSame(100, $answer->getQuestionId());
				return $answer;
			});

		$this->formsMigrator->import($user, $importSource, $this->createMock(OutputInterface::class));
	}

	public function testImport_NoVersion() {
		$user = $this->createMock(IUser::class);
		$importSource = $this->createMock(IImportSource::class);
		$output = $this->createMock(OutputInterface::class);

		$importSource->expects($this->once())
			->method('getMigratorVersion')
			->with('forms')
			->willReturn(null);
		$output->expects($this->once())
			->method('writeln');
		$importSource->expects($this->never())
			->method('getFileContents');

		$this->formsMigrator->import($user, $importSource, $output);
	}

	public function testGetId() {
		$this->assertEquals('forms', $this->formsMigrator->getId());
	}

	public function testGetDisplayName() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Forms')
			->willReturn('Translated Forms');
		$this->assertEquals('Translated Forms', $this->formsMigrator->getDisplayName());
	}

	public function testGetDescription() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Forms including questions and submissions')
			->willReturn('Translated Description');
		$this->assertEquals('Translated Description', $this->formsMigrator->getDescription());
	}

	public function testGetVersion() {
		$this->assertEquals(1, $this->formsMigrator->getVersion());
	}

	public static function dataCanImport() {
		return [
			'goodVersion' => [
				'version' => 1,
				'expected' => true
			],
			'badVersion' => [
				'version' => 2,
				'expected' => false
			],
		];
	}

	/**
	 * @dataProvider dataCanImport
	 *
	 * @param int $version Version to import
	 * @param bool $expected Expected boolean result
	 */
	public function testCanImport(int $version, bool $expected) {
		$importSource = $this->createMock(IImportSource::class);
		$importSource->expects($this->once())
			->method('getMigratorVersion')
			->willReturn($version);

		$this->assertEquals($expected, $this->formsMigrator->canImport($importSource));
	}
}
