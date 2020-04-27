<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @author René Gieling <github@dartcafe.de>
 * @author Inigo Jiron <ijiron@terpmail.umd.edu>
 * @author Natalie Gilbert
 * @author Affan Hussain
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Forms\Controller;

use OCA\Forms\AppInfo\Application;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;

use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\User; //To do: replace according to API
use OCP\Util;

class PageController extends Controller {

	private $userId;
	private $formMapper;
	private $submissionMapper;
	private $answerMapper;

	private $questionMapper;
	private $optionMapper;

	private $urlGenerator;
	private $userMgr;
	private $groupManager;

	/** @var ILogger */
	private $logger;

	public function __construct(
		IRequest $request,
		IUserManager $userMgr,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		$userId,
		FormMapper $formMapper,

		QuestionMapper $questionMapper,
		OptionMapper $optionMapper,
		SubmissionMapper $SubmissionMapper,
		AnswerMapper $AnswerMapper,
		ILogger $logger
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userMgr = $userMgr;
		$this->groupManager = $groupManager;
		$this->urlGenerator = $urlGenerator;
		$this->userId = $userId;
		$this->formMapper = $formMapper;

		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->submissionMapper = $SubmissionMapper;
		$this->answerMapper = $AnswerMapper;
		$this->logger = $logger;
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
		return new TemplateResponse($this->appName, 'main');
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function cloneForm(): TemplateResponse {
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'forms');
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
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (DoesNotExistException $e) {
			return new TemplateResponse('forms', 'no.acc.tmpl', []);
		}

		// If form expired, return Expired-Template
		if ( ($form->getExpires() !== 0) && (time() > $form->getExpires()) ) {
			return new TemplateResponse('forms', 'expired.tmpl');
		}

		if ($this->hasUserAccess($form)) {
			$renderAs = $this->userId !== null ? 'user' : 'public';
			$res = new TemplateResponse('forms', 'submit.tmpl', [
					'form' => $form,
					'questions' => $this->getQuestions($form->getId()),
			], $renderAs);
			$csp = new ContentSecurityPolicy();
			$csp->allowEvalScript(true);
			$res->setContentSecurityPolicy($csp);
			return $res;
		}

		User::checkLoggedIn();
		return new TemplateResponse('forms', 'no.acc.tmpl', []);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getQuestions(int $formId): array {
		$questionList = [];
		try{
			$questionEntities = $this->questionMapper->findByForm($formId);
			foreach ($questionEntities as $questionEntity) {
				$question = $questionEntity->read();
				$question['options'] = $this->getOptions($question['id']);
				$questionList[] =  $question;
			}
		} catch (DoesNotExistException $e) {
			//handle silently
		}

		return $questionList;
	}

	/**
	 * @NoAdminRequired
	 */
	public function getOptions(int $questionId): array {
		$optionList = [];

		try{
			$optionEntities = $this->optionMapper->findByQuestion($questionId);
			foreach ($optionEntities as $optionEntity) {
				$optionList[] = $optionEntity->read();
			}

		} catch (DoesNotExistException $e) {
			//handle silently
		}

		return $optionList;
	}


	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @param int $formId
	 * @param string $userId
	 * @param array $answers
	 * @param array $questions
	 * @return RedirectResponse
	 */
	public function insertSubmission($id, $userId, $answers, $questions) {

		$form = $this->formMapper->findById($id);
		$anonID = "anon-user-".  hash('md5', (time() + rand()));

		//Insert Submission
		$submission = new Submission();
		$submission->setFormId($id);
		if($form->getIsAnonymous()){
			$submission->setUserId($anonID);

		}else{
			$submission->setUserId($userId);
		}
		$submission->setTimestamp(time());
		$this->submissionMapper->insert($submission);
		$submissionId = $submission->getId();

		//Insert Answers
		foreach($questions as $question) {
			// If question is answered, the questionText exists as key in $answers. Does not exist, when a (non-mandatory) question was not answered.
			if (array_key_exists($question['text'], $answers)) {
				if($question['type'] === "multiple"){
					foreach(($answers[$question['text']]) as $ansText) {
						$answer = new Answer();
						$answer->setSubmissionId($submissionId);
						$answer->setQuestionId($question['id']);
						$answer->setText($ansText);
						$this->answerMapper->insert($answer);
					}
				} else {
					$answer = new Answer();
					$answer->setSubmissionId($submissionId);
					$answer->setQuestionId($question['id']);
					$answer->setText($answers[$question['text']]);
					$this->answerMapper->insert($answer);
				}
			}
		}

		$hash = $form->getHash();
		$url = $this->urlGenerator->linkToRoute('forms.page.goto_form', ['hash' => $hash]);
		return new RedirectResponse($url);
	}

	/**
	 * @NoAdminRequired
	 * Check if user has access to this form
	 */
	private function hasUserAccess(Form $form): bool {
		$access = $form->getAccess();
		$ownerId = $form->getOwnerId();
		
		if ($access['type'] === 'public') {
			return true;
		}
		
		// Refuse access, if not public and no user logged in.
		if ($this->userId === null) {
			return false;
		}

		// Always grant access to owner.
		if ($ownerId === $this->userId) {
			return true;
		}

		// Refuse access, if SubmitOnce is set and user already has taken part.
		if ($form->getSubmitOnce()) {
			$participants = $this->submissionMapper->findParticipantsByForm($form->getId());
			foreach($participants as $participant) {
				if ($participant === $this->userId) {
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
		if (in_array($this->userId, $access['users'])) {
			return true;
		}

		// Check if access granted by group.
		foreach ($access['groups'] as $group) {
			if( $this->groupManager->isInGroup($this->userId, $group) ) {
				return true;
			}
		}

		// None of the possible access-options matched.
		return false;
	}
}
