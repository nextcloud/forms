<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0-or-later
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

use OCA\Forms\Activity\ActivityManager;
use OCA\Forms\Constants;
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
use OCA\Forms\Service\SubmissionService;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;

class ApiController extends OCSController {
	protected $appName;

	/** @var ActivityManager */
	private $activityManager;

	/** @var AnswerMapper */
	private $answerMapper;

	/** @var FormMapper */
	private $formMapper;

	/** @var OptionMapper */
	private $optionMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var SubmissionMapper */
	private $submissionMapper;

	/** @var FormsService */
	private $formsService;

	/** @var SubmissionService */
	private $submissionService;

	/** @var IL10N */
	private $l10n;

	/** @var ILogger */
	private $logger;

	/** @var IUser */
	private $currentUser;

	/** @var IUserManager */
	private $userManager;

	/** @var ISecureRandom */
	private $secureRandom;

	public function __construct(string $appName,
								ActivityManager $activityManager,
								AnswerMapper $answerMapper,
								FormMapper $formMapper,
								OptionMapper $optionMapper,
								QuestionMapper $questionMapper,
								SubmissionMapper $submissionMapper,
								FormsService $formsService,
								SubmissionService $submissionService,
								IL10N $l10n,
								ILogger $logger,
								IRequest $request,
								IUserManager $userManager,
								IUserSession $userSession,
								ISecureRandom $secureRandom) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->activityManager = $activityManager;
		$this->answerMapper = $answerMapper;
		$this->formMapper = $formMapper;
		$this->optionMapper = $optionMapper;
		$this->questionMapper = $questionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->formsService = $formsService;
		$this->submissionService = $submissionService;

		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;

		$this->currentUser = $userSession->getUser();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Read Form-List of owned forms
	 * Return only with necessary information for Listing.
	 * @return DataResponse
	 */
	public function getForms(): DataResponse {
		$forms = $this->formMapper->findAllByOwnerId($this->currentUser->getUID());

		$result = [];
		foreach ($forms as $form) {
			$result[] = $this->formsService->getPartialFormArray($form->getId());
		}

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Read List of forms shared with current user
	 * Return only with necessary information for Listing.
	 * @return DataResponse
	 */
	public function getSharedForms(): DataResponse {
		$forms = $this->formMapper->findAll();

		$result = [];
		foreach ($forms as $form) {
			// Check if the form should be shown on sidebar
			if (!$this->formsService->isSharedFormShown($form->getId())) {
				continue;
			}
			$result[] = $this->formsService->getPartialFormArray($form->getId());
		}

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get a partial form by its hash. Implicitely checks, if the user has access.
	 *
	 * @param string $hash The form hash
	 * @return DataResponse
	 * @throws OCSBadRequestException if forbidden or not found
	 */
	public function getPartialForm(string $hash): DataResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($form->getId())) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		return new DataResponse($this->formsService->getPartialFormArray($form->getId()));
	}

	/**
	 * @NoAdminRequired
	 *
	 * Read all information to edit a Form (form, questions, options, except submissions/answers).
	 *
	 * @return DataResponse
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

		// TODO remove after removal of API v1.1
		// Backward compatibility for mandatory
		foreach ($form['questions'] as $index => $question) {
			$form['questions'][$index]['mandatory'] = $question['isRequired'];
		}

		return new DataResponse($form);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Create a new Form and return the Form to edit.
	 *
	 * @return DataResponse
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
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setSubmitOnce(true);

		$this->formMapper->insert($form);

		// Return like getForm(), just without loading Questions (as there are none).
		$result = $form->read();
		$result['questions'] = [];
		$result['shares'] = [];

		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Clones a form
	 *
	 * @param int $id FormId of the form to clone
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function cloneForm(int $id): DataResponse {
		$this->logger->debug('Cloning Form: {id}', [
			'id' => $id
		]);

		try {
			$oldForm = $this->formMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		// Only allow owner to clone a form
		if ($oldForm->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		// Read Form, set new Form specific data, extend Title.
		$formData = $oldForm->read();
		unset($formData['id']);
		$formData['created'] = time();
		$formData['hash'] = $this->secureRandom->generate(
			16,
			ISecureRandom::CHAR_HUMAN_READABLE
		);
		// TRANSLATORS Appendix to the form Title of a duplicated/copied form.
		$formData['title'] .= ' - ' . $this->l10n->t('Copy');

		$newForm = Form::fromParams($formData);
		$this->formMapper->insert($newForm);

		// Get Questions, set new formId, reinsert
		$questions = $this->questionMapper->findByForm($oldForm->getId());
		foreach ($questions as $oldQuestion) {
			$questionData = $oldQuestion->read();

			unset($questionData['id']);
			$questionData['formId'] = $newForm->getId();
			$newQuestion = Question::fromParams($questionData);
			$this->questionMapper->insert($newQuestion);

			// Get Options, set new QuestionId, reinsert
			$options = $this->optionMapper->findByQuestion($oldQuestion->getId());
			foreach ($options as $oldOption) {
				$optionData = $oldOption->read();

				unset($optionData['id']);
				$optionData['questionId'] = $newQuestion->getId();
				$newOption = Option::fromParams($optionData);
				$this->optionMapper->insert($newOption);
			}
		}

		// Return just like getForm does. Returns the full form.
		return $this->getForm($newForm->getId());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $id FormId of form to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse
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

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		// Don't allow to change params id, hash, ownerId, created
		if (key_exists('id', $keyValuePairs) || key_exists('hash', $keyValuePairs) ||
			key_exists('ownerId', $keyValuePairs) || key_exists('created', $keyValuePairs)) {
			$this->logger->info('Not allowed to update id, hash, ownerId or created');
			throw new OCSForbiddenException();
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
	 * @return DataResponse
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

		$this->formMapper->deleteForm($form);

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
	 * @return DataResponse
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
		$question->setIsRequired(false);

		$question = $this->questionMapper->insert($question);

		$response = $question->read();
		$response['options'] = [];

		// TODO remove after removal of API v1.1
		// Backward compatibility for mandatory
		$response['mandatory'] = $response['isRequired'];

		return new DataResponse($response);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Updates the Order of all Questions of a Form.
	 *
	 * @param int $formId Id of the form to reorder
	 * @param int[] $newOrder Array of Question-Ids in new order.
	 * @return DataResponse
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
	 * @return DataResponse
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

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		//Don't allow to change id or formId
		if (key_exists('id', $keyValuePairs) || key_exists('formId', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update id or formId');
			throw new OCSForbiddenException();
		}

		// Don't allow to reorder here
		if (key_exists('order', $keyValuePairs)) {
			$this->logger->debug('Key \'order\' is not allowed on updateQuestion. Please use reorderQuestions() to change order.');
			throw new OCSForbiddenException('Please use reorderQuestions() to change order');
		}

		// TODO remove after removal of API v1.1
		// Rename deprecated mandatory key to isRequired
		if (key_exists('mandatory', $keyValuePairs)) {
			$this->logger->info('Key \'mandatory\' is deprecated, please use \'isRequired\'.');
			$keyValuePairs['isRequired'] = $keyValuePairs['mandatory'];
			unset($keyValuePairs['mandatory']);
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
	 * @return DataResponse
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
	 * @return DataResponse
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

		return new DataResponse($option->read());
	}

	/**
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $id OptionId of option to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse
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

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		//Don't allow to change id or questionId
		if (key_exists('id', $keyValuePairs) || key_exists('questionId', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update id or questionId');
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
	 * @return DataResponse
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
	 * @return array
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
	 * @return DataResponse
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
				// TRANSLATORS On Results when listing the single Responses to the form, this text is shown as heading of the Response.
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

		// TODO remove after removal of API v1.1
		// Backward compatibility for mandatory
		foreach ($questions as $index => $question) {
			$questions[$index]['mandatory'] = $question['isRequired'];
		}
		
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
	 * @return DataResponse
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

		// Not allowed if form has expired.
		if ($this->formsService->hasFormExpired($form->getId())) {
			throw new OCSForbiddenException('This form is no longer taking answers');
		}

		// Does the user have permissions to submit
		if (!$this->formsService->canSubmit($form->getId())) {
			throw new OCSForbiddenException('Already submitted');
		}

		// Is the submission valid
		if (!$this->submissionService->validateSubmission($questions, $answers)) {
			throw new OCSBadRequestException('At least one submitted answer is not valid');
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
				if (in_array($question['type'], Constants::ANSWER_PREDEFINED)) {
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

		//Create Activity
		$this->activityManager->publishNewSubmission($form, $submission->getUserId());

		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete a specific submission
	 *
	 * @param int $id the submission id
	 * @return DataResponse
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
		$this->submissionMapper->deleteById($id);

		return new DataResponse($id);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Delete all submissions of a specified form
	 *
	 * @param int $formId the form id
	 * @return DataResponse
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

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * Export submissions of a specified form
	 *
	 * @param string $hash the form hash
	 * @return DataDownloadResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function exportSubmissions(string $hash): DataDownloadResponse {
		$this->logger->debug('Export submissions for form: {hash}', [
			'hash' => $hash,
		]);

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

		$csv = $this->submissionService->getSubmissionsCsv($hash);
		return new DataDownloadResponse($csv['data'], $csv['fileName'], 'text/csv');
	}

	/**
	 * @NoAdminRequired
	 *
	 * Export Submissions to the Cloud
	 *
	 * @param string $hash of the form
	 * @param string $path The Cloud-Path to export to
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function exportSubmissionsToCloud(string $hash, string $path) {
		$this->logger->debug('Export submissions for form: {hash} to Cloud at: /{path}', [
			'hash' => $hash,
			'path' => $path,
		]);

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

		// Write file to cloud
		try {
			$fileName = $this->submissionService->writeCsvToCloud($hash, $path);
		} catch (NotPermittedException $e) {
			$this->logger->debug('Failed to export Submissions: Not allowed to write to file');
			throw new OCSException('Not allowed to write to file.');
		}

		return new DataResponse($fileName);
	}
}
