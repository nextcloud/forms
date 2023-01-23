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
namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\Activity\ActivityManager;
use OCA\Forms\Controller\ApiController;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
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
use Test\TestCase;

use Psr\Log\LoggerInterface;

class ApiControllerTest extends TestCase {
	private ApiController $apiController;
	/** @var ActivityManager|MockObject */
	private $activityManager;
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

	public function setUp(): void {
		$this->activityManager = $this->createMock(ActivityManager::class);
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
		$userSession = $this->createMock(IUserSession::class);
		/** @var IL10N|MockObject */
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($v) {
				return $v;
			});

		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->apiController = new ApiController(
			'forms',
			$this->activityManager,
			$this->answerMapper,
			$this->formMapper,
			$this->optionMapper,
			$this->questionMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->configService,
			$this->formsService,
			$this->submissionService,
			$l10n,
			$this->logger,
			$this->request,
			$this->userManager,
			$userSession
		);
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
			->with(1)
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
			->with(1)
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
			->with(1)
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
			->with(1)
			->willReturn(true);

		$csv = ['data' => '__data__', 'fileName' => 'some.csv'];
		$this->submissionService->expects($this->once())
			->method('getSubmissionsCsv')
			->with('hash')
			->willReturn($csv);

		$this->assertEquals(new DataDownloadResponse($csv['data'], $csv['fileName'], 'text/csv'), $this->apiController->exportSubmissions('hash'));
	}
}
