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

use OCA\Forms\AppInfo\Application;
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

	public function __construct(
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
		parent::__construct(Application::APP_ID, $request);
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
				'mode' => $mode,
				'form' => $form,
				'questions' => $this->getQuestions($form['id']),
			];
		} catch (DoesNotExistException $e) {
				$data['form'] = ['result' => 'notFound'];
		} finally {
			return $data;
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function getForms() {
		$forms = $this->formMapper->findAllByOwnerId($this->userId);

		$result = [];
		foreach ($forms as $form) {
			$result[] = [
				'id' => $form->getId(),
				'form' => $form->read(),
				'mode' => 'edit',
				'questions' => $this->getQuestions($form->getId())
			];
		}

		return new DataResponse($result);
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

		// Only write Users/Groups-Arrays if necessary.
		if($form['access']['type'] !== 'selected') {
			unset($form['access']['users']);
			unset($form['access']['groups']);
		}
		$newForm->setAccess($form['access']);

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
		$form->setAccess([
			'type' => 'public'
		]);

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

		// Retrieve all active questions sorted by Order. Takes the order of the last array-element and adds one.
		$questions = $this->questionMapper->findByForm($formId);
		$lastQuestion = array_pop($questions);
		if ($lastQuestion) {
			$questionOrder = $lastQuestion->getOrder() + 1;
		} else {
			$questionOrder = 1;
		}

		$question = new Question();

		$question->setFormId($formId);
		$question->setOrder($questionOrder);
		$question->setType($type);
		$question->setText($text);

		$question = $this->questionMapper->insert($question);

		$response = [
			'id' => $question->getId(),
			'order' => $question->getOrder()
		];

		return new Http\JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 * Updates the Order of all Questions of a Form.
	 * @param int $formId Id of the form to reorder
	 * @param int $newOrder Array of Question-Ids in new order.
	 */
	public function reorderQuestions(int $formId, array $newOrder): Http\JSONResponse {
		$this->logger->debug('Reordering Questions on Form {formId} as Question-Ids {newOrder}', [
			'formId' => $formId,
			'newOrder' => $newOrder
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

		// Check if array contains duplicates
		if ( array_unique($newOrder) !== $newOrder ) {
			$this->logger->debug('The given Array contains duplicates.');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		// Check if all questions are given in Array.
		$questions = $this->questionMapper->findByForm($formId);
		if ( sizeof($questions) !== sizeof($newOrder) ) {
			$this->logger->debug('The length of the given array does not match the number of stored questions');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$questions = []; // Clear Array of Entities
		$response = []; // Array of ['questionId' => ['order' => newOrder]]

		// Store array of Question-Entities and check the Questions FormId & old Order.
		foreach($newOrder as $arrayKey => $questionId) {

			try {
				$questions[$arrayKey] = $this->questionMapper->findById($questionId);
			} catch (IMapperException $e) {
				$this->logger->debug('Could not find question. Id:{id}', [
					'id' => $questionId
				]);
				return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			// Abort if a question is not part of the Form.
			if ($questions[$arrayKey]->getFormId() !== $formId) {
				$this->logger->debug('This Question is not part of the given Form: questionId: {questionId}', [
					'questionId' => $questionId
				]);
				return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			// Abort if a question is already marked as deleted (order==0)
			$oldOrder = $questions[$arrayKey]->getOrder();
			if ( $oldOrder === 0) {
				$this->logger->debug('This Question has already been marked as deleted: Id: {id}', [
					'id' => $questions[$arrayKey]->getId()
				]);
				return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
			}

			// Only set order, if it changed.
			if ($oldOrder !== $arrayKey + 1) {
				// Set Order. ArrayKey counts from zero, order counts from 1.
				$questions[$arrayKey]->setOrder($arrayKey + 1);
			}
		}

		// Write to Database
		foreach($questions as $question) {
			$this->questionMapper->update($question);

			$response[$question->getId()] = [
				'order' => $question->getOrder()
			];
		}

		return new Http\JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 * Writes the given key-value pairs into Database.
	 * Key 'order' should only be changed by reorderQuestions() and is not allowed here.
	 * @param int $id QuestionId of question to update
	 * @param array $keyvalues Array of key=>value pairs to update.
	 */
	public function updateQuestion(int $id, array $keyvalues): Http\JSONResponse {
		$this->logger->debug('Updating question: questionId: {id}, values: {keyvalues}', [
			'id' => $id,
			'keyvalues' => $keyvalues
		]);

		try {
			$question = $this->questionMapper->findById($id);
			$form = $this->formMapper->find($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question or form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if (array_key_exists('order', $keyvalues)) {
			$this->logger->debug('Key \'order\' is not allowed on updateQuestion. Please use reorderQuestions() to change order.');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$question = Question::fromParams($keyvalues);
		$question->setId($id);

		$this->questionMapper->update($question);

		return new Http\JSONResponse($question->getId());
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteQuestion(int $id): Http\JSONResponse {
		$this->logger->debug('Mark question as deleted: {id}', [
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

		// Store Order of deleted Question
		$deletedOrder = $question->getOrder();

		// Mark question as deleted
		$question->setOrder(0);
		$this->questionMapper->update($question);

		// Update all question-order > deleted order.
		$formQuestions = $this->questionMapper->findByForm($form->getId());
		foreach ($formQuestions as $question) {
			$questionOrder = $question->getOrder();
			if ( $questionOrder > $deletedOrder ) {
				$question->setOrder($questionOrder - 1);
				$this->questionMapper->update($question);
			}
		}

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
