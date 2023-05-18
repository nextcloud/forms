<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;

use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
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

use Psr\Log\LoggerInterface;

class PageController extends Controller {
	private const TEMPLATE_MAIN = 'main';

	protected $appName;

	/** @var FormMapper */
	private $formMapper;

	/** @var ShareMapper */
	private $shareMapper;

	/** @var ConfigService */
	private $configService;

	/** @var FormsService */
	private $formsService;

	/** @var IAccountManager */
	protected $accountManager;

	/** @var IInitialState */
	private $initialState;

	/** @var IL10N */
	private $l10n;

	/** @var LoggerInterface */
	private $logger;

	/** @var IRequest */
	protected $request;

	/** @var IUrlGenerator */
	private $urlGenerator;

	/** @var IUserManager */
	private $userManager;
	
	/** @var IUserSession */
	private $userSession;

	public function __construct(string $appName,
		IRequest $request,
		FormMapper $formMapper,
		ShareMapper $shareMapper,
		ConfigService $configService,
		FormsService $formsService,
		IAccountManager $accountManager,
		IInitialState $initialState,
		IL10N $l10n,
		LoggerInterface $logger,
		IUrlGenerator $urlGenerator,
		IUserManager $userManager,
		IUserSession $userSession) {
		parent::__construct($appName, $request);

		$this->appName = $appName;

		$this->formMapper = $formMapper;
		$this->shareMapper = $shareMapper;
		$this->configService = $configService;
		$this->formsService = $formsService;

		$this->accountManager = $accountManager;
		$this->initialState = $initialState;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->request = $request;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		Util::addScript($this->appName, 'forms-main');
		Util::addStyle($this->appName, 'forms');
		$this->insertHeaderOnIos();
		$this->initialState->provideInitialState('maxStringLengths', Constants::MAX_STRING_LENGTHS);
		$this->initialState->provideInitialState('appConfig', $this->configService->getAppConfig());
		return new TemplateResponse($this->appName, self::TEMPLATE_MAIN, [
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function views(): TemplateResponse {
		return $this->index();
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @param string $hash
	 * @return RedirectResponse|TemplateResponse Redirect to login or internal view.
	 */
	public function internalLinkView(string $hash): Response {
		$internalView = $this->urlGenerator->linkToRoute('forms.page.views', ['hash' => $hash, 'view' => 'submit']);

		if ($this->userSession->isLoggedIn()) {
			// Redirect to internal Submit View
			return new RedirectResponse($internalView);
		}

		// For legacy-support, show public template
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (DoesNotExistException $e) {
			return $this->provideEmptyContent(Constants::EMPTY_NOTFOUND);
		}
		if (isset($form->getAccess()['legacyLink'])) {
			// Inject style on all templates
			Util::addStyle($this->appName, 'forms');

			// Has form expired
			if ($this->formsService->hasFormExpired($form->getId())) {
				return $this->provideEmptyContent(Constants::EMPTY_EXPIRED, $form);
			}

			// Public Template to fill the form
			Util::addScript($this->appName, 'forms-submit');
			$this->insertHeaderOnIos();
			$this->initialState->provideInitialState('form', $this->formsService->getPublicForm($form->getId()));
			$this->initialState->provideInitialState('isLoggedIn', $this->userSession->isLoggedIn());
			$this->initialState->provideInitialState('shareHash', $hash);
			$this->initialState->provideInitialState('maxStringLengths', Constants::MAX_STRING_LENGTHS);
			return $this->provideTemplate(self::TEMPLATE_MAIN, $form);
		}

		// Otherwise Redirect to login (& then internal view)
		return new RedirectResponse($this->urlGenerator->linkToRoute('core.login.showLoginForm', ['redirect_url' => $internalView]));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @param string $hash Public sharing hash.
	 * @return TemplateResponse Public template.
	 */
	public function publicLinkView(string $hash): Response {
		// Inject style on all templates
		Util::addStyle($this->appName, 'forms');

		try {
			$share = $this->shareMapper->findPublicShareByHash($hash);
			$form = $this->formMapper->findById($share->getFormId());
		} catch (DoesNotExistException $e) {
			return $this->provideEmptyContent(Constants::EMPTY_NOTFOUND);
		}

		// Has form expired
		if ($this->formsService->hasFormExpired($form->getId())) {
			return $this->provideEmptyContent(Constants::EMPTY_EXPIRED, $form);
		}

		// Main Template to fill the form
		Util::addScript($this->appName, 'forms-submit');
		$this->insertHeaderOnIos();
		$this->initialState->provideInitialState('form', $this->formsService->getPublicForm($form->getId()));
		$this->initialState->provideInitialState('isLoggedIn', $this->userSession->isLoggedIn());
		$this->initialState->provideInitialState('shareHash', $hash);
		$this->initialState->provideInitialState('maxStringLengths', Constants::MAX_STRING_LENGTHS);
		return $this->provideTemplate(self::TEMPLATE_MAIN, $form, ['id-app-navigation' => null]);
	}

	public function provideEmptyContent(string $renderAs, Form $form = null): TemplateResponse {
		Util::addScript($this->appName, 'forms-emptyContent');
		$this->initialState->provideInitialState('renderAs', $renderAs);
		return $this->provideTemplate(self::TEMPLATE_MAIN, $form);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @param string $template
	 * @param Form $form Necessary to set header on public forms, not necessary for 'notfound'-template
	 * @return TemplateResponse
	 */
	public function provideTemplate(string $template, Form $form = null, array $options = []): TemplateResponse {
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
				$response->setHeaderTitle($form->getTitle());

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
	public function insertHeaderOnIos(): void {
		$USER_AGENT_IPHONE_SAFARI = '/^Mozilla\/5\.0 \(iPhone[^)]+\) AppleWebKit\/[0-9.]+ \(KHTML, like Gecko\) Version\/[0-9.]+ Mobile\/[0-9.A-Z]+ Safari\/[0-9.A-Z]+$/';
		if (preg_match($USER_AGENT_IPHONE_SAFARI, $this->request->getHeader('User-Agent'))) {
			Util::addHeader('meta', [
				'name' => 'viewport',
				'content' => 'width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1'
			]);
		}
	}
}
