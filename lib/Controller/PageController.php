<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Controller;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;

use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PageController extends Controller {
	private const TEMPLATE_MAIN = 'main';

	public function __construct(
		string $appName,
		IRequest $request,
		private FormMapper $formMapper,
		private ShareMapper $shareMapper,
		private SubmissionMapper $submissionMapper,
		private ConfigService $configService,
		private FormsService $formsService,
		private IAccountManager $accountManager,
		private IInitialState $initialState,
		private IL10N $l10n,
		private IUrlGenerator $urlGenerator,
		private IUserManager $userManager,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return TemplateResponse
	 */
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(?string $hash = null, ?int $submissionId = null): TemplateResponse {
		Util::addScript($this->appName, 'forms-main');
		Util::addStyle($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms-style');
		$this->insertHeaderOnIos();
		$this->initialState->provideInitialState('maxStringLengths', Constants::MAX_STRING_LENGTHS);
		$this->initialState->provideInitialState('appConfig', $this->configService->getAppConfig());

		if (isset($hash)) {
			try {
				$form = $this->formMapper->findByHash($hash);
				$this->initialState->provideInitialState('formId', $form->id);
			} catch (DoesNotExistException $e) {
				// Provide null to indicate no form was found
				$this->initialState->provideInitialState('formId', 'invalid');
			}
		}

		if (isset($submissionId)) {
			try {
				$submission = $this->submissionMapper->findById($submissionId);
				$this->initialState->provideInitialState('submissionId', $submission->id);
			} catch (DoesNotExistException $e) {
				// Ignore exception and just don't set the initialState value
			}
		}

		return new TemplateResponse($this->appName, self::TEMPLATE_MAIN, [
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);
	}

	/**
	 * @return TemplateResponse
	 */
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[FrontpageRoute(verb: 'GET', url: '/{hash}/{view}', requirements: ['hash' => '[a-zA-Z0-9]{16,}'])]
	public function views(string $hash): TemplateResponse {
		return $this->index($hash);
	}

	/**
	 * @return TemplateResponse
	 */
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[FrontpageRoute(verb: 'GET', url: '/{hash}/submit/{submissionId}', requirements: ['hash' => '[a-zA-Z0-9]{16,}', 'submissionId' => '\d+'])]
	public function submitViewWithSubmission(string $hash, int $submissionId): TemplateResponse {
		return $this->formMapper->findByHash($hash)->getAllowEditSubmissions() ? $this->index($hash, $submissionId) : $this->index($hash);
	}

	/**
	 * @param string $hash
	 * @return RedirectResponse|TemplateResponse Redirect to login or internal view.
	 */
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[PublicPage()]
	#[FrontpageRoute(verb: 'GET', url: '/{hash}', requirements: ['hash' => '[a-zA-Z0-9]{16,}'])]
	public function internalLinkView(string $hash): Response {
		$internalView = $this->urlGenerator->linkToRoute('forms.page.views', ['hash' => $hash, 'view' => 'submit']);

		if ($this->userSession->isLoggedIn()) {
			// Redirect to internal Submit View
			return new RedirectResponse($internalView);
		}

		// Otherwise Redirect to login (& then internal view)
		return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', ['redirect_url' => $internalView]));
	}

	/**
	 * @param string $hash Public sharing hash.
	 * @return TemplateResponse Public template.
	 */
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[PublicPage()]
	#[FrontpageRoute(verb: 'GET', url: '/s/{hash}', requirements: ['hash' => '[a-zA-Z0-9]{24,}'])]
	public function publicLinkView(string $hash): Response {
		try {
			$share = $this->shareMapper->findPublicShareByHash($hash);
			$form = $this->formMapper->findById($share->getFormId());
		} catch (DoesNotExistException $e) {
			return $this->provideEmptyContent(Constants::EMPTY_NOTFOUND);
		}

		return $this->createPublicSubmitView($form, $hash);
	}

	/**
	 * @param string $hash
	 * @return Response
	 */
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[PublicPage()]
	#[FrontpageRoute(verb: 'GET', url: '/embed/{hash}')]
	public function embeddedFormView(string $hash): Response {
		try {
			$share = $this->shareMapper->findPublicShareByHash($hash);
			// Check if the form is allwed to be embedded
			if (!in_array(Constants::PERMISSION_EMBED, $share->getPermissions())) {
				throw new DoesNotExistException('Shared form not allowed to be embedded');
			}

			$form = $this->formMapper->findById($share->getFormId());
		} catch (DoesNotExistException $e) {
			return $this->provideEmptyContent(Constants::EMPTY_NOTFOUND);
			// We do not handle the MultipleObjectsReturnedException as this will automatically result in a 500 error as expected
		}

		Util::addStyle($this->appName, 'embedded');
		$response = $this->createPublicSubmitView($form, $hash)
			->renderAs(TemplateResponse::RENDER_AS_BASE);

		$this->initialState->provideInitialState('isEmbedded', true);

		return $this->setEmbeddedCSP($response);
	}

	/**
	 * Create a TemplateResponse for a given public form
	 * This sets all needed headers, initial state, loads scripts and styles
	 */
	protected function createPublicSubmitView(Form $form, string $hash): TemplateResponse {
		// Has form expired
		if ($this->formsService->hasFormExpired($form)) {
			return $this->provideEmptyContent(Constants::EMPTY_EXPIRED, $form);
		}

		$this->insertHeaderOnIos();

		// Inject style on all templates
		Util::addStyle($this->appName, 'forms');
		// Main Template to fill the form
		Util::addScript($this->appName, 'forms-submit');

		$this->initialState->provideInitialState('form', $this->formsService->getPublicForm($form));
		$this->initialState->provideInitialState('isLoggedIn', $this->userSession->isLoggedIn());
		$this->initialState->provideInitialState('shareHash', $hash);
		$this->initialState->provideInitialState('maxStringLengths', Constants::MAX_STRING_LENGTHS);
		return $this->provideTemplate(self::TEMPLATE_MAIN, $form, ['id-app-navigation' => null]);
	}

	/**
	 * Provide empty content message response for a form
	 */
	protected function provideEmptyContent(string $renderAs, ?Form $form = null): TemplateResponse {
		Util::addScript($this->appName, 'forms-emptyContent');
		$this->initialState->provideInitialState('renderAs', $renderAs);
		return $this->provideTemplate(self::TEMPLATE_MAIN, $form);
	}

	/**
	 * Helper function to create a template response from a form
	 * @param string $template
	 * @param Form $form Necessary to set header on public forms, not necessary for 'notfound'-template
	 * @return TemplateResponse
	 */
	protected function provideTemplate(string $template, ?Form $form = null, array $options = []): TemplateResponse {
		Util::addStyle($this->appName, 'forms-style');
		// If not logged in, use PublicTemplate
		if (!$this->userSession->isLoggedIn()) {
			Util::addStyle($this->appName, 'public');
			$response = new PublicTemplateResponse($this->appName, $template, array_merge([
				'id-app-content' => '#app-content-vue',
				'id-app-navigation' => null,
			], $options));

			// Set Header
			$response->setHeaderTitle($this->l10n->t('Forms'));
			if ($form !== null) {
				$response->setHeaderTitle($this->l10n->t('Forms') . ' · ' . $form->getTitle());

				// Get owner and check display name privacy settings
				$owner = $this->userManager->get($form->getOwnerId());
				if ($owner instanceof IUser) {
					$ownerAccount = $this->accountManager->getAccount($owner);

					$ownerName = $ownerAccount->getProperty(IAccountManager::PROPERTY_DISPLAYNAME);
					if ($ownerName->getScope() !== IAccountManager::SCOPE_PRIVATE) {
						$response->setHeaderDetails($this->l10n->t('Shared by %s', [$ownerName->getValue()]));
					}
				}
			}
			return $response;
		}

		return new TemplateResponse($this->appName, $template, array_merge([
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		], $options));
	}

	/**
	 * Insert the extended viewport Header on iPhones to prevent automatic zooming.
	 */
	protected function insertHeaderOnIos(): void {
		$USER_AGENT_IPHONE_SAFARI = '/^Mozilla\/5\.0 \(iPhone[^)]+\) AppleWebKit\/[0-9.]+ \(KHTML, like Gecko\) Version\/[0-9.]+ Mobile\/[0-9.A-Z]+ Safari\/[0-9.A-Z]+$/';
		if (preg_match($USER_AGENT_IPHONE_SAFARI, $this->request->getHeader('User-Agent'))) {
			Util::addHeader('meta', [
				'name' => 'viewport',
				'content' => 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1'
			]);
		}
	}

	/**
	 * Set CSP options to allow the page be embedded using <iframe>
	 *
	 * @return TemplateResponse
	 */
	protected function setEmbeddedCSP(TemplateResponse $response): TemplateResponse {
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedFrameAncestorDomain('*');

		$response->addHeader('X-Frame-Options', 'ALLOW');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}
}
