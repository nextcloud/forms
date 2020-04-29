<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Marcel Klehr <mklehr@gmx.net>
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
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {
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
		'formDescription' => 2048,
		'questionText' => 2048,
		'optionText' => 1024,
		'answerText' => 2048,
	];

	public function __construct(string $appName,
								IRequest $request,
								FormMapper $formMapper,
								FormsService $formsService,
								IAccountManager $accountManager,
								IGroupManager $groupManager,
								IInitialStateService $initialStateService,
								IL10N $l10n,
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
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms');
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
		return new TemplateResponse($this->appName, 'main');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function createForm(): TemplateResponse {
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms');
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
		return new TemplateResponse($this->appName, 'main');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * TODO: Implement cloning
	 *
	 * @return TemplateResponse
	 */
	public function cloneForm(): TemplateResponse {
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms');
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
		return new TemplateResponse($this->appName, 'main');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function editForm(): TemplateResponse {
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms');
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
		return new TemplateResponse($this->appName, 'main');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function getResult(): TemplateResponse {
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms');
		return new TemplateResponse($this->appName, 'main');
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

		// TODO: check if already submitted

		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (DoesNotExistException $e) {
			return new TemplateResponse('forms', 'notfound');
		}

		// Does the user have permissions to display
		if (!$this->hasUserAccess($form)) {
			return new TemplateResponse('forms', 'notfound');
		}

		// Has form expired
		if ($form->getExpires() !== 0 && time() > $form->getExpires()) {
			return new TemplateResponse('forms', 'expired');
		}

		Util::addScript($this->appName, 'submit');
		$this->initialStateService->provideInitialState($this->appName, 'form', $this->formsService->getForm($form->getId()));
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);

		if (!$this->userSession->isLoggedIn()) {
			Util::addStyle($this->appName, 'public');
			$response = new PublicTemplateResponse($this->appName, 'main');
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

			return $response;
		}

		return new TemplateResponse($this->appName, 'main');
	}

	/**
	 * @NoAdminRequired
	 * Check if user has access to this form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	private function hasUserAccess(Form $form): bool {
		$access = $form->getAccess();
		$ownerId = $form->getOwnerId();
		$user = $this->userSession->getUser();

		if ($access['type'] === 'public') {
			return true;
		}
		
		// Refuse access, if not public and no user logged in.
		if (!$user) {
			return false;
		}

		// Always grant access to owner.
		if ($ownerId === $user->getUID()) {
			return true;
		}

		// Refuse access, if SubmitOnce is set and user already has taken part.
		if ($form->getSubmitOnce()) {
			$participants = $this->submissionMapper->findParticipantsByForm($form->getId());
			foreach ($participants as $participant) {
				if ($participant === $user->getUID()) {
					return false;
				}
			}
		}

		// Now all remaining users are allowed, if access-type 'registered'.
		if ($access['type'] === 'registered') {
			return true;
		}

		// Selected Access remains.
		// Grant Access, if user is in users-Array.
		if (in_array($user->getUID(), $access['users'])) {
			return true;
		}

		// Check if access granted by group.
		foreach ($access['groups'] as $group) {
			if ($this->groupManager->isInGroup($user->getUID(), $group)) {
				return true;
			}
		}

		// None of the possible access-options matched.
		return false;
	}
}
