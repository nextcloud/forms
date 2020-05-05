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
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;

class PageController extends Controller {
	protected $appName;

	/** @var FormMapper */
	private $formMapper;

	/** @var IURLGenerator */
	private $urlGenerator;
	
	/** @var IGroupManager */
	private $groupManager;
	
	/** @var IUserSession */
	private $userSession;
	
	/** @var IInitialStateService */
	private $initialStateService;
	
	/** @var FormsService */
	private $formService;

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
								IGroupManager $groupManager,
								IURLGenerator $urlGenerator,
								FormMapper $formMapper,
								QuestionMapper $questionMapper,
								OptionMapper $optionMapper,
								IUserSession $userSession,
								IInitialStateService $initialStateService,
								FormsService $formsService) {
		parent::__construct($appName, $request);

		$this->groupManager = $groupManager;
		$this->urlGenerator = $urlGenerator;
		$this->appName = $appName;
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->userSession = $userSession;
		$this->initialStateService = $initialStateService;
		$this->formsService = $formsService;
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
		if (!$this->formsService->canSubmit($form->getId())) {
			return new TemplateResponse('forms', 'nosubmit');
		}

		// Does the user have permissions to display
		if (!$this->formsService->hasUserAccess($form->getId())) {
			return new TemplateResponse('forms', 'notfound');
		}

		// Has form expired
		if ($form->getExpires() !== 0 && time() > $form->getExpires()) {
			return new TemplateResponse('forms', 'expired');
		}

		$renderAs = $this->userSession->isLoggedIn() ? 'user' : 'public';

		Util::addScript($this->appName, 'submit');
		$this->initialStateService->provideInitialState($this->appName, 'form', $this->formsService->getPublicForm($form->getId()));
		$this->initialStateService->provideInitialState($this->appName, 'maxStringLengths', $this->maxStringLengths);
		return new TemplateResponse($this->appName, 'main', [], $renderAs);
	}
}
