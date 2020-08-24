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

use Exception;
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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
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

	/** @var IL10N */
	private $l10n;

	/** @var IUser */
	private $currentUser;

	/** @var IUserManager */
	private $userManager;

	/** @var FormsService */
	private $formsService;

	/** @var ISecureRandom */
	private $secureRandom;

	public function __construct(string $appName,
								IRequest $request,
								IUserSession $userSession,
								IUserManager $userManager,
								FormMapper $formMapper,
								SubmissionMapper $submissionMapper,
								AnswerMapper $answerMapper,
								QuestionMapper $questionMapper,
								OptionMapper $optionMapper,
								ILogger $logger,
								IL10N $l10n,
								FormsService $formsService,
								ISecureRandom $secureRandom) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->userManager = $userManager;
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->answerMapper = $answerMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->formsService = $formsService;
		$this->secureRandom = $secureRandom;

		$this->currentUser = $userSession->getUser();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Read Form-List only with necessary information for Listing.
	 */
	public function getForms(): DataResponse {
		$forms = $this->formMapper->findAllByOwnerId($this->currentUser->getUID());

		$result = [];
		foreach ($forms as $form) {
			$result[] = [
				'id' => $form->getId(),
				'hash' => $form->getHash(),
				'title' => $form->getTitle(),
				'expires' => $form->getExpires(),
				'partial' => true
			];
		}

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Read all information to edit a Form (form, questions, options, except submissions/answers).
	 *
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getForm(int $id): DataResponse {
		try {
			$form = $this->formsService->getForm($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($id)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		return new DataResponse($form);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Create a new Form and return the Form to edit.
	 *
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newForm(): DataResponse {
		$form = new Form();

		$form->setOwnerId($this->currentUser->getUID());
		$form->setCreated(time());
		$form->setHash($this->secureRandom->generate(
			16,
			ISecureRandom::CHAR_HUMAN_READABLE
		));

		$form->setTitle('');
		$form->setDescription('');
		$form->setAccess([
			'type' => 'public'
		]);
		$form->setSubmitOnce(true);

		$this->formMapper->insert($form);

		// Return like getForm(), just without loading Questions (as there are none).
		$result = $form->read();
		$result['questions'] = [];

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $id FormId of form to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateForm(int $id, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating form: FormId: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$form = $this->formMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Make sure we only store id
		try {
			if (array_key_exists('access', $keyValuePairs)) {
				$keyValuePairs['access']['users'] = array_map(function (array $user): string {
					return $user['shareWith'];
				}, $keyValuePairs['access']['users']);
				$keyValuePairs['access']['groups'] = array_map(function (array $group): string {
					return $group['shareWith'];
				}, $keyValuePairs['access']['groups']);
			}
		} catch (Exception $e) {
			$this->logger->debug('Malformed access');
			throw new OCSBadRequestException('Malformed access');
		}

		// Create FormEntity with given Params & Id.
		$form = Form::fromParams($keyValuePairs);
		$form->setId($id);

		// Update changed Columns in Db.
		$this->formMapper->update($form);

		return new DataResponse($form->getId());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete a form
	 *
	 * @param int $id the form id
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteForm(int $id): DataResponse {
		$this->logger->debug('Delete Form: {id}', [
			'id' => $id,
		]);

		try {
			$form = $this->formMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Delete Submissions(incl. Answers), Questions(incl. Options) and Form.
		$this->submissionMapper->deleteByForm($id);
		$this->questionMapper->deleteByForm($id);
		$this->formMapper->delete($form);

		return new DataResponse($id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Add a new question
	 *
	 * @param int $formId the form id
	 * @param string $type the new question type
	 * @param string $text the new question title
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newQuestion(int $formId, string $type, string $text = ''): DataResponse {
		$this->logger->debug('Adding new question: formId: {formId}, type: {type}, text: {text}', [
			'formId' => $formId,
			'type' => $type,
			'text' => $text,
		]);

		if (array_search($type, Question::TYPES) === false) {
			$this->logger->debug('Invalid type');
			throw new OCSBadRequestException('Invalid type');
		}

		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
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
		$question->setMandatory(false);

		$question = $this->questionMapper->insert($question);

		$response = $question->read();
		$response['options'] = [];

		return new DataResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Updates the Order of all Questions of a Form.
	 *
	 * @param int $formId Id of the form to reorder
	 * @param int[] $newOrder Array of Question-Ids in new order.
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function reorderQuestions(int $formId, array $newOrder): DataResponse {
		$this->logger->debug('Reordering Questions on Form {formId} as Question-Ids {newOrder}', [
			'formId' => $formId,
			'newOrder' => $newOrder
		]);

		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Check if array contains duplicates
		if (array_unique($newOrder) !== $newOrder) {
			$this->logger->debug('The given Array contains duplicates');
			throw new OCSBadRequestException('The given Array contains duplicates');
		}

		// Check if all questions are given in Array.
		$questions = $this->questionMapper->findByForm($formId);
		if (sizeof($questions) !== sizeof($newOrder)) {
			$this->logger->debug('The length of the given array does not match the number of stored questions');
			throw new OCSBadRequestException('The length of the given array does not match the number of stored questions');
		}

		$questions = []; // Clear Array of Entities
		$response = []; // Array of ['questionId' => ['order' => newOrder]]

		// Store array of Question-Entities and check the Questions FormId & old Order.
		foreach ($newOrder as $arrayKey => $questionId) {
			try {
				$questions[$arrayKey] = $this->questionMapper->findById($questionId);
			} catch (IMapperException $e) {
				$this->logger->debug('Could not find question. Id:{id}', [
					'id' => $questionId
				]);
				throw new OCSBadRequestException();
			}

			// Abort if a question is not part of the Form.
			if ($questions[$arrayKey]->getFormId() !== $formId) {
				$this->logger->debug('This Question is not part of the given Form: questionId: {questionId}', [
					'questionId' => $questionId
				]);
				throw new OCSBadRequestException();
			}

			// Abort if a question is already marked as deleted (order==0)
			$oldOrder = $questions[$arrayKey]->getOrder();
			if ($oldOrder === 0) {
				$this->logger->debug('This Question has already been marked as deleted: Id: {id}', [
					'id' => $questions[$arrayKey]->getId()
				]);
				throw new OCSBadRequestException();
			}

			// Only set order, if it changed.
			if ($oldOrder !== $arrayKey + 1) {
				// Set Order. ArrayKey counts from zero, order counts from 1.
				$questions[$arrayKey]->setOrder($arrayKey + 1);
			}
		}

		// Write to Database
		foreach ($questions as $question) {
			$this->questionMapper->update($question);

			$response[$question->getId()] = [
				'order' => $question->getOrder()
			];
		}

		return new DataResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 * Key 'order' should only be changed by reorderQuestions() and is not allowed here.
	 *
	 * @param int $id QuestionId of question to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateQuestion(int $id, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating question: questionId: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$question = $this->questionMapper->findById($id);
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question');
			throw new OCSBadRequestException('Could not find form or question');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		if (array_key_exists('order', $keyValuePairs)) {
			$this->logger->debug('Key \'order\' is not allowed on updateQuestion. Please use reorderQuestions() to change order.');
			throw new OCSForbiddenException('Please use reorderQuestions() to change order');
		}

		// Create QuestionEntity with given Params & Id.
		$question = Question::fromParams($keyValuePairs);
		$question->setId($id);

		// Update changed Columns in Db.
		$this->questionMapper->update($question);

		return new DataResponse($question->getId());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete a question
	 *
	 * @param int $id the question id
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteQuestion(int $id): DataResponse {
		$this->logger->debug('Mark question as deleted: {id}', [
			'id' => $id,
		]);

		try {
			$question = $this->questionMapper->findById($id);
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question');
			throw new OCSBadRequestException('Could not find form or question');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
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
			if ($questionOrder > $deletedOrder) {
				$question->setOrder($questionOrder - 1);
				$this->questionMapper->update($question);
			}
		}

		return new DataResponse($id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Add a new option to a question
	 *
	 * @param int $questionId the question id
	 * @param string $text the new option text
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newOption(int $questionId, string $text): DataResponse {
		$this->logger->debug('Adding new option: questionId: {questionId}, text: {text}', [
			'questionId' => $questionId,
			'text' => $text,
		]);

		try {
			$question = $this->questionMapper->findById($questionId);
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question');
			throw new OCSBadRequestException('Could not find form or question');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		$option = new Option();

		$option->setQuestionId($questionId);
		$option->setText($text);

		$option = $this->optionMapper->insert($option);

		return new DataResponse([
			'id' => $option->getId()
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $id OptionId of option to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateOption(int $id, array $keyValuePairs): DataResponse {
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
			throw new OCSBadRequestException('Could not find option, question or form');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Create OptionEntity with given Params & Id.
		$option = Option::fromParams($keyValuePairs);
		$option->setId($id);

		// Update changed Columns in Db.
		$this->optionMapper->update($option);

		return new DataResponse($option->getId());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete an option
	 *
	 * @param int $id the option id
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteOption(int $id): DataResponse {
		$this->logger->debug('Deleting option: {id}', [
			'id' => $id
		]);

		try {
			$option = $this->optionMapper->findById($id);
			$question = $this->questionMapper->findById($option->getQuestionId());
			$form = $this->formMapper->findById($question->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or option');
			throw new OCSBadRequestException('Could not find form or option');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		$this->optionMapper->delete($option);

		return new DataResponse($id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get all the answers of a given submission
	 *
	 * @param int $submissionId the submission id
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	private function getAnswers(int $submissionId): array {
		try {
			$answerEntities = $this->answerMapper->findBySubmission($submissionId);
		} catch (DoesNotExistException $e) {
			//Just ignore, if no Data. Returns empty Answers-Array
		}

		// Load Answer-Data
		$answers = [];
		foreach ($answerEntities as $answerEntity) {
			$answers[] = $answerEntity->read();
		}

		return $answers;
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get all the submissions of a given form
	 *
	 * @param string $hash the form hash
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getSubmissions(string $hash): DataResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		try {
			$submissionEntities = $this->submissionMapper->findByForm($form->getId());
		} catch (DoesNotExistException $e) {
			// Just ignore, if no Data. Returns empty Submissions-Array
		}

		$submissions = [];
		foreach ($submissionEntities as $submissionEntity) {
			// Load Submission-Data & corresponding Answers
			$submission = $submissionEntity->read();
			$submission['answers'] = $this->getAnswers($submission['id']);

			// Append Display Name
			if (substr($submission['userId'], 0, 10) === 'anon-user-') {
				// Anonymous User
				$submission['userDisplayName'] = $this->l10n->t('Anonymous response');
			} else {
				$userEntity = $this->userManager->get($submission['userId']);

				if ($userEntity instanceof IUser) {
					$submission['userDisplayName'] = $userEntity->getDisplayName();
				} else {
					// Fallback, should not occur regularly.
					$submission['userDisplayName'] = $submission['userId'];
				}
			}

			// Add to returned List of Submissions
			$submissions[] = $submission;
		}

		// Load currently active questions
		$questions = $this->formsService->getQuestions($form->getId());

		$response = [
			'submissions' => $submissions,
			'questions' => $questions,
		];

		return new DataResponse($response);
	}

	/**
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * Process a new submission
	 *
	 * @param int $formId the form id
	 * @param array $answers [question_id => arrayOfString]
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function insertSubmission(int $formId, array $answers): DataResponse {
		$this->logger->debug('Inserting submission: formId: {formId}, answers: {answers}', [
			'formId' => $formId,
			'answers' => $answers,
		]);

		try {
			$form = $this->formMapper->findById($formId);
			$questions = $this->formsService->getQuestions($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		// Does the user have access to the form
		if (!$this->formsService->hasUserAccess($form->getId())) {
			throw new OCSForbiddenException('Not allowed to access this form');
		}

		// Not allowed if form expired. Expires is '0' if the form does not expire.
		if ($form->getExpires() && $form->getExpires() < time()) {
			throw new OCSForbiddenException('This form is no longer taking answers');
		}

		// Does the user have permissions to submit
		if (!$this->formsService->canSubmit($form->getId())) {
			throw new OCSForbiddenException('Already submitted');
		}

		// Create Submission
		$submission = new Submission();
		$submission->setFormId($formId);
		$submission->setTimestamp(time());

		// If not logged in or anonymous use anonID
		if (!$this->currentUser || $form->getIsAnonymous()) {
			$anonID = "anon-user-".  hash('md5', (time() + rand()));
			$submission->setUserId($anonID);
		} else {
			$submission->setUserId($this->currentUser->getUID());
		}

		// Insert new submission
		$this->submissionMapper->insert($submission);
		$submissionId = $submission->getId();

		// Process Answers
		foreach ($answers as $questionId => $answerArray) {
			// Search corresponding Question, skip processing if not found
			$questionIndex = array_search($questionId, array_column($questions, 'id'));
			if ($questionIndex === false) {
				continue;
			} else {
				$question = $questions[$questionIndex];
			}

			foreach ($answerArray as $answer) {
				// Are we using answer ids as values
				if ($question['type'] === 'multiple'
					|| $question['type'] === 'multiple_unique'
					|| $question['type'] === 'dropdown') {

					// Search corresponding option, skip processing if not found
					$optionIndex = array_search($answer, array_column($question['options'], 'id'));
					if ($optionIndex === false) {
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

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete a specific submission
	 *
	 * @param int $id the submission id
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteSubmission(int $id): DataResponse {
		$this->logger->debug('Delete Submission: {id}', [
			'id' => $id,
		]);

		try {
			$submission = $this->submissionMapper->findById($id);
			$form = $this->formMapper->findById($submission->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or submission');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Delete submission (incl. Answers)
		$this->submissionMapper->delete($submission);

		return new DataResponse($id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete all submissions of a specified form
	 *
	 * @param int $formId the form id
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteAllSubmissions(int $formId): DataResponse {
		$this->logger->debug('Delete all submissions to form: {formId}', [
			'formId' => $formId,
		]);

		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Delete all submissions (incl. Answers)
		$this->submissionMapper->deleteByForm($formId);

		return new DataResponse($formId);
	}
}
