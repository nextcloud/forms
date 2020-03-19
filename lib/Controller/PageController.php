<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @author René Gieling <github@dartcafe.de>
 * @author Inigo Jiron <ijiron@terpmail.umd.edu>
 * @author Natalie Gilbert
 * @author Affan Hussain
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
use OCA\Forms\Db\Event;
use OCA\Forms\Db\EventMapper;
use OCA\Forms\Db\Vote;
use OCA\Forms\Db\VoteMapper;

use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\QuestionMapper;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\User; //To do: replace according to API
use OCP\Util;

class PageController extends Controller {

	private $userId;
	private $eventMapper;
	private $notificationMapper;
	private $voteMapper;

	private $questionMapper;
	private $answerMapper;

	private $urlGenerator;
	private $userMgr;
	private $groupManager;

	public function __construct(
		IRequest $request,
		IUserManager $userMgr,
		IGroupManager $groupManager,
		IURLGenerator $urlGenerator,
		$userId,
		EventMapper $eventMapper,
		QuestionMapper $questionMapper,
		AnswerMapper $answerMapper,
		VoteMapper $VoteMapper
	) {
		parent::__construct(Application::APP_ID, $request);
		$this->userMgr = $userMgr;
		$this->groupManager = $groupManager;
		$this->urlGenerator = $urlGenerator;
		$this->userId = $userId;
		$this->eventMapper = $eventMapper;

		$this->questionMapper = $questionMapper;
		$this->answerMapper = $answerMapper;
		$this->voteMapper = $VoteMapper;
	}

	/**
	* @NoAdminRequired
	* @NoCSRFRequired
	*/
	public function index() {
		return new TemplateResponse('forms', 'forms.tmpl',
		['urlGenerator' => $this->urlGenerator]);
	}

	/**
	* @NoAdminRequired
	*/
	public function createForm() {
		return new TemplateResponse('forms', 'forms.tmpl',
		['urlGenerator' => $this->urlGenerator]);
	}

	/**
	* @NoAdminRequired
	*/
	public function cloneForm() {
		return new TemplateResponse('forms', 'forms.tmpl',
		['urlGenerator' => $this->urlGenerator]);
	}

	/**
	 * @NoAdminRequired
	 * @param string $hash
	 * @return TemplateResponse
	 */
	public function editForm($hash) {
		return new TemplateResponse('forms', 'forms.tmpl', [
			'urlGenerator' => $this->urlGenerator,
 			'hash' => $hash
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @param string $hash
	 * @return TemplateResponse
	 */
	public function gotoForm($hash) {
		try {
			$form = $this->eventMapper->findByHash($hash);
		} catch (DoesNotExistException $e) {
			return new TemplateResponse('forms', 'no.acc.tmpl', []);
		}

		if ($form->getExpire() === null) {
			$expired = false;
		} else {
			$expired = time() > strtotime($form->getExpire());
		}

		if ($expired) {
			return new TemplateResponse('forms', 'expired.tmpl');
		}

		if ($this->hasUserAccess($form)) {
			$renderAs = $this->userId !== null ? 'user' : 'public';
			$res = new TemplateResponse('forms', 'vote.tmpl', [
					'form' => $form,
					'questions' => $this->getQuestions($form->getId()),
			], $renderAs);
			$csp = new ContentSecurityPolicy();
			$csp->allowEvalScript(true);
			$res->setContentSecurityPolicy($csp);
			return $res;
		} else {
			User::checkLoggedIn();
			return new TemplateResponse('forms', 'no.acc.tmpl', []);
		}
	}

	public function getQuestions($formId) {
		$questionList = array();
		try{
			$questions = $this->questionMapper->findByForm($formId);
			foreach ($questions as $questionElement) {
				$temp = $questionElement->read();
				$temp['answers'] = $this->getAnswers($formId, $temp['id']);
				$questionList[] =  $temp;
			}

		} catch (DoesNotExistException $e) {
			//handle silently
		}finally{
			return $questionList;
		}
	}

	public function getAnswers($formId, $questionId) {
		$answerList = array();
		try{
			$answers = $this->answerMapper->findByForm($formId, $questionId);
			foreach ($answers as $answerElement) {
				$answerList[] = $answerElement->read();
			}

		} catch (DoesNotExistException $e) {
			//handle silently
		}finally{
			return $answerList;
		}
	}

	/**
	 * @NoAdminRequired
	 * @param int $formId
	 * @return TemplateResponse|RedirectResponse
	 */
	public function deleteForm($formId) {
		$formToDelete = $this->eventMapper->find($formId);
		if ($this->userId !== $formToDelete->getOwner() && !$this->groupManager->isAdmin($this->userId)) {
			return new TemplateResponse('forms', 'no.delete.tmpl');
		}
		$form = new Event();
		$form->setId($formId);
		$this->voteMapper->deleteByForm($formId);
		$this->eventMapper->delete($form);
		$url = $this->urlGenerator->linkToRoute('forms.page.index');
		return new RedirectResponse($url);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * @param int $formId
	 * @param string $userId
	 * @param string $answers
	 * @param string $options question id
	 * @param bool $receiveNotifications
	 * @param bool $changed
	 * @return RedirectResponse
	 */
	public function insertVote($id, $userId, $answers, $questions) {

		$form = $this->eventMapper->find($id);
		$count_answers = count($answers);
		$count = 1;
		$anonID = "anon-user-".  hash('md5', (time() + rand()));

		for ($i = 0; $i < $count_answers; $i++) {
			if($questions[$i]['type'] == "checkbox"){
				foreach (($answers[$questions[$i]['text']]) as $value) {
					$vote = new Vote();
					$vote->setFormId($id);
					if($form->getIsAnonymous()){
						$vote->setUserId($anonID);

					}else{
						$vote->setUserId($userId);
					}
					$vote->setVoteOptionText(htmlspecialchars($questions[$i]['text']));
					$vote->setVoteAnswer($value);
					$vote->setVoteOptionId($count);
					$vote->setVoteOptionType($questions[$i]['type']);
					$this->voteMapper->insert($vote);
				}
				$count++;
			} else {
				$vote = new Vote();
				$vote->setFormId($id);
				if($form->getIsAnonymous()){
						$vote->setUserId($anonID);
				}else{
						$vote->setUserId($userId);
				}
				$vote->setVoteOptionText(htmlspecialchars($questions[$i]['text']));
				$vote->setVoteAnswer($answers[$questions[$i]['text']]);
				$vote->setVoteOptionId($count++);
				$vote->setVoteOptionType($questions[$i]['type']);
				$this->voteMapper->insert($vote);
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
		$gids = array();
		$sgids = array();
		foreach ($selectedGroups as $sg) {
			$sgids[] = str_replace('group_', '', $sg);
		}
		foreach ($groups as $g) {
			$gids[] = $g->getGID();
		}
		$diffGids = array_diff($gids, $sgids);
		$gids = array();
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
		$users = array();
		$sUsers = array();
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
				$users[] = array('uid' => $u->getUID(), 'displayName' => $u->getDisplayName(), 'isGroup' => false);
			} else {
				continue;
			}
		}
		return $users;
	}

	/**
	 * @NoAdminRequired
	 * @param string $username
	 * @return string
	 */
	public function getDisplayName($username) {
		return $this->userMgr->get($username)->getDisplayName();
	}

	/**
	 * @return \OCP\IGroup[]
	 */
	private function getGroups() {
		if (class_exists('\OC_Group')) {
			// Nextcloud <= 11, ownCloud
			return \OC_Group::getUserGroups($this->userId);
		}
		// Nextcloud >= 12
		$groups = $this->groupManager->getUserGroups(\OC::$server->getUserSession()->getUser());
		return array_map(function($group) {
			return $group->getGID();
		}, $groups);
	}

	/**
	 * Check if user has access to this form
	 *
	 * @param Event $form
	 * @return bool
	 */
	private function hasUserAccess($form) {
		$access = $form->getAccess();
		$owner = $form->getOwner();
		if ($access === 'public' || $access === 'hidden') {
			return true;
		}
		if ($this->userId === null) {
			return false;
		}
		if ($access === 'registered') {
			if ($form->getUnique()) {
				$participants = $this->voteMapper->findParticipantsByForm($form->getId());
				foreach($participants as $participant) {
					// Don't allow access if user has already taken part
					if ($participant->getUserId() === $this->userId) return false;
				}
			}
			return true;
		}
		if ($owner === $this->userId) {
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
	/**
	 * Check if user is owner of this form
	 *
	 * @param Event $form
	 * @return bool
	 */

	private function userIsOwner($form) {
		$owner = $form->getOwner();

		if ($owner === $this->userId) {
			return true;
		}
		Util::writeLog('forms', $this->userId, Util::ERROR);
		return false;
	}

	/**
	 * @NoAdminRequired
	 * @param int $id
	 * @return TemplateResponse
	 */
	public function getResult(int $id): TemplateResponse {
		return new TemplateResponse('forms', 'forms.tmpl');
	}
}
