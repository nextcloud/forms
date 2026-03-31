<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\SubmissionVerification;
use OCA\Forms\Db\SubmissionVerificationMapper;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Forms\Service\SubmissionVerificationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class SubmissionVerificationServiceTest extends TestCase {
	/** @var SubmissionVerificationMapper|MockObject */
	private $verificationMapper;
	/** @var SubmissionMapper|MockObject */
	private $submissionMapper;
	/** @var FormMapper|MockObject */
	private $formMapper;
	/** @var IEventDispatcher|MockObject */
	private $eventDispatcher;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var LoggerInterface|MockObject */
	private $logger;

	private SubmissionVerificationService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->verificationMapper = $this->createMock(SubmissionVerificationMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new SubmissionVerificationService(
			$this->verificationMapper,
			$this->submissionMapper,
			$this->formMapper,
			$this->eventDispatcher,
			$this->urlGenerator,
			$this->logger,
		);
	}

	public function testCreateVerificationTokenCreatesRecordForNewSubmission(): void {
		$submission = new Submission();
		$submission->setId(42);
		$submission->setIsVerified(false);

		$this->verificationMapper->expects($this->once())
			->method('findBySubmissionId')
			->with(42)
			->willThrowException(new DoesNotExistException('missing'));

		$this->verificationMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (SubmissionVerification $verification): bool {
				return $verification->getSubmissionId() === 42
					&& strlen($verification->getTokenHash()) === 64
					&& strlen($verification->getRecipientEmailHash()) === 64
					&& $verification->getUsed() === null;
			}));

		$token = $this->service->createVerificationToken($submission, 'USER@example.com');

		$this->assertNotNull($token);
		$this->assertEquals(48, strlen((string)$token));
	}

	public function testVerifyTokenMarksSubmissionAsVerified(): void {
		$token = '123456789012345678901234567890123456789012345678';
		$verification = new SubmissionVerification();
		$verification->setId(7);
		$verification->setSubmissionId(123);
		$verification->setRecipientEmailHash(hash('sha256', 'user@example.com'));
		$verification->setTokenHash(hash('sha256', $token));
		$verification->setExpires(time() + 3600);
		$verification->setUsed(null);

		$submission = new Submission();
		$submission->setId(123);
		$submission->setFormId(1);
		$submission->setUserId('user');
		$submission->setTimestamp(time());
		$submission->setIsVerified(false);

		$this->verificationMapper->expects($this->once())
			->method('findByTokenHash')
			->with(hash('sha256', $token))
			->willReturn($verification);

		$this->submissionMapper->expects($this->once())
			->method('findById')
			->with(123)
			->willReturn($submission);

		$this->submissionMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (Submission $updated): bool {
				return $updated->getId() === 123 && $updated->getIsVerified() === true;
			}));

		$form = new Form();
		$form->setId(1);
		$this->formMapper->expects($this->once())
			->method('findById')
			->with(1)
			->willReturn($form);

		$this->verificationMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (SubmissionVerification $updated): bool {
				return $updated->getId() === 7 && $updated->getUsed() !== null;
			}));
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (FormSubmittedEvent $event): bool {
				return $event->getTrigger() === FormSubmittedEvent::TRIGGER_VERIFIED
					&& $event->getSubmission()->getId() === 123
					&& $event->getForm()->getId() === 1;
			}));

		$this->assertTrue($this->service->verifyToken($token));
	}
}
