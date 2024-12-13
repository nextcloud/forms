<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\BackgroundJob;

use Exception;

use OCA\Forms\BackgroundJob\SyncSubmissionsWithLinkedFileJob;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\AppFramework\Utility\ITimeFactory;

use OCP\BackgroundJob\IJobList;
use OCP\Files\NotFoundException;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

use Psr\Log\LoggerInterface;
use Test\TestCase;

class SyncSubmissionsWithLinkedFileJobTest extends TestCase {
	private SyncSubmissionsWithLinkedFileJob $job;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var FormsService|MockObject */
	private $formsService;

	/** @var SubmissionService|MockObject */
	private $submissionService;

	/** @var ITimeFactory|MockObject */
	private $timeFactory;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IUserSession|MockObject */
	private $userSession;

	/** @var IJobList|MockObject */
	private $jobList;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->submissionService = $this->createMock(SubmissionService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->job = new SyncSubmissionsWithLinkedFileJob(
			$this->timeFactory,
			$this->formMapper,
			$this->formsService,
			$this->submissionService,
			$this->logger,
			$this->userManager,
			$this->userSession,
			$this->jobList
		);
	}

	public function testRunSuccessfulSync(): void {
		$formId = 1;
		$argument = ['form_id' => $formId, 'attempt' => 1];
		$form = $this->getForm($formId);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with($formId)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getFilePath')
			->with($form)
			->willReturn('some/file/path');

		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('owner_name')
			->willReturn($user);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->submissionService->expects($this->once())
			->method('writeFileToCloud')
			->with($form, 'some/file/path', $this->anything(), $this->anything());

		$this->job->run($argument);
	}

	public function testRunNotFoundException(): void {
		$formId = 1;
		$argument = ['form_id' => $formId, 'attempt' => 1];

		$this->formMapper->expects($this->once())
			->method('findById')
			->with($formId)
			->willThrowException(new NotFoundException('Test exception'));

		$this->logger->expects($this->once())
			->method('notice')
			->with('Form {formId} linked to a file that doesn\'t exist anymore', ['formId' => $formId]);

		$this->job->run($argument);
	}

	public function testRunThrowableException(): void {
		$formId = 1;
		$argument = ['form_id' => $formId, 'attempt' => 1];
		$form = $this->getForm($formId);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with($formId)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getFilePath')
			->willReturn('some/file/path');

		$this->submissionService->expects($this->once())
			->method('writeFileToCloud')
			->willThrowException(new Exception('Test exception'));

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Failed to synchronize form {formId} with the file (attempt {attempt} of {maxAttempts}), reason: {message}',
				[
					'formId' => $formId,
					'message' => 'Test exception',
					'attempt' => 1,
					'maxAttempts' => SyncSubmissionsWithLinkedFileJob::MAX_ATTEMPTS
				]
			);

		$this->jobList->expects($this->once())
			->method('scheduleAfter')
			->with(
				SyncSubmissionsWithLinkedFileJob::class,
				$this->anything(),
				['form_id' => $formId, 'attempt' => 2]
			);

		$this->job->run($argument);
	}

	public function testMaxAttemptsReached(): void {
		$formId = 1;
		$argument = ['form_id' => $formId, 'attempt' => SyncSubmissionsWithLinkedFileJob::MAX_ATTEMPTS];
		$form = $this->getForm($formId);

		$this->formMapper->expects($this->once())
			->method('findById')
			->with($formId)
			->willReturn($form);

		$this->formsService->expects($this->once())
			->method('getFilePath')
			->willReturn('some/file/path');

		$this->submissionService->expects($this->once())
			->method('writeFileToCloud')
			->willThrowException(new Exception('Test exception'));

		$this->jobList->expects($this->never())->method('add');

		$this->job->run($argument);
	}

	private function getForm(int $formId): Form {
		$form = new Form();
		$form->setId($formId);
		$form->setFileFormat('csv');
		$form->setOwnerId('owner_name');

		return $form;
	}
}
