<?php


/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author René Gieling <github@dartcafe.de>
 * @author Natalie Gilbert <ngilb634@umd.edu>
 * @author Inigo Jiron
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Db\DoesNotExistException;

use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

use OCA\Forms\Db\Event;
use OCA\Forms\Db\EventMapper;
use OCA\Forms\Db\Vote;
use OCA\Forms\Db\VoteMapper;
use OCA\Forms\Db\Notification;
use OCA\Forms\Db\NotificationMapper;

use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;



class ApiController extends Controller {

	private $groupManager;
	private $userManager;
	private $eventMapper;
	private $voteMapper;
	private $questionMapper;
	private $answerMapper;

	/**
	 * PageController constructor.
	 * @param string $appName
	 * @param IGroupManager $groupManager
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param string $userId
	 * @param EventMapper $eventMapper
	 * @param VoteMapper $voteMapper
	 * @param QuestionMapper $questionMapper
	 * @param AnswerMapper $answerMapper
	 */
	public function __construct(
		$appName,
		IGroupManager $groupManager,
		IRequest $request,
		IUserManager $userManager,
		$userId,
		EventMapper $eventMapper,
		VoteMapper $voteMapper,
		QuestionMapper $questionMapper,
		AnswerMapper $answerMapper
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->eventMapper = $eventMapper;
		$this->voteMapper = $voteMapper;
		$this->questionMapper = $questionMapper;
		$this->answerMapper = $answerMapper;
	}

	/**
	 * Transforms a string with user and group names to an array
	 * of nextcloud users and groups
	 * @param string $item
	 * @return Array
	 */
	private function convertAccessList($item) {
		$split = array();
		if (strpos($item, 'user_') === 0) {
			$user = $this->userManager->get(substr($item, 5));
			$split = [
				'id' => $user->getUID(),
				'user' => $user->getUID(),
				'type' => 'user',
				'desc' => 'user',
				'icon' => 'icon-user',
				'displayName' => $user->getDisplayName(),
				'avatarURL' => '',
				'lastLogin' => $user->getLastLogin(),
				'cloudId' => $user->getCloudId()
			];
		} elseif (strpos($item, 'group_') === 0) {
			$group = substr($item, 6);
			$group = $this->groupManager->get($group);
			$split = [
				'id' => $group->getGID(),
				'user' => $group->getGID(),
				'type' => 'group',
				'desc' => 'group',
				'icon' => 'icon-group',
				'displayName' => $group->getDisplayName(),
				'avatarURL' => '',
			];
		}

		return($split);
	}

	/**
	 * Check if current user is in the access list
	 * @param Array $accessList
	 * @return Boolean
	 */
	private function checkUserAccess($accessList) {
		foreach ($accessList as $accessItem ) {
			if ($accessItem['type'] === 'user' && $accessItem['id'] === \OC::$server->getUserSession()->getUser()->getUID()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check If current user is member of a group in the access list
	 * @param Array $accessList
	 * @return Boolean
	 */
	private function checkGroupAccess($accessList) {
		foreach ($accessList as $accessItem ) {
			if ($accessItem['type'] === 'group' && $this->groupManager->isInGroup(\OC::$server->getUserSession()->getUser()->getUID(),$accessItem['id'])) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set the access right of the current user for the form
	 * @param Array $event
	 * @param Array $shares
	 * @return String
	 */
	private function grantAccessAs($event, $shares) {
		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			$currentUser = '';
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		}

		$grantAccessAs = 'none';

		if ($event['owner'] === $currentUser) {
			$grantAccessAs = 'owner';
		} elseif ($event['access'] === 'public') {
			$grantAccessAs = 'public';
		} elseif ($event['access'] === 'registered' && \OC::$server->getUserSession()->getUser() instanceof IUser) {
			$grantAccessAs = 'registered';
		} elseif ($event['access'] === 'hidden' && ($event['owner'] === \OC::$server->getUserSession()->getUser())) {
			$grantAccessAs = 'hidden';
		} elseif ($this->checkUserAccess($shares)) {
			$grantAccessAs = 'userInvitation';
		} elseif ($this->checkGroupAccess($shares)) {
			$grantAccessAs = 'groupInvitation';
		} elseif ($this->groupManager->isAdmin($currentUser)) {
			$grantAccessAs = 'admin';
		}

		return $grantAccessAs;
	}

	/**
	 * Read all votes of a form based on the form id
	 * @NoAdminRequired
	 * @param Integer $formId
	 * @return Array
	 */
	public function getVotes($formId) {
		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			$currentUser = '';
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		}

		$event = $this->getEvent($formId);

		$accessList = $this->convertAccessList($event['access']);

		if ($event['owner'] == $currentUser) {
		$votesList = array();
		$votes = $this->voteMapper->findByForm($formId);

		foreach ($votes as $voteElement) {
			$votesList[] = $voteElement->read();
		}

		return $votesList;
	}
	return NULL;
}


	/**
	 * Read an entire form based on form id
	 * @NoAdminRequired
	 * @param Integer $formId
	 * @return Array
	 */
	public function getEvent($formId) {

		$data = array();
		try {
			$data = $this->eventMapper->find($formId)->read();
		} catch (DoesNotExistException $e) {
			// return silently
		} finally {
			return $data;
		}

	}

	/**
	 * Read all shares (users and groups with access) of a form based on the form id
	 * @NoAdminRequired
	 * @param Integer $formId
	 * @return Array
	 */
	public function getShares($formId) {

		$accessList = array();

		try {
			$form = $this->eventMapper->find($formId);
			if (!strpos('|public|hidden|registered', $form->getAccess())) {
				$accessList = explode(';', $form->getAccess());
				$accessList = array_filter($accessList);
				$accessList = array_map(array($this, 'convertAccessList'), $accessList);
			}
		} catch (DoesNotExistException $e) {
			// return silently
		} finally {
			return $accessList;
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
	 * Read an entire form based on the form id or hash
	 * @NoAdminRequired
	 * @param String $formIdOrHash form id or hash
	 * @return Array
	 */
	public function getForm($formIdOrHash) {

		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			$currentUser = '';
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		}

		$data = array();

		try {

			if (is_numeric($formIdOrHash)) {
				$formId = $this->eventMapper->find(intval($formIdOrHash))->id;
				$result = 'foundById';
			} else {
				$formId = $this->eventMapper->findByHash($formIdOrHash)->id;
				$result = 'foundByHash';
			}

			$event = $this->getEvent($formId);
			$shares = $this->getShares($event['id']);

			if ($event['owner'] !== $currentUser && !$this->groupManager->isAdmin($currentUser)) {
				$mode = 'create';
			} else {
				$mode = 'edit';
			}

			$data = [
				'id' => $event['id'],
				'result' => $result,
				'grantedAs' => $this->grantAccessAs($event, $shares),
				'mode' => $mode,
				'event' => $event,
				'votes' => $this->getVotes($event['id']),
				'shares' => $shares,
				'options' => [
					'formQuizQuestions' => $this->getQuestions($event['id'])
				]
			];
		} catch (DoesNotExistException $e) {
				$data['form'] = ['result' => 'notFound'];
		} finally {
			return $data;
		}
	}

	/**
	 * Get all forms
	 * @NoAdminRequired
	 * @return DataResponse
	 */

	public function getForms() {
		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}

		try {
			$events = $this->eventMapper->findAll();
		} catch (DoesNotExistException $e) {
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}

		$eventsList = array();

		foreach ($events as $eventElement) {
			$event = $this->getForm($eventElement->id);
			//if ($event['grantedAs'] !== 'none') {
				$eventsList[] = $event;
			//}
		}

		return new DataResponse($eventsList, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @param int $formId
	 * @return DataResponse
	 */
	public function removeForm(int $id) {
		try {
			$formToDelete = $this->eventMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new Http\JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		if ($this->userId !== $formToDelete->getOwner() && !$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}
		$this->voteMapper->deleteByForm($id);
		// $this->notificationMapper->deleteByForm($id);
		$this->questionMapper->deleteByForm($id);
		$this->answerMapper->deleteByForm($id);
		$this->eventMapper->delete($formToDelete);
		return new DataResponse(array(
			'id' => $id,
			'action' => 'deleted'
		), Http::STATUS_OK);
	}


	/**
	 * Write form (create/update)
	 * @NoAdminRequired
	 * @param Array $event
	 * @param Array $options
	 * @param Array  $shares
	 * @param String $mode
	 * @return DataResponse
	 */
	public function writeForm($event, $options, $shares, $mode) {
		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
			$AdminAccess = $this->groupManager->isAdmin($currentUser);
		}

		$newEvent = new Event();

		// Set the configuration options entered by the user
		$newEvent->setTitle($event['title']);
		$newEvent->setDescription($event['description']);

		$newEvent->setIsAnonymous($event['isAnonymous']);
		$newEvent->setUnique($event['unique']);

		if ($event['access'] === 'select') {
			$shareAccess = '';
			foreach ($shares as $shareElement) {
				if ($shareElement['type'] === 'user') {
					$shareAccess = $shareAccess . 'user_' . $shareElement['id'] . ';';
				} elseif ($shareElement['type'] === 'group') {
					$shareAccess = $shareAccess . 'group_' . $shareElement['id'] . ';';
				}
			}
			$newEvent->setAccess(rtrim($shareAccess, ';'));
		} else {
			$newEvent->setAccess($event['access']);
		}

		if ($event['expiration']) {
			$newEvent->setExpire(date('Y-m-d H:i:s', strtotime($event['expirationDate'])));
		} else {
			$newEvent->setExpire(null);
		}

		if ($mode === 'edit') {
			// Edit existing form
			$oldForm = $this->eventMapper->findByHash($event['hash']);

			// Check if current user is allowed to edit existing form
			if ($oldForm->getOwner() !== $currentUser && !$AdminAccess) {
				// If current user is not owner of existing form deny access
				return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
			}

			// else take owner, hash and id of existing form
			$newEvent->setOwner($oldForm->getOwner());
			$newEvent->setHash($oldForm->getHash());
			$newEvent->setId($oldForm->getId());
			$this->eventMapper->update($newEvent);
			$this->questionMapper->deleteByForm($newEvent->getId());

		} elseif ($mode === 'create') {
			// Create new form
			// Define current user as owner, set new creation date and create a new hash
			$newEvent->setOwner($currentUser);
			$newEvent->setCreated(date('Y-m-d H:i:s'));
			$newEvent->setHash(\OC::$server->getSecureRandom()->generate(
				16,
				ISecureRandom::CHAR_DIGITS .
				ISecureRandom::CHAR_LOWER .
				ISecureRandom::CHAR_UPPER
			));
			$newEvent = $this->eventMapper->insert($newEvent);
		}

		// Update options
		$qId = 1;
		foreach($options['formQuizQuestions'] as $questionElement){
			$newQuestion = new Question();

			$newQuestion->setFormId($newEvent->getId());
			$newQuestion->setFormQuestionType($questionElement['type']);
			$newQuestion->setFormQuestionText(trim(htmlspecialchars($questionElement['text'])));

			$newQuestion = $this->questionMapper->insert($newQuestion);

			foreach($questionElement['answers'] as $answer){
				$newAnswer = new Answer();

				$newAnswer->setFormId($newEvent->getId());
				$newAnswer->setQuestionId($newQuestion->getId());
				$newAnswer->setText($answer['text']);

				$newAnswer = $this->answerMapper->insert($newAnswer);
			}
		}

		return new DataResponse(array(
			'id' => $newEvent->getId(),
			'hash' => $newEvent->getHash()
		), Http::STATUS_OK);

	}
}
