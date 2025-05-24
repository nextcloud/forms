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
use PHPUnit\Framework\MockObject\MockObject;

use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class FormsMigratorTest extends TestCase {

	/** @var FormsMigrator */
	private $formsMigrator;

	/** @var AnswerMapper|MockObject */
	private $answerMapper;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var OptionMapper|MockObject */
	private $optionMapper;

	/** @var QuestionMapper|MockObject */
	private $questionMapper;

	/** @var SubmissionMapper|MockObject */
	private $submissionMapper;

	/** @var FormsService|MockObject */
	private $formsService;

	/** @var SubmissionService|MockObject */
	private $submissionService;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var IUserManager|MockObject */
	private $userManager;

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

	public function dataExport() {
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
	"state": 0,
	"lockedBy": null,
	"lockedUntil": null,
    "isAnonymous": false,
    "submitMultiple": false,
    "allowEditSubmissions": false,
    "showExpiration": false,
    "lastUpdated": 123456789,
    "submissionMessage": "Back to website",
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
			->will($this->returnCallback(function ($path, $jsonData) use ($expectedJson) {
				$this->assertJsonStringEqualsJsonString($expectedJson, $jsonData);
			}));
		$this->formsMigrator->export($user, $exportDestination, $output);
	}

	public function dataImport() {
		return [
			'exactlyOneOfEach' => [
				'$inputJson' => '[{"title":"Link","description":"","created":1646251830,"access":{"permitAllUsers":false,"showToAllUsers":false},"expires":0,"state":0,"lockedBy":null,"lockedUntil":null,"isAnonymous":false,"submitMultiple":false,"allowEditSubmissions":false,"showExpiration":false,"lastUpdated":123456789,"questions":[{"id":14,"order":2,"type":"multiple","isRequired":false,"text":"checkbox","description":"huhu","extraSettings":{},"options":[{"text":"ans1"}]}],"submissions":[{"userId":"anyUser@localhost","timestamp":1651354059,"answers":[{"questionId":14,"text":"ans1"}]}]}]'
			]
		];
	}

	/**
	 * @dataProvider dataImport
	 *
	 * @param string $inputJson JsonString to input
	 */
	public function testImport(string $inputJson) {
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

		$this->formMapper->expects($this->once())->method('insert');
		$this->questionMapper->expects($this->once())->method('insert');
		$this->optionMapper->expects($this->once())->method('insert');
		$this->submissionMapper->expects($this->once())->method('insert');
		$this->answerMapper->expects($this->once())->method('insert');

		$this->formsMigrator->import($user, $importSource, $output);
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

	public function dataCanImport() {
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
