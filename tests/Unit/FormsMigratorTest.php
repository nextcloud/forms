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
use OCA\Forms\Db\Option;
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

	public static function dataImportMapsOptionsOfGridAndRankingQuestions(): array {
		return [
			'current archive' => [
				'options' => [
					['id' => 11, 'order' => 1, 'text' => 'Row', 'optionType' => 'row'],
					['id' => 12, 'order' => 1, 'text' => 'Yes', 'optionType' => 'column'],
					['id' => 13, 'order' => 2, 'text' => 'No', 'optionType' => 'column'],
				],
				'rankingOptions' => [
					['id' => 21, 'order' => 1, 'text' => 'A', 'optionType' => 'choice'],
					['id' => 22, 'order' => 2, 'text' => 'B', 'optionType' => 'choice'],
				],
				'expectedOptions' => [[1, 'row'], [1, 'column'], [2, 'column'], [1, 'choice'], [2, 'choice']],
				'expectedAnswers' => ['{"500":501}', '{"500":[501,502]}', '{"500":{"501":12,"502":"13"}}', '[504,503]'],
			],
			'archive without option ids, order and type' => [
				'options' => [['text' => 'Row'], ['text' => 'Yes'], ['text' => 'No']],
				'rankingOptions' => [['text' => 'A'], ['text' => 'B']],
				'expectedOptions' => [[null, null], [null, null], [null, null], [null, null], [null, null]],
				'expectedAnswers' => ['{"11":12}', '{"11":[12,13]}', '{"11":{"12":12,"13":"13"}}', '[22,21]'],
			],
		];
	}

	/**
	 * @param list<array<string, mixed>> $options exported options of the grid question
	 * @param list<array<string, mixed>> $rankingOptions exported options of the ranking question
	 * @param list<array{0: ?int, 1: ?string}> $expectedOptions order and type of each imported option
	 * @param list<string> $expectedAnswers texts of the imported answers
	 */
	#[DataProvider('dataImportMapsOptionsOfGridAndRankingQuestions')]
	public function testImportMapsOptionsOfGridAndRankingQuestions(array $options, array $rankingOptions, array $expectedOptions, array $expectedAnswers): void {
		$question = ['isRequired' => false, 'description' => '', 'extraSettings' => []];
		$formData = [[
			'title' => 'Grid', 'description' => '', 'access' => ['permitAllUsers' => false, 'showToAllUsers' => false], 'expires' => 0,
			'isAnonymous' => false, 'submitMultiple' => false, 'allowEditSubmissions' => false, 'showExpiration' => false,
			'questions' => [
				$question + ['id' => 1, 'order' => 1, 'type' => 'grid', 'text' => 'Grid', 'options' => $options],
				$question + ['id' => 2, 'order' => 2, 'type' => 'ranking', 'text' => 'Ranking', 'options' => $rankingOptions],
			],
			'submissions' => [[
				'userId' => 'anyUser@localhost', 'timestamp' => 1651354059,
				'answers' => [
					['questionId' => 1, 'text' => '{"11":12}'],
					['questionId' => 1, 'text' => '{"11":[12,13]}'],
					['questionId' => 1, 'text' => '{"11":{"12":12,"13":"13"}}'],
					['questionId' => 2, 'text' => '[22,21]'],
				],
			]],
		]];

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('someUser');
		$importSource = $this->createMock(IImportSource::class);
		$importSource->method('getMigratorVersion')->willReturn(1);
		$importSource->method('getFileContents')->willReturn(json_encode($formData));
		$questionId = 100;
		$this->questionMapper->method('insert')->willReturnCallback(function ($question) use (&$questionId) {
			$question->setId($questionId++);
			return $question;
		});
		$optionId = 500;
		$insertedOptions = [];
		$this->optionMapper->method('insert')->willReturnCallback(function (Option $option) use (&$optionId, &$insertedOptions) {
			$option->setId($optionId++);
			$insertedOptions[] = [$option->getOrder(), $option->getOptionType()];
			return $option;
		});
		$insertedAnswers = [];
		$this->answerMapper->method('insert')->willReturnCallback(function ($answer) use (&$insertedAnswers) {
			$insertedAnswers[] = $answer->getText();
			return $answer;
		});

		$this->formsMigrator->import($user, $importSource, $this->createMock(OutputInterface::class));

		$this->assertSame($expectedOptions, $insertedOptions);
		$this->assertSame($expectedAnswers, $insertedAnswers);
	}

	public function testImportMapsJumps(): void {
		$inputJson = '[{"title":"Link","description":"","created":1646251830,"access":{"permitAllUsers":false,"showToAllUsers":false},"expires":0,"state":0,"isAnonymous":false,"submitMultiple":false,"allowEditSubmissions":false,"showExpiration":false,"lastUpdated":123456789,'
			. '"questions":[{"id":14,"order":1,"type":"multiple_unique","isRequired":false,"text":"Pet?","description":"","extraSettings":{"goTo":[{"optionId":35,"target":15},{"optionId":36,"target":15}],"next":"submit"},"options":[{"id":35,"text":"Yes"},{"text":"No"}]},'
			. '{"id":15,"order":2,"type":"short","isRequired":false,"text":"Which?","description":"","extraSettings":{},"options":[]}],'
			. '"submissions":[]}]';

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('someUser');
		$importSource = $this->createMock(IImportSource::class);
		$importSource->method('getMigratorVersion')->willReturn(1);
		$importSource->method('getFileContents')->willReturn($inputJson);
		$questionIds = [100, 101];
		$this->questionMapper->method('insert')->willReturnCallback(function ($question) use (&$questionIds) {
			$question->setId(array_shift($questionIds));
			return $question;
		});
		$optionIds = [500, 501];
		$this->optionMapper->method('insert')->willReturnCallback(function ($option) use (&$optionIds) {
			$option->setId(array_shift($optionIds));
			return $option;
		});

		$this->questionMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn ($question) => $question->getId() === 100
				&& $question->getExtraSettings() === ['goTo' => [['optionId' => 500, 'target' => 101]]]));

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
