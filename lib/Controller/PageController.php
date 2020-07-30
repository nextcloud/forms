<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
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

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;

use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {
	private const TEMPLATE_EXPIRED = 'expired';
	private const TEMPLATE_MAIN = 'main';
	private const TEMPLATE_NOSUBMIT = 'nosubmit';
	private const TEMPLATE_NOTFOUND = 'notfound';

	protected $appName;

	/** @var FormMapper */
	private $formMapper;
	
	/** @var FormsService */
	private $formsService;

	/** @var IAccountManager */
	protected $accountManager;
	
	/** @var IGroupManager */
	private $groupManager;
	
	/** @var IInitialStateService */
	private $initialStateService;

	/** @var IL10N */
	private $l10n;

	/** @var ILogger */
	private $logger;

	/** @var IUserManager */
	private $userManager;
	
	/** @var IUserSession */
	private $userSession;

	/** @var Array
	 *
	 * Maximum String lengths, the database is set to store.
	 */
	private $maxStringLengths = [
		'formTitle' => 256,
		'formDescription' => 8192,
		'questionText' => 2048,
		'optionText' => 1024,
		'answerText' => 4096,
	];

	public function __construct(string $appName,
								IRequest $request,
								FormMapper $formMapper,
								FormsService $formsService,
								IAccountManager $accountManager,
								IGroupManager $groupManager,
								IInitialStateService $initialStateService,
								IL10N $l10n,
								ILogger $logger,
								IUserManager $userManager,
								IUserSession $userSession) {
		parent::__construct($appName, $request);

		$this->appName = $appName;

		$this->formMapper = $formMapper;
		$this->formsService = $formsService;

		$this->accountManager = $accountManager;
		$this->groupManager = $groupManager;
		$this->initialStateService = $initialStateService;
		$this->l10n = $l10n;
		$this->logger = $logger;
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
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
		return new TemplateResponse($this->appName, self::TEMPLATE_MAIN);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @param string $hash
	 * @return TemplateResponse
	 */
	public function gotoForm($hash): ?TemplateResponse {
		// Inject style on all templates
		Util::addStyle($this->appName, 'forms');

		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (DoesNotExistException $e) {
			return $this->provideTemplate(self::TEMPLATE_NOTFOUND);
		}

		// Does the user have access to form
		if (!$this->formsService->hasUserAccess($form->getId())) {
			return $this->provideTemplate(self::TEMPLATE_NOTFOUND);
		}

		// Does the user have permissions to submit (resp. submitOnce)
		if (!$this->formsService->canSubmit($form->getId())) {
			return $this->provideTemplate(self::TEMPLATE_NOSUBMIT, $form);
		}

		// Has form expired
		if ($form->getExpires() !== 0 && time() > $form->getExpires()) {
			return $this->provideTemplate(self::TEMPLATE_EXPIRED, $form);
		}

		// Main Template to fill the form
		Util::addScript($this->appName, 'forms-submit');
		$this->initialStateService->provideInitialState($this->appName, 'form', $this->formsService->getPublicForm($form->getId()));
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
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
	public function provideTemplate(string $template, Form $form = null): ?TemplateResponse {
		// If not logged in, use PublicTemplate
		if (!$this->userSession->isLoggedIn()) {
			Util::addStyle($this->appName, 'public');
			$response = new PublicTemplateResponse($this->appName, $template);

			// Set Header
			$response->setHeaderTitle($this->l10n->t('Forms'));
			if ($template !== self::TEMPLATE_NOTFOUND) {
				$response->setHeaderTitle($form->getTitle());

				// Get owner and check display name privacy settings
				$owner = $this->userManager->get($form->getOwnerId());
				if ($owner instanceof IUser) {
					$ownerAccount = $this->accountManager->getAccount($owner);

					$ownerName = $ownerAccount->getProperty(IAccountManager::PROPERTY_DISPLAYNAME);
					if ($ownerName->getScope() === IAccountManager::VISIBILITY_PUBLIC) {
						$response->setHeaderDetails($this->l10n->t('Shared by %s', [$ownerName->getValue()]));
					}
				}
			}

			return $response;
		}

		return new TemplateResponse($this->appName, $template);
	}
}
