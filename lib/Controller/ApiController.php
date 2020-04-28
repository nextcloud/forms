<?php


/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author René Gieling <github@dartcafe.de>
 * @author Natalie Gilbert <ngilb634@umd.edu>
 * @author Inigo Jiron
 * @author Affan Hussain
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

use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Service\FormsService;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

class ApiController extends Controller {

	protected $appName;

	/** @var SubmissionMapper */
	private $submissionMapper;

	/** @var FormMapper */
	private $formMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var OptionMapper */
	private $optionMapper;

	/** @var AnswerMapper */
	private $answerMapper;

	/** @var ILogger */
	private $logger;

	/** @var IUserSession */
	private $userSession;
	
	/** @var FormsService */
	private $formsService;

	public function __construct(string $appName,
								IRequest $request,
								$userId, // TODO remove & replace with userSession below.
								IUserSession $userSession,
								FormMapper $formMapper,
								SubmissionMapper $submissionMapper,
								AnswerMapper $answerMapper,
								QuestionMapper $questionMapper,
								OptionMapper $optionMapper,
								ILogger $logger,
								FormsService $formsService) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->userId = $userId;
		$this->userSession = $userSession;
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->answerMapper = $answerMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->logger = $logger;
		$this->formsService = $formsService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Read Form-List only with necessary information for Listing.
	 */
	public function getForms(): Http\JSONResponse {
		$forms = $this->formMapper->findAllByOwnerId($this->userId);

		$result = [];
		foreach ($forms as $form) {
			$result[] = [
				'id' => $form->getId(),
				'hash' => $form->getHash(),
				'title' => $form->getTitle(),
				'expires' => $form->getExpires(),
			];
		}

		return new Http\JSONResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * 
	 * 
	 * Read all information to edit a Form (form, questions, options, except submissions/answers).
	 */
	public function getForm(int $id): Http\JSONResponse {
		try {
			$results = $this->formsService->getForm($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new Http\JSONResponse($results);
	}

	/**
	 * @NoAdminRequired
	 * Create a new Form and return the Form to edit.
	 */
	public function newForm(): Http\JSONResponse {
		$form = new Form();

		$currentUser = \OC::$server->getUserSession()->getUser()->getUID();
		$form->setOwnerId($currentUser);
		$form->setCreated(time());
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

		// Return like getForm(), just without loading Questions (as there are none).
		$result = $form->read();
		$result['questions'] = [];

		return new Http\JSONResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $id FormId of form to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 */
	public function updateForm(int $id, array $keyValuePairs): Http\JSONResponse {
		$this->logger->debug('Updating form: FormId: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$form = $this->formMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		// Create FormEntity with given Params & Id.
		$form = Form::fromParams($keyValuePairs);
		$form->setId($id);

		// Update changed Columns in Db.
		$this->formMapper->update($form);

		return new Http\JSONResponse($form->getId());
	}

	/**
	 * @NoAdminRequired
	 */
	public function deleteForm(int $id): Http\JSONResponse {
		$this->logger->debug('Delete Form: {id}', [
			'id' => $id,
		]);

		try {
			$form = $this->formMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		// Delete Submissions(incl. Answers), Questions(incl. Options) and Form.
		$this->submissionMapper->deleteByForm($id);
		$this->questionMapper->deleteByForm($id);
		$this->formMapper->delete($form);

		return new Http\JSONResponse($id);
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

		if (array_search($type, Question::TYPES) === false) {
			$this->logger->debug('Invalid type');
			return new Http\JSONResponse(['message' => 'Invalid type'], Http::STATUS_BAD_REQUEST);
		}
		
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse(['message' => 'Could not find form'], Http::STATUS_BAD_REQUEST);
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

		$response = $question->read();
		$response['options'] = [];

		return new Http\JSONResponse($response);
	}

	/**
	 * @NoAdminRequired
	 * Updates the Order of all Questions of a Form.
	 * @param int $formId Id of the form to reorder
	 * @param int[] $newOrder Array of Question-Ids in new order.
	 */
	public function reorderQuestions(int $formId, array $newOrder): Http\JSONResponse {
		$this->logger->debug('Reordering Questions on Form {formId} as Question-Ids {newOrder}', [
			'formId' => $formId,
			'newOrder' => $newOrder
		]);

		try {
			$form = $this->formMapper->findById($formId);
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
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 */
	public function updateQuestion(int $id, array $keyValuePairs): Http\JSONResponse {
		$this->logger->debug('Updating question: questionId: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$question = $this->questionMapper->findById($id);
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question or form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		if (array_key_exists('order', $keyValuePairs)) {
			$this->logger->debug('Key \'order\' is not allowed on updateQuestion. Please use reorderQuestions() to change order.');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		// Create QuestionEntity with given Params & Id.
		$question = Question::fromParams($keyValuePairs);
		$question->setId($id);

		// Update changed Columns in Db.
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
			$form = $this->formMapper->findById($question->getFormId());
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
	public function newOption(int $questionId, string $text): Http\JSONResponse {
		$this->logger->debug('Adding new option: questionId: {questionId}, text: {text}', [
			'questionId' => $questionId,
			'text' => $text,
		]);

		try {
			$question = $this->questionMapper->findById($questionId);
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question so option can\'t be added');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$option = new Option();

		$option->setQuestionId($questionId);
		$option->setText($text);

		$option = $this->optionMapper->insert($option);

		return new Http\JSONResponse([
			'id' => $option->getId()
		]);
	}

	/**
	 * @NoAdminRequired
	 * Writes the given key-value pairs into Database.

	 * @param int $id OptionId of option to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 */
	public function updateOption(int $id, array $keyValuePairs): Http\JSONResponse {
		$this->logger->debug('Updating option: option: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$option = $this->optionMapper->findById($id);
			$question = $this->questionMapper->findById($option->getQuestionId());
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find option, question or form');
			return new Http\JSONResponse(['message' => 'Could not find option, question or form'], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		// Create OptionEntity with given Params & Id.
		$option = Option::fromParams($keyValuePairs);
		$option->setId($id);

		// Update changed Columns in Db.
		$this->optionMapper->update($option);

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
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or option');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
			return new Http\JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$this->optionMapper->delete($option);

		return new Http\JSONResponse($id);
	}

	/**
	 * @NoAdminRequired
	 */
	public function getSubmissions(string $hash): Http\JSONResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($form->getOwnerId() !== $this->userId) {
			$this->logger->debug('This form is not owned by the current user');
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

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * 
	 * Process a new submission
	 * @param int $formId
	 * @param array $answers [question_id => arrayOfString]
	 */
	public function insertSubmission(int $formId, array $answers) {
		$this->logger->debug('Inserting submission: formId: {formId}, answers: {answers}', [
			'formId' => $formId,
			'answers' => $answers,
		]);

		try {
			$form = $this->formMapper->findById($formId);
			$questions = $this->formsService->getQuestions($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			return new Http\JSONResponse(['message' => 'Could not find form'], Http::STATUS_BAD_REQUEST);
		}

		$user = $this->userSession->getUser();
		// TODO check again hasUserAccess?!

		// Create Submission
		$submission = new Submission();
		$submission->setFormId($formId);
		$submission->setTimestamp(time());

		// If not logged in or anonymous use anonID
		if (!$user || $form->getIsAnonymous()){
			$anonID = "anon-user-".  hash('md5', (time() + rand()));
			$submission->setUserId($anonID);
		} else {
			$submission->setUserId($user->getUID());
		}

		// Insert new submission
		$this->submissionMapper->insert($submission);
		$submissionId = $submission->getId();

		// Process Answers
		foreach($answers as $questionId => $answerArray) {
			// Search corresponding Question, skip processing if not found
			$questionIndex = array_search($questionId, array_column($questions, 'id'));
			if ($questionIndex === false) {
				continue;
			} else {
				$question = $questions[$questionIndex];
			}

			foreach($answerArray as $answer) {
				if($question['type'] === 'multiple' || $question['type'] === 'multiple_unique') {
					// Search corresponding option, skip processing if not found
					$optionIndex = array_search($answer, array_column($question['options'], 'id'));
					if($optionIndex === false) {
						continue;
					} else {
						$option = $question['options'][$optionIndex];
					}

					// Load option-text
					$answerText = $option['text'];

				} else {
					$answerText = $answer; // Not a multiple-question, answerText is given answer
				}

				$answerEntity = new Answer();
				$answerEntity->setSubmissionId($submissionId);
				$answerEntity->setQuestionId($question['id']);
				$answerEntity->setText($answerText);
				$this->answerMapper->insert($answerEntity);
			}
		}

		return new Http\JSONResponse([]);
	}
}
