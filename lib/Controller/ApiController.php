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
use OCP\AppFramework\Db\IMapperException;

use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;

use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;

use OCP\Util;

class ApiController extends Controller {

	private $groupManager;
	private $userManager;
	private $formMapper;
	private $submissionMapper;
	private $answerMapper;
	private $questionMapper;
	private $optionMapper;

	/** @var ILogger */
	private $logger;

	/** @var string */
	private $userId;

	/**
	 * PageController constructor.
	 * @param string $appName
	 * @param IGroupManager $groupManager
	 * @param IRequest $request
	 * @param IUserManager $userManager
	 * @param string $userId
	 * @param FormMapper $formMapper
	 * @param SubmissionMapper $submissionMapper
	 * @param AnswerMapper $answerMapper
	 * @param QuestionMapper $questionMapper
	 * @param OptionMapper $optionMapper
	 */
	public function __construct(
		$appName,
		IGroupManager $groupManager,
		IRequest $request,
		IUserManager $userManager,
		$userId,
		FormMapper $formMapper,
		SubmissionMapper $submissionMapper,
		AnswerMapper $answerMapper,
		QuestionMapper $questionMapper,
		OptionMapper $optionMapper,
		ILogger $logger
	) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->formMapper = $formMapper;
		$this->submissionMapper = $submissionMapper;
		$this->answerMapper = $answerMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->logger = $logger;
	}

	/**
	 * Transforms a string with user and group names to an array
	 * of nextcloud users and groups
	 * @param string $item
	 * @return Array
	 */
	private function convertAccessList($item) : array {
		$split = [];
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
	 * @param Array $form
	 * @param Array $shares
	 * @return String
	 */
	private function grantAccessAs($form, $shares) {
		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			$currentUser = '';
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		}

		$grantAccessAs = 'none';

		if ($form['ownerId'] === $currentUser) {
			$grantAccessAs = 'owner';
		} elseif ($form['access'] === 'public') {
			$grantAccessAs = 'public';
		} elseif ($form['access'] === 'registered' && \OC::$server->getUserSession()->getUser() instanceof IUser) {
			$grantAccessAs = 'registered';
		} elseif ($form['access'] === 'hidden' && ($form['ownerId'] === \OC::$server->getUserSession()->getUser())) {
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
	 * Read an entire form based on form id
	 * @NoAdminRequired
	 * @param Integer $formId
	 * @return Array
	 */
	public function getForm($formId) {

		$data = array();
		try {
			$data = $this->formMapper->find($formId)->read();
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
			$form = $this->formMapper->find($formId);
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

	public function getQuestions($formId) : array {
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
		}finally{
			return $questionList;
		}
	}

	public function getOptions($questionId) : array {
		$optionList = [];
		try{
			$optionEntities = $this->optionMapper->findByQuestion($questionId);
			foreach ($optionEntities as $optionEntity) {
				$optionList[] = $optionEntity->read();
			}

		} catch (DoesNotExistException $e) {
			//handle silently
		}finally{
			return $optionList;
		}
	}

	/**
	 * Read an entire form based on the form id or hash
	 * @NoAdminRequired
	 * @param String $formIdOrHash form id or hash
	 * @return Array
	 */
	public function getFullForm($formIdOrHash) {

		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			$currentUser = '';
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		}

		$data = array();

		try {

			if (is_numeric($formIdOrHash)) {
				$formId = $this->formMapper->find(intval($formIdOrHash))->id;
				$result = 'foundById';
			} else {
				$formId = $this->formMapper->findByHash($formIdOrHash)->id;
				$result = 'foundByHash';
			}

			$form = $this->getForm($formId);
			$shares = $this->getShares($form['id']);

			if ($form['ownerId'] !== $currentUser && !$this->groupManager->isAdmin($currentUser)) {
				$mode = 'create';
			} else {
				$mode = 'edit';
			}

			$data = [
				'id' => $form['id'],
				'result' => $result,
				'grantedAs' => $this->grantAccessAs($form, $shares),
				'mode' => $mode,
				'form' => $form,
				'shares' => $shares,
				'questions' => $this->getQuestions($form['id']),
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
			$forms = $this->formMapper->findAll();
		} catch (DoesNotExistException $e) {
			return new DataResponse($e, Http::STATUS_NOT_FOUND);
		}

		$formsList = array();
		foreach ($forms as $formElement) {
			$form = $this->getFullForm($formElement->id);
			//if ($form['grantedAs'] !== 'none') {
				$formsList[] = $form;
			//}
		}

		return new DataResponse($formsList, Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 * @param int $formId
	 * @return DataResponse
	 * TODO: use hash instead of id ?
	 */
	public function deleteForm(int $id) {
		try {
			$formToDelete = $this->formMapper->find($id);
		} catch (DoesNotExistException $e) {
			return new Http\JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		if ($this->userId !== $formToDelete->getOwnerId() && !$this->groupManager->isAdmin($this->userId)) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		}
		$this->submissionMapper->deleteByForm($id);
		$this->questionMapper->deleteByForm($id);
		$this->formMapper->delete($formToDelete);
		return new DataResponse(array(
			'id' => $id,
			'action' => 'deleted'
		), Http::STATUS_OK);
	}


	/**
	 * Write form (create/update)
	 * @NoAdminRequired
	 * @param Array $form
	 * @param Array $options
	 * @param Array  $shares
	 * @param String $mode
	 * @return DataResponse
	 */
	public function writeForm($form, $questions, $shares, $mode) {
		if (!\OC::$server->getUserSession()->getUser() instanceof IUser) {
			return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
		} else {
			$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
			$adminAccess = $this->groupManager->isAdmin($currentUser);
		}

		$newForm = new Form();

		// Set the configuration options entered by the user
		$newForm->setTitle($form['title']);
		$newForm->setDescription($form['description']);

		$newForm->setIsAnonymous($form['isAnonymous']);
		$newForm->setSubmitOnce($form['submitOnce']);

		if ($form['access'] === 'select') {
			$shareAccess = '';
			foreach ($shares as $shareElement) {
				if ($shareElement['type'] === 'user') {
					$shareAccess = $shareAccess . 'user_' . $shareElement['id'] . ';';
				} elseif ($shareElement['type'] === 'group') {
					$shareAccess = $shareAccess . 'group_' . $shareElement['id'] . ';';
				}
			}
			$newForm->setAccess(rtrim($shareAccess, ';'));
		} else {
			$newForm->setAccess($form['access']);
		}

		if ($form['expires']) {
			$newForm->setExpirationDate(date('Y-m-d H:i:s', strtotime($form['expirationDate'])));
		} else {
			$newForm->setExpirationDate(null);
		}

		if ($mode === 'edit') {
			// Edit existing form
			$oldForm = $this->formMapper->findByHash($form['hash']);

			// Check if current user is allowed to edit existing form
			if ($oldForm->getOwnerId() !== $currentUser && !$adminAccess) {
				// If current user is not owner of existing form deny access
				return new DataResponse(null, Http::STATUS_UNAUTHORIZED);
			}

			// else take owner, hash and id of existing form
			$newForm->setOwnerId($oldForm->getOwnerId());
			$newForm->setHash($oldForm->getHash());
			$newForm->setId($oldForm->getId());
			$this->formMapper->update($newForm);

		} elseif ($mode === 'create') {
			// Create new form
			// Define current user as owner, set new creation date and create a new hash
			$newForm->setOwnerId($currentUser);
			$newForm->setCreated(date('Y-m-d H:i:s'));
			$newForm->setHash(\OC::$server->getSecureRandom()->generate(
				16,
				ISecureRandom::CHAR_DIGITS .
				ISecureRandom::CHAR_LOWER .
				ISecureRandom::CHAR_UPPER
			));
			$newForm = $this->formMapper->insert($newForm);
		}

		return new DataResponse(array(
			'id' => $newForm->getId(),
			'hash' => $newForm->getHash()
		), Http::STATUS_OK);

	}

	/**
	 * @NoAdminRequired
	 */
	public function newForm(): Http\JSONResponse {
		$form = new Form();

		$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		$form->setOwnerId($currentUser);
		$form->setCreated(date('Y-m-d H:i:s'));
		$form->setHash(\OC::$server->getSecureRandom()->generate(
			16,
			ISecureRandom::CHAR_HUMAN_READABLE
		));
		$form->setTitle('New form');
		$form->setDescription('');
		$form->setAccess('public');

		$this->formMapper->insert($form);

		return new Http\JSONResponse($this->getFullForm($form->getHash()));
	}

	/**
	 * @NoAdminRequired
	 */
	public function newQuestion(int $formId, string $type, string $text): Http\JSONResponse {
		$this->logger->debug('Adding new question: formId: {formId}, type: {type}, text: {text}', [
			'formId' => $formId,
			'type' => $type,
			'text' => $text,
		]);

		try {
			$form = $this->formMapper->find($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$question = new Question();

		$question->setFormId($formId);
		$question->setType($type);
		$question->setText($text);

		$question = $this->questionMapper->insert($question);

		return new Http\JSONResponse($question->getId());
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteQuestion(int $id): Http\JSONResponse {
		$this->logger->debug('Delete question: {id}', [
			'id' => $id,
		]);

		try {
			$question = $this->questionMapper->findById($id);
			$form = $this->formMapper->find($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->optionMapper->deleteByQuestion($id);
		$this->questionMapper->delete($question);

		return new Http\JSONResponse($id);
	}

	/**
	 * @NoAdminRequired
	 */
	public function newOption(int $formId, int $questionId, string $text): Http\JSONResponse {
		$this->logger->debug('Adding new option: formId: {formId}, questionId: {questionId}, text: {text}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'text' => $text,
		]);

		try {
			$form = $this->formMapper->find($formId);
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question so option can\'t be added');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if ($question->getFormId() !== $formId) {
			$this->logger->debug('This question is not part of the current form');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$option = new Option();

		$option->setQuestionId($questionId);
		$option->setText($text);

		$option = $this->optionMapper->insert($option);

		return new Http\JSONResponse($option->getId());
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteOption(int $id): Http\JSONResponse {
		$this->logger->debug('Deleting option: {id}', [
			'id' => $id
		]);

		try {
			$option = $this->optionMapper->findById($id);
			$question = $this->questionMapper->findById($option->getQuestionId());
			$form = $this->formMapper->find($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or option');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->optionMapper->delete($option);

		//TODO useful response
		return new Http\JSONResponse($id);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getSubmissions(string $hash): Http\JSONResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$result = [];
		$submissionList = $this->submissionMapper->findByForm($form->getId());
		foreach ($submissionList as $submissionEntity) {
			$answerList = $this->answerMapper->findBySubmission($submissionEntity->id);
			foreach ($answerList as $answerEntity) {
				$answer = $answerEntity->read();
				//Temporary Adapt Data to be usable by old Results-View
				$answer['userId'] = $submissionEntity->getUserId();

				$question = $this->questionMapper->findById($answer['questionId']);
				$answer['questionText'] = $question->getText();
				$answer['questionType'] = $question->getType();

				$result[] = $answer;
			}
		}

		return new Http\JSONResponse($result);
	}
}
