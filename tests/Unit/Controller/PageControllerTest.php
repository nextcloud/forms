<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\Controller\PageController;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionVerificationService;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;

use Test\TestCase;

class PageControllerTest extends TestCase {

	/** @var PageController */
	private $pageController;

	/** @var IRequest|MockObject */
	private $request;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var ShareMapper|MockObject */
	private $shareMapper;

	/** @var SubmissionMapper|MockObject */
	private $submissionMapper;

	/** @var ConfigService|MockObject */
	private $configService;

	/** @var FormsService|MockObject */
	private $formsService;

	/** @var IAccountManager|MockObject */
	private $accountManager;

	/** @var IInitialState|MockObject */
	private $initialState;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IUserSession|MockObject */
	private $userSession;
	/** @var SubmissionVerificationService|MockObject */
	private $submissionVerificationService;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->submissionMapper = $this->createMock(SubmissionMapper::class);
		$this->configService = $this->createMock(ConfigService::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->submissionVerificationService = $this->createMock(SubmissionVerificationService::class);

		$this->pageController = new PageController(
			'forms',
			$this->request,
			$this->formMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->configService,
			$this->formsService,
			$this->submissionVerificationService,
			$this->accountManager,
			$this->initialState,
			$this->l10n,
			$this->urlGenerator,
			$this->userManager,
			$this->userSession
		);
	}

	public function testSetEmbeddedCSP() {
		/** @var MockObject */
		$response = $this->createMock(TemplateResponse::class);
		$response->expects($this->once())->method('addHeader')->with('X-Frame-Options', 'ALLOW');
		$response->expects($this->once())
			->method('setContentSecurityPolicy')
			->with(self::callback(fn (ContentSecurityPolicy $csp): bool => preg_match('/frame-ancestors[^;]* \*[ ;]/', $csp->buildPolicy()) !== false));
		TestCase::invokePrivate($this->pageController, 'setEmbeddedCSP', [$response]);
	}

	public function testVerifySubmissionEmailSuccess(): void {
		$token = '0123456789abcdef0123456789abcdef0123456789abcdef';
		$this->submissionVerificationService->expects($this->once())
			->method('verifyToken')
			->with($token)
			->willReturn(true);

		$this->l10n->method('t')
			->willReturnMap([
				['Email address verified', [], 'Email address verified'],
				['Your email address has been verified successfully. You can close this page now.', [], 'verified message'],
				['Forms', [], 'Forms'],
			]);

		$response = $this->pageController->verifySubmissionEmail($token);

		$this->assertInstanceOf(PublicTemplateResponse::class, $response);
		$this->assertSame('verify', $response->getTemplateName());
		$this->assertSame([
			'verified' => true,
			'headline' => 'Email address verified',
			'message' => 'verified message',
		], $response->getParams());
	}

	public function testVerifySubmissionEmailFailure(): void {
		$token = 'fedcba9876543210fedcba9876543210fedcba9876543210';
		$this->submissionVerificationService->expects($this->once())
			->method('verifyToken')
			->with($token)
			->willReturn(false);

		$this->l10n->method('t')
			->willReturnMap([
				['Email verification failed', [], 'Email verification failed'],
				['The verification link is invalid or expired.', [], 'failed message'],
				['Forms', [], 'Forms'],
			]);

		$response = $this->pageController->verifySubmissionEmail($token);

		$this->assertInstanceOf(PublicTemplateResponse::class, $response);
		$this->assertSame('verify', $response->getTemplateName());
		$this->assertSame([
			'verified' => false,
			'headline' => 'Email verification failed',
			'message' => 'failed message',
		], $response->getParams());
	}
}
