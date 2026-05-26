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
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Comments\ICommentsManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class PageControllerTest extends TestCase {

	private PageController $pageController;
	private IRequest|MockObject $request;
	private FormMapper|MockObject $formMapper;
	private ShareMapper|MockObject $shareMapper;
	private SubmissionMapper|MockObject $submissionMapper;
	private ConfigService|MockObject $configService;
	private FormsService|MockObject $formsService;
	private IAccountManager|MockObject $accountManager;
	private IInitialState|MockObject $initialState;
	private ICommentsManager|MockObject $commentsManager;
	private IL10N|MockObject $l10n;
	private IURLGenerator|MockObject $urlGenerator;
	private IUserManager|MockObject $userManager;
	private IUserSession|MockObject $userSession;

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
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->pageController = new PageController(
			'forms',
			$this->request,
			$this->formMapper,
			$this->shareMapper,
			$this->submissionMapper,
			$this->configService,
			$this->formsService,
			$this->accountManager,
			$this->initialState,
			$this->commentsManager,
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
}
