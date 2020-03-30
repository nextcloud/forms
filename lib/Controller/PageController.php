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
		AnswerMapper $AnswerMapper
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
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		Util::addScript($this->appName, 'forms');
		Util::addStyle($this->appName, 'icons');
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
		Util::addStyle($this->appName, 'icons');
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
		Util::addStyle($this->appName, 'icons');
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
		Util::addStyle($this->appName, 'icons');
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
		Util::addStyle($this->appName, 'icons');
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

		if ($form->getExpirationDate() === null) {
			$expired = false;
		} else {
			$expired = time() > strtotime($form->getExpirationDate());
		}

		if ($expired) {
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
	 * @param int $formId
	 * @return TemplateResponse|RedirectResponse
	 */
	public function deleteForm($formId) {
		$formToDelete = $this->formMapper->find($formId);
		if ($this->userId !== $formToDelete->getOwnerId() && !$this->groupManager->isAdmin($this->userId)) {
			return new TemplateResponse('forms', 'no.delete.tmpl');
		}
		$form = new Form();
		$form->setId($formId);
		$this->submissionMapper->deleteByForm($formId);
		$this->formMapper->delete($form);
		$url = $this->urlGenerator->linkToRoute('forms.page.index');
		return new RedirectResponse($url);
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

		$form = $this->formMapper->find($id);
		$anonID = "anon-user-".  hash('md5', (time() + rand()));

		//Insert Submission
		$submission = new Submission();
		$submission->setFormId($id);
		if($form->getIsAnonymous()){
			$submission->setUserId($anonID);

		}else{
			$submission->setUserId($userId);
		}
		$submission->setTimestamp(date('Y-m-d H:i:s'));
		$this->submissionMapper->insert($submission);
		$submissionId = $submission->getId();

		//Insert Answers
		foreach($questions as $question) {
			if($question['type'] === "checkbox"){
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

		$hash = $form->getHash();
		$url = $this->urlGenerator->linkToRoute('forms.page.goto_form', ['hash' => $hash]);
		return new RedirectResponse($url);
	}

	/**
	 * @NoAdminRequired
	 * @param string $searchTerm
	 * @param string $groups
	 * @param string $users
	 * @return array
	 */
	public function search($searchTerm, $groups, $users) {
		return array_merge($this->searchForGroups($searchTerm, $groups), $this->searchForUsers($searchTerm, $users));
	}

	/**
	 * @NoAdminRequired
	 * @param string $searchTerm
	 * @param string $groups
	 * @return array
	 */
	public function searchForGroups($searchTerm, $groups) {
		$selectedGroups = json_decode($groups);
		$groups = $this->groupManager->search($searchTerm);
		$gids = [];
		$sgids = [];
		foreach ($selectedGroups as $sg) {
			$sgids[] = str_replace('group_', '', $sg);
		}
		foreach ($groups as $g) {
			$gids[] = $g->getGID();
		}
		$diffGids = array_diff($gids, $sgids);
		$gids = [];
		foreach ($diffGids as $g) {
			$gids[] = ['gid' => $g, 'isGroup' => true];
		}
		return $gids;
	}

	/**
	 * @NoAdminRequired
	 * @param string $searchTerm
	 * @param string $users
	 * @return array
	 */
	public function searchForUsers($searchTerm, $users) {
		$selectedUsers = json_decode($users);
		Util::writeLog('forms', print_r($selectedUsers, true), Util::ERROR);
		$userNames = $this->userMgr->searchDisplayName($searchTerm);
		$users = [];
		$sUsers = [];
		foreach ($selectedUsers as $su) {
			$sUsers[] = str_replace('user_', '', $su);
		}
		foreach ($userNames as $u) {
			$allreadyAdded = false;
			foreach ($sUsers as &$su) {
				if ($su === $u->getUID()) {
					unset($su);
					$allreadyAdded = true;
					break;
				}
			}
			if (!$allreadyAdded) {
				$users[] = ['uid' => $u->getUID(), 'displayName' => $u->getDisplayName(), 'isGroup' => false];
			} else {
				continue;
			}
		}
		return $users;
	}

	/**
	 * @return \OCP\IGroup[]
	 */
	private function getGroups() {
		$groups = $this->groupManager->getUserGroups(\OC::$server->getUserSession()->getUser());
		return array_map(function(IGroup $group) {
			return $group->getGID();
		}, $groups);
	}

	/**
	 * Check if user has access to this form
	 *
	 * @param Form $form
	 * @return bool
	 */
	private function hasUserAccess($form) {
		$access = $form->getAccess();
		$ownerId = $form->getOwnerId();
		if ($access === 'public' || $access === 'hidden') {
			return true;
		}
		if ($this->userId === null) {
			return false;
		}
		if ($access === 'registered') {
			if ($form->getSubmitOnce()) {
				$participants = $this->submissionMapper->findParticipantsByForm($form->getId());
				foreach($participants as $participant) {
					// Don't allow access if user has already taken part
					if ($participant->getUserId() === $this->userId) return false;
				}
			}
			return true;
		}
		if ($ownerId === $this->userId) {
			return true;
		}
		Util::writeLog('forms', $this->userId, Util::ERROR);
		$userGroups = $this->getGroups();
		$arr = explode(';', $access);
		foreach ($arr as $item) {
			if (strpos($item, 'group_') === 0) {
				$grp = substr($item, 6);
				foreach ($userGroups as $userGroup) {
					if ($userGroup === $grp) {
						return true;
					}
				}
			} else {
				if (strpos($item, 'user_') === 0) {
					$usr = substr($item, 5);
					if ($usr === $this->userId) {
						return true;
					}
				}
			}
		}
		return false;
	}
}
