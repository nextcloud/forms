<?php

/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Christian Hartmann <chris-hartmann@gmx.de>
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
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

use OCA\Forms\BackgroundJob\SyncSubmissionsWithLinkedFileJob;
use OCA\Forms\Constants;
use OCA\Forms\Db\Answer;
use OCA\Forms\Db\AnswerMapper;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Option;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Db\UploadedFile;
use OCA\Forms\Db\UploadedFileMapper;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\BackgroundJob\IJobList;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;

use Psr\Log\LoggerInterface;

class ApiController extends OCSController {
	private ?IUser $currentUser;

	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		private AnswerMapper $answerMapper,
		private FormMapper $formMapper,
		private OptionMapper $optionMapper,
		private QuestionMapper $questionMapper,
		private ShareMapper $shareMapper,
		private SubmissionMapper $submissionMapper,
		private ConfigService $configService,
		private FormsService $formsService,
		private SubmissionService $submissionService,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IUserManager $userManager,
		private IRootFolder $rootFolder,
		private UploadedFileMapper $uploadedFileMapper,
		private IMimeTypeDetector $mimeTypeDetector,
		private IJobList $jobList,
	) {
		parent::__construct($appName, $request);
		$this->currentUser = $userSession->getUser();
	}

	// API v3 methods
	// Forms
	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read Form-List of owned forms
	 * Return only with necessary information for Listing.
	 * @return DataResponse
	 */
	public function getForms(string $type = 'owned'): DataResponse {
		if ($type === 'owned') {
			$forms = $this->formMapper->findAllByOwnerId($this->currentUser->getUID());
			$result = [];
			foreach ($forms as $form) {
				$result[] = $this->formsService->getPartialFormArray($form);
			}
			return new DataResponse($result);
		} elseif ($type === 'shared') {
			$forms = $this->formsService->getSharedForms($this->currentUser);
			$result = array_values(array_map(fn (Form $form): array => $this->formsService->getPartialFormArray($form), $forms));
			return new DataResponse($result);
		} else {
			throw new OCSBadRequestException();
		}
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Create a new Form and return the Form to edit.
	 * Return a cloned Form if the parameter $fromId is set
	 *
	 * @param int $fromId (optional) ID of the Form that should be cloned
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newForm(?int $fromId = null): DataResponse {
		// Check if user is allowed
		if (!$this->configService->canCreateForms()) {
			$this->logger->debug('This user is not allowed to create Forms.');
			throw new OCSForbiddenException();
		}

		if ($fromId === null) {
			// Create Form
			$form = new Form();
			$form->setOwnerId($this->currentUser->getUID());
			$form->setHash($this->formsService->generateFormHash());
			$form->setTitle('');
			$form->setDescription('');
			$form->setAccess([
				'permitAllUsers' => false,
				'showToAllUsers' => false,
			]);
			$form->setSubmitMultiple(false);
			$form->setShowExpiration(false);
			$form->setExpires(0);
			$form->setIsAnonymous(false);

			$this->formMapper->insert($form);
		} else {
			$oldForm = $this->getFormIfAllowed($fromId);

			// Read old form, (un)set new form specific data, extend title
			$formData = $oldForm->read();
			unset($formData['id']);
			unset($formData['created']);
			unset($formData['lastUpdated']);
			unset($formData['fileId']);
			unset($formData['fileFormat']);
			$formData['hash'] = $this->formsService->generateFormHash();
			// TRANSLATORS Appendix to the form Title of a duplicated/copied form.
			$formData['title'] .= ' - ' . $this->l10n->t('Copy');

			$form = Form::fromParams($formData);
			$this->formMapper->insert($form);

			// Get Questions, set new formId, reinsert
			$questions = $this->questionMapper->findByForm($oldForm->getId());
			foreach ($questions as $oldQuestion) {
				$questionData = $oldQuestion->read();

				unset($questionData['id']);
				$questionData['formId'] = $form->getId();
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
		}
		return $this->getForm($form->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read all information to edit a Form (form, questions, options, except submissions/answers).
	 *
	 * @param int $formId Id of the form
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getForm(int $formId): DataResponse {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		$formData = $this->formsService->getForm($form);

		return new DataResponse($formData);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $formId FormId of form to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateForm(int $formId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating form: formId: {formId}, values: {keyValuePairs}', [
			'formId' => $formId,
			'keyValuePairs' => $keyValuePairs
		]);

		$form = $this->getFormIfAllowed($formId);

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		// Process owner transfer
		if (sizeof($keyValuePairs) === 1 && key_exists('ownerId', $keyValuePairs)) {
			$this->logger->debug('Updating owner: formId: {formId}, userId: {uid}', [
				'formId' => $formId,
				'uid' => $keyValuePairs['ownerId']
			]);

			$user = $this->userManager->get($keyValuePairs['ownerId']);
			if ($user == null) {
				$this->logger->debug('Could not find new form owner');
				throw new OCSBadRequestException('Could not find new form owner');
			}

			// update form owner
			$form->setOwnerId($keyValuePairs['ownerId']);

			// Update changed Columns in Db.
			$this->formMapper->update($form);

			return new DataResponse($form->getOwnerId());
		}

		// Don't allow to change params id, hash, ownerId, created, lastUpdated, fileId
		if (
			key_exists('id', $keyValuePairs) || key_exists('hash', $keyValuePairs) ||
			key_exists('ownerId', $keyValuePairs) || key_exists('created', $keyValuePairs) ||
			isset($keyValuePairs['fileId']) || key_exists('lastUpdated', $keyValuePairs)
		) {
			$this->logger->info('Not allowed to update id, hash, ownerId, created, fileId or lastUpdated');
			throw new OCSForbiddenException();
		}

		// Do not allow changing showToAllUsers if disabled
		if (isset($keyValuePairs['access'])) {
			$showAll = $keyValuePairs['access']['showToAllUsers'] ?? false;
			$permitAll = $keyValuePairs['access']['permitAllUsers'] ?? false;
			if (($showAll && !$this->configService->getAllowShowToAll())
				|| ($permitAll && !$this->configService->getAllowPermitAll())) {
				$this->logger->info('Not allowed to update showToAllUsers or permitAllUsers');
				throw new OCSForbiddenException();
			}
		}

		// Process file linking
		if (isset($keyValuePairs['path']) && isset($keyValuePairs['fileFormat'])) {
			$file = $this->submissionService->writeFileToCloud($form, $keyValuePairs['path'], $keyValuePairs['fileFormat']);

			$form->setFileId($file->getId());
			$form->setFileFormat($keyValuePairs['fileFormat']);
		}

		// Process file unlinking
		if (key_exists('fileId', $keyValuePairs) && key_exists('fileFormat', $keyValuePairs) && !isset($keyValuePairs['fileId']) && !isset($keyValuePairs['fileFormat'])) {
			$form->setFileId(null);
			$form->setFileFormat(null);
		}

		unset($keyValuePairs['path']);
		unset($keyValuePairs['fileId']);
		unset($keyValuePairs['fileFormat']);

		// Create FormEntity with given Params & Id.
		foreach ($keyValuePairs as $key => $value) {
			$method = 'set' . ucfirst($key);
			$form->$method($value);
		}

		// Update changed Columns in Db.
		$this->formMapper->update($form);

		return new DataResponse($form->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a form
	 *
	 * @param int $formId the form id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteForm(int $formId): DataResponse {
		$this->logger->debug('Delete Form: {formId}', [
			'formId' => $formId,
		]);

		$form = $this->getFormIfAllowed($formId);
		$this->formMapper->deleteForm($form);

		return new DataResponse($formId);
	}

	// Questions
	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read all questions (including options)
	 *
	 * @param int $formId FormId
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getQuestions(int $formId): DataResponse {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		$questionData = $this->formsService->getQuestions($formId);

		return new DataResponse($questionData);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read a specific question (including options)
	 *
	 * @param int $formId FormId
	 * @param int $questionId QuestionId
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getQuestion(int $formId, int $questionId): DataResponse {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		$question = $this->formsService->getQuestion($questionId);

		if ($question['formId'] !== $formId) {
			throw new OCSBadRequestException('Question doesn\'t belong to given Form');
		}

		return new DataResponse($question);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Add a new question
	 *
	 * @param int $formId the form id
	 * @param string $type the new question type
	 * @param string $text the new question title
	 * @param int $fromId (optional) id of the question that should be cloned
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newQuestion(int $formId, ?string $type = null, string $text = '', ?int $fromId = null): DataResponse {
		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		if ($fromId === null) {
			$this->logger->debug('Adding new question: formId: {formId}, type: {type}, text: {text}', [
				'formId' => $formId,
				'type' => $type,
				'text' => $text,
			]);

			if (array_search($type, Constants::ANSWER_TYPES) === false) {
				$this->logger->debug('Invalid type');
				throw new OCSBadRequestException('Invalid type');
			}

			// Block creation of datetime questions
			if ($type === 'datetime') {
				$this->logger->debug('Datetime question type no longer supported');
				throw new OCSBadRequestException('Datetime question type no longer supported');
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
			$question->setDescription('');
			$question->setIsRequired(false);
			$question->setExtraSettings([]);

			$question = $this->questionMapper->insert($question);

			$response = $question->read();
			$response['options'] = [];
			$response['accept'] = [];
		} else {
			$this->logger->debug('Question to be cloned: {fromId}', [
				'fromId' => $fromId
			]);

			try {
				$sourceQuestion = $this->questionMapper->findById($fromId);
				$sourceOptions = $this->optionMapper->findByQuestion($fromId);
			} catch (IMapperException $e) {
				$this->logger->debug('Could not find question');
				throw new OCSNotFoundException('Could not find question');
			}

			$allQuestions = $this->questionMapper->findByForm($formId);

			$questionData = $sourceQuestion->read();
			unset($questionData['id']);
			$questionData['order'] = end($allQuestions)->getOrder() + 1;

			$newQuestion = Question::fromParams($questionData);
			$this->questionMapper->insert($newQuestion);

			$response = $newQuestion->read();
			$response['options'] = [];
			$response['accept'] = [];

			foreach ($sourceOptions as $sourceOption) {
				$optionData = $sourceOption->read();

				unset($optionData['id']);
				$optionData['questionId'] = $newQuestion->getId();
				$newOption = Option::fromParams($optionData);
				$insertedOption = $this->optionMapper->insert($newOption);

				$response['options'][] = $insertedOption->read();
			}
		}

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 * Key 'order' should only be changed by reorderQuestions() and is not allowed here.
	 *
	 * @param int $formId the form id
	 * @param int $questionId id of question to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateQuestion(int $formId, int $questionId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating question: formId: {formId}, questionId: {questionId}, values: {keyValuePairs}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSBadRequestException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			throw new OCSBadRequestException('Question doesn\'t belong to given Form');
		}

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		//Don't allow to change id or formId
		if (key_exists('id', $keyValuePairs) || key_exists('formId', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update \'id\' or \'formId\'');
			throw new OCSForbiddenException();
		}

		// Don't allow to reorder here
		if (key_exists('order', $keyValuePairs)) {
			$this->logger->debug('Key \'order\' is not allowed on updateQuestion. Please use reorderQuestions() to change order.');
			throw new OCSForbiddenException('Please use reorderQuestions() to change order');
		}

		if (key_exists('extraSettings', $keyValuePairs) && !$this->formsService->areExtraSettingsValid($keyValuePairs['extraSettings'], $question->getType())) {
			throw new OCSBadRequestException('Invalid extraSettings, will not update.');
		}

		// Create QuestionEntity with given Params & Id.
		$question = Question::fromParams($keyValuePairs);
		$question->setId($questionId);

		// Update changed Columns in Db.
		$this->questionMapper->update($question);
		$this->formMapper->update($form);

		return new DataResponse($question->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a question
	 *
	 * @param int $formId the form id
	 * @param int $questionId the question id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteQuestion(int $formId, int $questionId): DataResponse {
		$this->logger->debug('Mark question as deleted: {questionId}', [
			'questionId' => $questionId,
		]);

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSBadRequestException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			throw new OCSBadRequestException('Question doesn\'t belong to given Form');
		}

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		// Store Order of deleted Question
		$deletedOrder = $question->getOrder();

		// Mark question as deleted
		$question->setOrder(0);
		$this->questionMapper->update($question);

		// Update all question-order > deleted order.
		$formQuestions = $this->questionMapper->findByForm($formId);
		foreach ($formQuestions as $question) {
			$questionOrder = $question->getOrder();
			if ($questionOrder > $deletedOrder) {
				$question->setOrder($questionOrder - 1);
				$this->questionMapper->update($question);
			}
		}

		$this->formMapper->update($form);

		return new DataResponse($questionId);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Updates the Order of all Questions of a Form.
	 *
	 * @param int $formId Id of the form to reorder
	 * @param Array<int, int> $newOrder Array of Question-Ids in new order.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function reorderQuestions(int $formId, array $newOrder): DataResponse {
		$this->logger->debug('Reordering Questions on Form {formId} as Question-Ids {newOrder}', [
			'formId' => $formId,
			'newOrder' => $newOrder
		]);

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		// Check if array contains duplicates
		if (array_unique($newOrder) !== $newOrder) {
			$this->logger->debug('The given array contains duplicates');
			throw new OCSBadRequestException('The given array contains duplicates');
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
				$this->logger->debug('Could not find question. Id: {questionId}', [
					'questionId' => $questionId
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
				$this->logger->debug('This Question has already been marked as deleted: Id: {questionId}', [
					'questionId' => $questions[$arrayKey]->getId()
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

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	// Options

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Add a new option to a question
	 *
	 * @param int $formId id of the form
	 * @param int $questionId id of the question
	 * @param array<string> $optionTexts the new option text
	 * @return DataResponse Returns a DataResponse containing the added options
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newOption(int $formId, int $questionId, array $optionTexts): DataResponse {
		$this->logger->debug('Adding new options: formId: {formId}, questionId: {questionId}, text: {text}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'text' => $optionTexts,
		]);

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSBadRequestException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			$this->logger->debug('This Question is not part of the given Form: questionId: {questionId}', [
				'questionId' => $questionId
			]);
			throw new OCSBadRequestException();
		}

		// Retrieve all options sorted by 'order'. Takes the order of the last array-element and adds one.
		$options = $this->optionMapper->findByQuestion($questionId);
		$lastOption = array_pop($options);
		if ($lastOption) {
			$optionOrder = $lastOption->getOrder() + 1;
		} else {
			$optionOrder = 1;
		}

		$addedOptions = [];
		foreach ($optionTexts as $text) {
			$option = new Option();

			$option->setQuestionId($questionId);
			$option->setText($text);
			$option->setOrder($optionOrder++);

			try {
				$option = $this->optionMapper->insert($option);
				// Add the stored option to the collection of added options
				$addedOptions[] = $option->read();
			} catch (IMapperException $e) {
				$this->logger->error("Failed to add option: {$e->getMessage()}");
				// Optionally handle the error, e.g., by continuing to the next iteration or returning an error response
			}
		}

		$this->formMapper->update($form);

		return new DataResponse($addedOptions);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Writes the given key-value pairs into Database.
	 *
	 * @param int $formId id of form
	 * @param int $questionId id of question
	 * @param int $optionId id of option to update
	 * @param array $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function updateOption(int $formId, int $questionId, int $optionId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating option: form: {formId}, question: {questionId}, option: {optionId}, values: {keyValuePairs}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'optionId' => $optionId,
			'keyValuePairs' => $keyValuePairs
		]);

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		try {
			$option = $this->optionMapper->findById($optionId);
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find option or question');
			throw new OCSBadRequestException('Could not find option or question');
		}

		if ($option->getQuestionId() !== $questionId || $question->getFormId() !== $formId) {
			$this->logger->debug('The given option id doesn\'t match the question or form.');
			throw new OCSBadRequestException();
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
		$option->setId($optionId);

		// Update changed Columns in Db.
		$this->optionMapper->update($option);

		$this->formMapper->update($form);

		return new DataResponse($option->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete an option
	 *
	 * @param int $formId id of form
	 * @param int $questionId id of question
	 * @param int $optionId id of option to update
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteOption(int $formId, int $questionId, int $optionId): DataResponse {
		$this->logger->debug('Deleting option: {optionId}', [
			'optionId' => $optionId
		]);

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		try {
			$option = $this->optionMapper->findById($optionId);
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form, question or option');
			throw new OCSBadRequestException('Could not find form, question or option');
		}

		if ($option->getQuestionId() !== $questionId || $question->getFormId() !== $formId) {
			$this->logger->debug('The given option id doesn\'t match the question or form.');
			throw new OCSBadRequestException();
		}

		$this->optionMapper->delete($option);

		// Reorder the remaining options
		$options = array_values($this->optionMapper->findByQuestion($questionId));
		foreach ($options as $order => $option) {
			// Always start order with 1
			$option->setOrder($order + 1);
			$this->optionMapper->update($option);
		}

		$this->formMapper->update($form);

		return new DataResponse($optionId);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Reorder options for a given question
	 * @param int $formId id of form
	 * @param int $questionId id of question
	 * @param Array<int, int> $newOrder Order to use
	 */
	public function reorderOptions(int $formId, int $questionId, array $newOrder) {
		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question', ['exception' => $e]);
			throw new OCSNotFoundException('Could not find form or question');
		}

		if ($question->getFormId() !== $formId) {
			$this->logger->debug('The given question id doesn\'t match the form.');
			throw new OCSBadRequestException();
		}

		// Check if array contains duplicates
		if (array_unique($newOrder) !== $newOrder) {
			$this->logger->debug('The given array contains duplicates');
			throw new OCSBadRequestException('The given array contains duplicates');
		}

		$options = $this->optionMapper->findByQuestion($questionId);

		if (sizeof($options) !== sizeof($newOrder)) {
			$this->logger->debug('The length of the given array does not match the number of stored options');
			throw new OCSBadRequestException('The length of the given array does not match the number of stored options');
		}

		$options = []; // Clear Array of Entities
		$response = []; // Array of ['optionId' => ['order' => newOrder]]

		// Store array of Option entities and check the Options questionId & old order.
		foreach ($newOrder as $arrayKey => $optionId) {
			try {
				$options[$arrayKey] = $this->optionMapper->findById($optionId);
			} catch (IMapperException $e) {
				$this->logger->debug('Could not find option. Id: {optionId}', [
					'optionId' => $optionId
				]);
				throw new OCSBadRequestException();
			}

			// Abort if a question is not part of the Form.
			if ($options[$arrayKey]->getQuestionId() !== $questionId) {
				$this->logger->debug('This Option is not part of the given Question: formId: {formId}', [
					'formId' => $formId
				]);
				throw new OCSBadRequestException();
			}

			// Abort if a question is already marked as deleted (order==0)
			$oldOrder = $options[$arrayKey]->getOrder();

			// Only set order, if it changed.
			if ($oldOrder !== $arrayKey + 1) {
				// Set Order. ArrayKey counts from zero, order counts from 1.
				$options[$arrayKey]->setOrder($arrayKey + 1);
			}
		}

		// Write to Database
		foreach ($options as $option) {
			$this->optionMapper->update($option);

			$response[$option->getId()] = [
				'order' => $option->getOrder()
			];
		}

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	// Submissions

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Get all the submissions of a given form
	 *
	 * @param int $formId of the form
	 * @return DataResponse|DataDownloadResponse
	 * @throws OCSNotFoundException
	 * @throws OCSForbiddenException
	 */
	public function getSubmissions(int $formId, ?string $fileFormat = null): DataResponse|DataDownloadResponse {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if (!$this->formsService->canSeeResults($form)) {
			$this->logger->debug('The current user has no permission to get the results for this form');
			throw new OCSForbiddenException();
		}

		if ($fileFormat !== null) {
			$submissionsData = $this->submissionService->getSubmissionsData($form, $fileFormat);
			$fileName = $this->formsService->getFileName($form, $fileFormat);

			return new DataDownloadResponse($submissionsData, $fileName, Constants::SUPPORTED_EXPORT_FORMATS[$fileFormat]);
		}

		// Load submissions and currently active questions
		$submissions = $this->submissionService->getSubmissions($formId);
		$questions = $this->formsService->getQuestions($formId);

		// Append Display Names
		foreach ($submissions as $key => $submission) {
			if (substr($submission['userId'], 0, 10) === 'anon-user-') {
				// Anonymous User
				// TRANSLATORS On Results when listing the single Responses to the form, this text is shown as heading of the Response.
				$submissions[$key]['userDisplayName'] = $this->l10n->t('Anonymous response');
			} else {
				$userEntity = $this->userManager->get($submission['userId']);

				if ($userEntity instanceof IUser) {
					$submissions[$key]['userDisplayName'] = $userEntity->getDisplayName();
				} else {
					// Fallback, should not occur regularly.
					$submissions[$key]['userDisplayName'] = $submission['userId'];
				}
			}
		}

		$response = [
			'submissions' => $submissions,
			'questions' => $questions,
		];

		return new DataResponse($response);
	}

	/**
	 * @CORS
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

		// The current user has permissions to remove submissions
		if (!$this->formsService->canDeleteResults($form)) {
			$this->logger->debug('This form is not owned by the current user and user has no `results_delete` permission');
			throw new OCSForbiddenException();
		}

		// Delete all submissions (incl. Answers)
		$this->submissionMapper->deleteByForm($formId);
		$this->formMapper->update($form);

		return new DataResponse($formId);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Process a new submission
	 *
	 * @param int $formId the form id
	 * @param array $answers [question_id => arrayOfString]
	 * @param string $shareHash public share-hash -> Necessary to submit on public link-shares.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newSubmission(int $formId, array $answers, string $shareHash = ''): DataResponse {
		$this->logger->debug('Inserting submission: formId: {formId}, answers: {answers}, shareHash: {shareHash}', [
			'formId' => $formId,
			'answers' => $answers,
			'shareHash' => $shareHash,
		]);

		$form = $this->loadFormForSubmission($formId, $shareHash);

		$questions = $this->formsService->getQuestions($formId);
		// Is the submission valid
		$isSubmissionValid = $this->submissionService->validateSubmission($questions, $answers, $form->getOwnerId());
		if (is_string($isSubmissionValid)) {
			throw new OCSBadRequestException($isSubmissionValid);
		}
		if ($isSubmissionValid === false) {
			throw new OCSBadRequestException('At least one submitted answer is not valid');
		}

		// Create Submission
		$submission = new Submission();
		$submission->setFormId($formId);
		$submission->setTimestamp(time());

		// If not logged in, anonymous, or embedded use anonID
		if (!$this->currentUser || $form->getIsAnonymous()) {
			$anonID = 'anon-user-' . hash('md5', strval(time() + rand()));
			$submission->setUserId($anonID);
		} else {
			$submission->setUserId($this->currentUser->getUID());
		}

		// Does the user have permissions to submit
		// This is done right before insert so we minimize race conditions for submitting on unique-submission forms
		if (!$this->formsService->canSubmit($form)) {
			throw new OCSForbiddenException('Already submitted');
		}

		// Insert new submission
		$this->submissionMapper->insert($submission);

		// Ensure the form is unique if needed.
		// If we can not submit anymore then the submission must be unique
		if (!$this->formsService->canSubmit($form) && $this->submissionMapper->hasMultipleFormSubmissionsByUser($form, $submission->getUserId())) {
			$this->submissionMapper->delete($submission);
			throw new OCSForbiddenException('Already submitted');
		}

		// Process Answers
		foreach ($answers as $questionId => $answerArray) {
			// Search corresponding Question, skip processing if not found
			$questionIndex = array_search($questionId, array_column($questions, 'id'));
			if ($questionIndex === false) {
				continue;
			}

			$this->storeAnswersForQuestion($form, $submission->getId(), $questions[$questionIndex], $answerArray);
		}

		$this->formMapper->update($form);

		//Create Activity
		$this->formsService->notifyNewSubmission($form, $submission);

		if ($form->getFileId() !== null) {
			$this->jobList->add(SyncSubmissionsWithLinkedFileJob::class, ['form_id' => $form->getId()]);
		}

		return new DataResponse();
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a specific submission
	 *
	 * @param int $formId the form id
	 * @param int $submissionId the submission id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteSubmission(int $formId, int $submissionId): DataResponse {
		$this->logger->debug('Delete Submission: {submissionId}', [
			'submissionId' => $submissionId,
		]);

		try {
			$submission = $this->submissionMapper->findById($submissionId);
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or submission');
			throw new OCSBadRequestException();
		}

		if ($formId !== $submission->getFormId()) {
			$this->logger->debug('Submission doesn\'t belong to given form');
			throw new OCSBadRequestException('Submission doesn\'t belong to given form');
		}

		// The current user has permissions to remove submissions
		if (!$this->formsService->canDeleteResults($form)) {
			$this->logger->debug('This form is not owned by the current user and user has no `results_delete` permission');
			throw new OCSForbiddenException();
		}

		// Delete submission (incl. Answers)
		$this->submissionMapper->deleteById($submissionId);
		$this->formMapper->update($form);

		return new DataResponse($submissionId);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Export Submissions to the Cloud
	 *
	 * @param int $formId of the form
	 * @param string $path The Cloud-Path to export to
	 * @param string $fileFormat File format used for export
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function exportSubmissionsToCloud(int $formId, string $path, string $fileFormat = Constants::DEFAULT_FILE_FORMAT) {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if (!$this->formsService->canSeeResults($form)) {
			$this->logger->debug('The current user has no permission to get the results for this form');
			throw new OCSForbiddenException();
		}

		$file = $this->submissionService->writeFileToCloud($form, $path, $fileFormat);

		return new DataResponse($file->getName());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * Uploads a temporary files to the server during form filling
	 *
	 * @param int $formId id of the form
	 * @param int $questionId id of the question
	 * @param string $shareHash hash of the form share
	 * @return Response
	 */
	public function uploadFiles(int $formId, int $questionId, string $shareHash = ''): Response {
		$this->logger->debug('Uploading files for formId: {formId}, questionId: {questionId}', [
			'formId' => $formId,
			'questionId' => $questionId
		]);

		$uploadedFiles = [];
		foreach ($this->request->getUploadedFile('files') as $key => $files) {
			foreach ($files as $i => $value) {
				$uploadedFiles[$i][$key] = $value;
			}
		}

		if (!count($uploadedFiles)) {
			throw new OCSBadRequestException('No files provided');
		}

		$form = $this->loadFormForSubmission($formId, $shareHash);

		if (!$this->formsService->canSubmit($form)) {
			throw new OCSForbiddenException('Already submitted');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question with id {questionId}', [
				'questionId' => $questionId
			]);
			throw new OCSBadRequestException(previous: $e instanceof \Exception ? $e : null);
		}

		if ($formId !== $question->getFormId()) {
			$this->logger->debug('Question doesn\'t belong to the given form');
			throw new OCSBadRequestException('Question doesn\'t belong to the given form');
		}

		$path = $this->formsService->getTemporaryUploadedFilePath($form, $question);

		$response = [];
		foreach ($uploadedFiles as $uploadedFile) {
			$error = $uploadedFile['error'] ?? 0;
			if ($error !== UPLOAD_ERR_OK) {
				$this->logger->error(
					'Failed to get the uploaded file. PHP file upload error code: ' . $error,
					['file_name' => $uploadedFile['name']]
				);

				throw new OCSBadRequestException(sprintf('Failed to upload the file "%s".', $uploadedFile['name']));
			}

			if (!is_uploaded_file($uploadedFile['tmp_name'])) {
				throw new OCSBadRequestException('Invalid file provided');
			}

			$userFolder = $this->rootFolder->getUserFolder($form->getOwnerId());
			$userFolder->getStorage()->verifyPath($path, $uploadedFile['name']);

			$extraSettings = $question->getExtraSettings();
			if (($extraSettings['maxFileSize'] ?? 0) > 0 && $uploadedFile['size'] > $extraSettings['maxFileSize']) {
				throw new OCSBadRequestException(sprintf('File size exceeds the maximum allowed size of %s bytes.', $extraSettings['maxFileSize']));
			}

			if (!empty($extraSettings['allowedFileTypes']) || !empty($extraSettings['allowedFileExtensions'])) {
				$mimeType = $this->mimeTypeDetector->detectContent($uploadedFile['tmp_name']);
				$aliases = $this->mimeTypeDetector->getAllAliases();

				$valid = false;
				foreach ($extraSettings['allowedFileTypes'] ?? [] as $allowedFileType) {
					if (str_starts_with($mimeType, $allowedFileType) || str_starts_with($aliases[$mimeType] ?? '', $allowedFileType)) {
						$valid = true;
						break;
					}
				}

				if (!$valid && !empty($extraSettings['allowedFileExtensions'])) {
					$mimeTypesPerExtension = method_exists($this->mimeTypeDetector, 'getAllMappings')
						? $this->mimeTypeDetector->getAllMappings() : [];
					foreach ($extraSettings['allowedFileExtensions'] as $allowedFileExtension) {
						if (
							isset($mimeTypesPerExtension[$allowedFileExtension])
							&& in_array($mimeType, $mimeTypesPerExtension[$allowedFileExtension])
						) {
							$valid = true;
							break;
						}
					}
				}

				if (!$valid) {
					throw new OCSBadRequestException(sprintf(
						'File type is not allowed. Allowed file types: %s',
						implode(', ', array_merge($extraSettings['allowedFileTypes'] ?? [], $extraSettings['allowedFileExtensions'] ?? []))
					));
				}
			}

			if ($userFolder->nodeExists($path)) {
				$folder = $userFolder->get($path);
			} else {
				$folder = $userFolder->newFolder($path);
			}
			/** @var \OCP\Files\Folder $folder */

			$fileName = $folder->getNonExistingName($uploadedFile['name']);
			$file = $folder->newFile($fileName, file_get_contents($uploadedFile['tmp_name']));

			$uploadedFileEntity = new UploadedFile();
			$uploadedFileEntity->setFormId($formId);
			$uploadedFileEntity->setOriginalFileName($fileName);
			$uploadedFileEntity->setFileId($file->getId());
			$uploadedFileEntity->setCreated(time());
			$this->uploadedFileMapper->insert($uploadedFileEntity);

			$response[] = [
				'uploadedFileId' => $uploadedFileEntity->getId(),
				'fileName' => $fileName,
			];
		}

		return new DataResponse($response);
	}

	/*
	 *
	 * Legacy API v2 methods (TODO: remove with Forms v5)
	 *
	 */

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read Form-List of owned forms
	 * Return only with necessary information for Listing.
	 * @return DataResponse
	 */
	public function getFormsLegacy(): DataResponse {
		$forms = $this->formMapper->findAllByOwnerId($this->currentUser->getUID());

		$result = [];
		foreach ($forms as $form) {
			$result[] = $this->formsService->getPartialFormArray($form);
		}

		return new DataResponse($result);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read List of forms shared with current user
	 * Return only with necessary information for Listing.
	 * @return DataResponse
	 */
	public function getSharedFormsLegacy(): DataResponse {
		$forms = $this->formsService->getSharedForms($this->currentUser);
		$result = array_values(array_map(fn (Form $form): array => $this->formsService->getPartialFormArray($form), $forms));

		return new DataResponse($result);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Get a partial form by its hash. Implicitely checks, if the user has access.
	 *
	 * @param string $hash The form hash
	 * @return DataResponse
	 * @throws OCSBadRequestException if forbidden or not found
	 */
	public function getPartialFormLegacy(string $hash): DataResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		return new DataResponse($this->formsService->getPartialFormArray($form));
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Read all information to edit a Form (form, questions, options, except submissions/answers).
	 *
	 * @param int $id FormId
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getFormLegacy(int $id): DataResponse {
		try {
			$form = $this->formMapper->findById($id);
			$formData = $this->formsService->getForm($form);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException();
		}

		return new DataResponse($formData);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Create a new Form and return the Form to edit.
	 *
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function newFormLegacy(): DataResponse {
		// Check if user is allowed
		if (!$this->configService->canCreateForms()) {
			$this->logger->debug('This user is not allowed to create Forms.');
			throw new OCSForbiddenException();
		}

		// Create Form
		$form = new Form();
		$form->setOwnerId($this->currentUser->getUID());
		$form->setHash($this->formsService->generateFormHash());
		$form->setTitle('');
		$form->setDescription('');
		$form->setAccess([
			'permitAllUsers' => false,
			'showToAllUsers' => false,
		]);
		$form->setSubmitMultiple(false);
		$form->setShowExpiration(false);
		$form->setExpires(0);
		$form->setIsAnonymous(false);

		$this->formMapper->insert($form);

		// Return like getForm()
		return $this->getForm($form->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Clones a form
	 *
	 * @param int $id FormId of the form to clone
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function cloneFormLegacy(int $id): DataResponse {
		$this->logger->debug('Cloning Form: {id}', [
			'id' => $id
		]);

		// Check if user can create forms
		if (!$this->configService->canCreateForms()) {
			$this->logger->debug('This user is not allowed to create Forms.');
			throw new OCSForbiddenException();
		}

		$oldForm = $this->getFormIfAllowed($id);

		// Read Form, set new Form specific data, extend Title.
		$formData = $oldForm->read();
		unset($formData['id']);
		unset($formData['fileId']);
		unset($formData['fileFormat']);
		$formData['created'] = time();
		$formData['lastUpdated'] = time();
		$formData['hash'] = $this->formsService->generateFormHash();
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
	 * @CORS
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
	public function updateFormLegacy(int $id, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating form: FormId: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		$form = $this->getFormIfAllowed($id);

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException();
		}

		// Don't allow to change params id, hash, ownerId, created, lastUpdated
		if (
			key_exists('id', $keyValuePairs) || key_exists('hash', $keyValuePairs) ||
			key_exists('ownerId', $keyValuePairs) || key_exists('created', $keyValuePairs) ||
			key_exists('lastUpdated', $keyValuePairs)
		) {
			$this->logger->info('Not allowed to update id, hash, ownerId or created');
			throw new OCSForbiddenException();
		}

		// Do not allow changing showToAllUsers if disabled
		if (isset($keyValuePairs['access'])) {
			$showAll = $keyValuePairs['access']['showToAllUsers'] ?? false;
			$permitAll = $keyValuePairs['access']['permitAllUsers'] ?? false;
			if (($showAll && !$this->configService->getAllowShowToAll())
				|| ($permitAll && !$this->configService->getAllowPermitAll())) {
				$this->logger->info('Not allowed to update showToAllUsers or permitAllUsers');
				throw new OCSForbiddenException();
			}
		}

		// Create FormEntity with given Params & Id.
		foreach ($keyValuePairs as $key => $value) {
			$method = 'set' . ucfirst($key);
			$form->$method($value);
		}

		// Update changed Columns in Db.
		$this->formMapper->update($form);

		return new DataResponse($form->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Transfer ownership of a form to another user
	 *
	 * @param int $formId id of the form to update
	 * @param string $uid id of the new owner
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function transferOwnerLegacy(int $formId, string $uid): DataResponse {
		$this->logger->debug('Updating owner: formId: {formId}, userId: {uid}', [
			'formId' => $formId,
			'uid' => $uid
		]);

		$form = $this->getFormIfAllowed($formId);
		$user = $this->userManager->get($uid);
		if ($user == null) {
			$this->logger->debug('Could not find new form owner');
			throw new OCSBadRequestException('Could not find new form owner');
		}

		// update form owner
		$form->setOwnerId($uid);

		// Update changed Columns in Db.
		$this->formMapper->update($form);

		return new DataResponse($form->getOwnerId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a form
	 *
	 * @param int $id the form id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteFormLegacy(int $id): DataResponse {
		$this->logger->debug('Delete Form: {id}', [
			'id' => $id,
		]);

		$form = $this->getFormIfAllowed($id);
		$this->formMapper->deleteForm($form);

		return new DataResponse($id);
	}

	/**
	 * @CORS
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
	public function newQuestionLegacy(int $formId, string $type, string $text = ''): DataResponse {
		$this->logger->debug('Adding new question: formId: {formId}, type: {type}, text: {text}', [
			'formId' => $formId,
			'type' => $type,
			'text' => $text,
		]);

		if (array_search($type, Constants::ANSWER_TYPES) === false) {
			$this->logger->debug('Invalid type');
			throw new OCSBadRequestException('Invalid type');
		}

		// Block creation of datetime questions
		if ($type === 'datetime') {
			$this->logger->debug('Datetime question type no longer supported');
			throw new OCSBadRequestException('Datetime question type no longer supported');
		}

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
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
		$question->setDescription('');
		$question->setIsRequired(false);
		$question->setExtraSettings([]);

		$question = $this->questionMapper->insert($question);

		$response = $question->read();
		$response['options'] = [];
		$response['accept'] = [];

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Updates the Order of all Questions of a Form.
	 *
	 * @param int $formId Id of the form to reorder
	 * @param Array<int, int> $newOrder Array of Question-Ids in new order.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function reorderQuestionsLegacy(int $formId, array $newOrder): DataResponse {
		$this->logger->debug('Reordering Questions on Form {formId} as Question-Ids {newOrder}', [
			'formId' => $formId,
			'newOrder' => $newOrder
		]);

		$form = $this->getFormIfAllowed($formId);
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
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

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	/**
	 * @CORS
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
	public function updateQuestionLegacy(int $id, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating question: questionId: {id}, values: {keyValuePairs}', [
			'id' => $id,
			'keyValuePairs' => $keyValuePairs
		]);

		try {
			$question = $this->questionMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSBadRequestException('Could not find question');
		}

		$form = $this->getFormIfAllowed($question->getFormId());
		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
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

		if (key_exists('extraSettings', $keyValuePairs) && !$this->formsService->areExtraSettingsValid($keyValuePairs['extraSettings'], $question->getType())) {
			throw new OCSBadRequestException('Invalid extraSettings, will not update.');
		}

		// Create QuestionEntity with given Params & Id.
		$question = Question::fromParams($keyValuePairs);
		$question->setId($id);

		// Update changed Columns in Db.
		$this->questionMapper->update($question);
		$this->formMapper->update($form);

		return new DataResponse($question->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a question
	 *
	 * @param int $id the question id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteQuestionLegacy(int $id): DataResponse {
		$this->logger->debug('Mark question as deleted: {id}', [
			'id' => $id,
		]);

		try {
			$question = $this->questionMapper->findById($id);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSBadRequestException('Could not find question');
		}

		$form = $this->getFormIfAllowed($question->getFormId());

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
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

		$this->formMapper->update($form);

		return new DataResponse($id);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Clone a question
	 *
	 * @param int $id the question id
	 * @return DataResponse
	 * @throws OCSBadRequestException|OCSForbiddenException
	 */
	public function cloneQuestionLegacy(int $id): DataResponse {
		$this->logger->debug('Question to be cloned: {id}', [
			'id' => $id
		]);

		try {
			$sourceQuestion = $this->questionMapper->findById($id);
			$sourceOptions = $this->optionMapper->findByQuestion($id);
			$form = $this->formMapper->findById($sourceQuestion->getFormId());
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form or question');
			throw new OCSNotFoundException('Could not find form or question');
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		$allQuestions = $this->questionMapper->findByForm($form->getId());

		$questionData = $sourceQuestion->read();
		unset($questionData['id']);
		$questionData['order'] = end($allQuestions)->getOrder() + 1;

		$newQuestion = Question::fromParams($questionData);
		$this->questionMapper->insert($newQuestion);

		$response = $newQuestion->read();
		$response['options'] = [];
		$response['accept'] = [];

		foreach ($sourceOptions as $sourceOption) {
			$optionData = $sourceOption->read();

			unset($optionData['id']);
			$optionData['questionId'] = $newQuestion->getId();
			$newOption = Option::fromParams($optionData);
			$insertedOption = $this->optionMapper->insert($newOption);

			$response['options'][] = $insertedOption->read();
		}

		return new DataResponse($response);
	}

	/**
	 * @CORS
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
	public function newOptionLegacy(int $questionId, string $text): DataResponse {
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

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		// Retrieve all options sorted by 'order'. Takes the order of the last array-element and adds one.
		$options = $this->optionMapper->findByQuestion($questionId);
		$lastOption = array_pop($options);
		if ($lastOption) {
			$optionOrder = $lastOption->getOrder() + 1;
		} else {
			$optionOrder = 1;
		}

		$option = new Option();

		$option->setQuestionId($questionId);
		$option->setText($text);
		$option->setOrder($optionOrder);

		$option = $this->optionMapper->insert($option);
		$this->formMapper->update($form);

		return new DataResponse($option->read());
	}

	/**
	 * @CORS
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
	public function updateOptionLegacy(int $id, array $keyValuePairs): DataResponse {
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

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
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
		$this->formMapper->update($form);

		return new DataResponse($option->getId());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete an option
	 *
	 * @param int $id the option id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteOptionLegacy(int $id): DataResponse {
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

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException();
		}

		$this->optionMapper->delete($option);

		// Reorder the remaining options
		$options = array_values($this->optionMapper->findByQuestion($option->getQuestionId()));
		foreach ($options as $order => $option) {
			// Always start order with 1
			$option->setOrder($order + 1);
			$this->optionMapper->update($option);
		}

		$this->formMapper->update($form);

		return new DataResponse($id);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Get all the submissions of a given form
	 *
	 * @param string $hash the form hash
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function getSubmissionsLegacy(string $hash): DataResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		if (!$this->formsService->canSeeResults($form)) {
			$this->logger->debug('The current user has no permission to get the results for this form');
			throw new OCSForbiddenException();
		}

		// Load submissions and currently active questions
		$submissions = $this->submissionService->getSubmissions($form->getId());
		$questions = $this->formsService->getQuestions($form->getId());

		// Append Display Names
		foreach ($submissions as $key => $submission) {
			if (substr($submission['userId'], 0, 10) === 'anon-user-') {
				// Anonymous User
				// TRANSLATORS On Results when listing the single Responses to the form, this text is shown as heading of the Response.
				$submissions[$key]['userDisplayName'] = $this->l10n->t('Anonymous response');
			} else {
				$userEntity = $this->userManager->get($submission['userId']);

				if ($userEntity instanceof IUser) {
					$submissions[$key]['userDisplayName'] = $userEntity->getDisplayName();
				} else {
					// Fallback, should not occur regularly.
					$submissions[$key]['userDisplayName'] = $submission['userId'];
				}
			}
		}

		$response = [
			'submissions' => $submissions,
			'questions' => $questions,
		];

		return new DataResponse($response);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 * @PublicPage
	 *
	 * Uploads a temporary files to the server during form filling
	 *
	 * @return Response
	 */
	public function uploadFilesLegacy(int $formId, int $questionId, string $shareHash = ''): Response {
		$this->logger->debug(
			'Uploading files for formId: {formId}, questionId: {questionId}',
			['formId' => $formId, 'questionId' => $questionId]
		);

		$uploadedFiles = [];
		foreach ($this->request->getUploadedFile('files') as $key => $files) {
			foreach ($files as $i => $value) {
				$uploadedFiles[$i][$key] = $value;
			}
		}

		if (!count($uploadedFiles)) {
			throw new OCSBadRequestException('No files provided');
		}

		$form = $this->loadFormForSubmission($formId, $shareHash);

		if (!$this->formsService->canSubmit($form)) {
			throw new OCSForbiddenException('Already submitted');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question with id {questionId}', ['questionId' => $questionId]);
			throw new OCSBadRequestException(previous: $e instanceof \Exception ? $e : null);
		}

		$path = $this->formsService->getTemporaryUploadedFilePath($form, $question);

		$response = [];
		foreach ($uploadedFiles as $uploadedFile) {
			$error = $uploadedFile['error'] ?? 0;
			if ($error !== UPLOAD_ERR_OK) {
				$this->logger->error(
					'Failed to get the uploaded file. PHP file upload error code: ' . $error,
					['file_name' => $uploadedFile['name']]
				);

				throw new OCSBadRequestException(sprintf('Failed to upload the file "%s".', $uploadedFile['name']));
			}

			if (!is_uploaded_file($uploadedFile['tmp_name'])) {
				throw new OCSBadRequestException('Invalid file provided');
			}

			$userFolder = $this->rootFolder->getUserFolder($form->getOwnerId());
			$userFolder->getStorage()->verifyPath($path, $uploadedFile['name']);

			$extraSettings = $question->getExtraSettings();
			if (($extraSettings['maxFileSize'] ?? 0) > 0 && $uploadedFile['size'] > $extraSettings['maxFileSize']) {
				throw new OCSBadRequestException(sprintf('File size exceeds the maximum allowed size of %s bytes.', $extraSettings['maxFileSize']));
			}

			if (!empty($extraSettings['allowedFileTypes']) || !empty($extraSettings['allowedFileExtensions'])) {
				$mimeType = $this->mimeTypeDetector->detectContent($uploadedFile['tmp_name']);
				$aliases = $this->mimeTypeDetector->getAllAliases();

				$valid = false;
				foreach ($extraSettings['allowedFileTypes'] ?? [] as $allowedFileType) {
					if (str_starts_with($mimeType, $allowedFileType) || str_starts_with($aliases[$mimeType] ?? '', $allowedFileType)) {
						$valid = true;
						break;
					}
				}

				if (!$valid && !empty($extraSettings['allowedFileExtensions'])) {
					$mimeTypesPerExtension = method_exists($this->mimeTypeDetector, 'getAllMappings')
						? $this->mimeTypeDetector->getAllMappings() : [];
					foreach ($extraSettings['allowedFileExtensions'] as $allowedFileExtension) {
						if (
							isset($mimeTypesPerExtension[$allowedFileExtension])
							&& in_array($mimeType, $mimeTypesPerExtension[$allowedFileExtension])
						) {
							$valid = true;
							break;
						}
					}
				}

				if (!$valid) {
					throw new OCSBadRequestException(sprintf(
						'File type is not allowed. Allowed file types: %s',
						implode(', ', array_merge($extraSettings['allowedFileTypes'] ?? [], $extraSettings['allowedFileExtensions'] ?? []))
					));
				}
			}

			if ($userFolder->nodeExists($path)) {
				$folder = $userFolder->get($path);
			} else {
				$folder = $userFolder->newFolder($path);
			}
			/** @var \OCP\Files\Folder $folder */

			$fileName = $folder->getNonExistingName($uploadedFile['name']);
			$file = $folder->newFile($fileName, file_get_contents($uploadedFile['tmp_name']));

			$uploadedFileEntity = new UploadedFile();
			$uploadedFileEntity->setFormId($formId);
			$uploadedFileEntity->setOriginalFileName($fileName);
			$uploadedFileEntity->setFileId($file->getId());
			$uploadedFileEntity->setCreated(time());
			$this->uploadedFileMapper->insert($uploadedFileEntity);

			$response[] = [
				'uploadedFileId' => $uploadedFileEntity->getId(),
				'fileName' => $fileName,
			];
		}

		return new DataResponse($response);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * Process a new submission
	 *
	 * @param int $formId the form id
	 * @param array $answers [question_id => arrayOfString]
	 * @param string $shareHash public share-hash -> Necessary to submit on public link-shares.
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function insertSubmissionLegacy(int $formId, array $answers, string $shareHash = ''): DataResponse {
		$this->logger->debug('Inserting submission: formId: {formId}, answers: {answers}, shareHash: {shareHash}', [
			'formId' => $formId,
			'answers' => $answers,
			'shareHash' => $shareHash,
		]);

		$form = $this->loadFormForSubmission($formId, $shareHash);

		$questions = $this->formsService->getQuestions($formId);
		// Is the submission valid
		$isSubmissionValid = $this->submissionService->validateSubmission($questions, $answers, $form->getOwnerId());
		if (is_string($isSubmissionValid)) {
			throw new OCSBadRequestException($isSubmissionValid);
		}
		if ($isSubmissionValid === false) {
			throw new OCSBadRequestException('At least one submitted answer is not valid');
		}

		// Create Submission
		$submission = new Submission();
		$submission->setFormId($formId);
		$submission->setTimestamp(time());

		// If not logged in, anonymous, or embedded use anonID
		if (!$this->currentUser || $form->getIsAnonymous()) {
			$anonID = 'anon-user-' . hash('md5', strval(time() + rand()));
			$submission->setUserId($anonID);
		} else {
			$submission->setUserId($this->currentUser->getUID());
		}

		// Does the user have permissions to submit
		// This is done right before insert so we minimize race conditions for submitting on unique-submission forms
		if (!$this->formsService->canSubmit($form)) {
			throw new OCSForbiddenException('Already submitted');
		}

		// Insert new submission
		$this->submissionMapper->insert($submission);

		// Ensure the form is unique if needed.
		// If we can not submit anymore then the submission must be unique
		if (!$this->formsService->canSubmit($form) && $this->submissionMapper->hasMultipleFormSubmissionsByUser($form, $submission->getUserId())) {
			$this->submissionMapper->delete($submission);
			throw new OCSForbiddenException('Already submitted');
		}

		// Process Answers
		foreach ($answers as $questionId => $answerArray) {
			// Search corresponding Question, skip processing if not found
			$questionIndex = array_search($questionId, array_column($questions, 'id'));
			if ($questionIndex === false) {
				continue;
			}

			$this->storeAnswersForQuestion($form, $submission->getId(), $questions[$questionIndex], $answerArray);
		}

		$this->formMapper->update($form);

		//Create Activity
		$this->formsService->notifyNewSubmission($form, $submission);

		if ($form->getFileId() !== null) {
			$this->jobList->add(SyncSubmissionsWithLinkedFileJob::class, ['form_id' => $form->getId()]);
		}

		return new DataResponse();
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete a specific submission
	 *
	 * @param int $id the submission id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteSubmissionLegacy(int $id): DataResponse {
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

		// The current user has permissions to remove submissions
		if (!$this->formsService->canDeleteResults($form)) {
			$this->logger->debug('This form is not owned by the current user and user has no `results_delete` permission');
			throw new OCSForbiddenException();
		}

		// Delete submission (incl. Answers)
		$this->submissionMapper->deleteById($id);
		$this->formMapper->update($form);

		return new DataResponse($id);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Delete all submissions of a specified form
	 *
	 * @param int $formId the form id
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function deleteAllSubmissionsLegacy(int $formId): DataResponse {
		$this->logger->debug('Delete all submissions to form: {formId}', [
			'formId' => $formId,
		]);

		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException();
		}

		// The current user has permissions to remove submissions
		if (!$this->formsService->canDeleteResults($form)) {
			$this->logger->debug('This form is not owned by the current user and user has no `results_delete` permission');
			throw new OCSForbiddenException();
		}

		// Delete all submissions (incl. Answers)
		$this->submissionMapper->deleteByForm($formId);
		$this->formMapper->update($form);

		return new DataResponse($formId);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Export submissions of a specified form
	 *
	 * @param string $hash the form hash
	 * @param string $fileFormat File format used for export
	 * @return DataDownloadResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function exportSubmissionsLegacy(string $hash, string $fileFormat = Constants::DEFAULT_FILE_FORMAT): DataDownloadResponse {
		$this->logger->debug('Export submissions for form: {hash}', [
			'hash' => $hash,
		]);

		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if (!$this->formsService->canSeeResults($form)) {
			$this->logger->debug('The current user has no permission to get the results for this form');
			throw new OCSForbiddenException();
		}

		$submissionsData = $this->submissionService->getSubmissionsData($form, $fileFormat);
		$fileName = $this->formsService->getFileName($form, $fileFormat);

		return new DataDownloadResponse($submissionsData, $fileName, Constants::SUPPORTED_EXPORT_FORMATS[$fileFormat]);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Export Submissions to the Cloud
	 *
	 * @param string $hash of the form
	 * @param string $path The Cloud-Path to export to
	 * @param string $fileFormat File format used for export
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function exportSubmissionsToCloudLegacy(string $hash, string $path, string $fileFormat = Constants::DEFAULT_FILE_FORMAT) {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if (!$this->formsService->canSeeResults($form)) {
			$this->logger->debug('The current user has no permission to get the results for this form');
			throw new OCSForbiddenException();
		}

		$file = $this->submissionService->writeFileToCloud($form, $path, $fileFormat);

		return new DataResponse($file->getName());
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * @param string $hash of the form
	 */
	public function unlinkFileLegacy(string $hash): DataResponse {
		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if (!$this->formsService->canEditForm($form)) {
			$this->logger->debug('User has no permissions to unlink this form from files');
			throw new OCSForbiddenException();
		}

		if (!$form->getFileId()) {
			$this->logger->debug('Form not linked to file');
			throw new OCSBadRequestException();
		}

		$form->setFileId(null);
		$form->setFileFormat(null);

		$this->formMapper->update($form);

		return new DataResponse($hash);
	}

	/**
	 * @CORS
	 * @NoAdminRequired
	 *
	 * Export Submissions to the Cloud and Link the FileId to the form
	 *
	 * @param string $hash of the form
	 * @param string $path The Cloud-Path to export to
	 * @param string $fileFormat File format used for export
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 */
	public function linkFileLegacy(string $hash, string $path, string $fileFormat): DataResponse {
		$this->logger->debug('Linking form {hash} to file at /{path} in format {fileFormat}', [
			'hash' => $hash,
			'path' => $path,
			'fileFormat' => $fileFormat,
		]);

		try {
			$form = $this->formMapper->findByHash($hash);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if (!$this->formsService->canEditForm($form)) {
			$this->logger->debug('User has no permissions to link this form with files');
			throw new OCSForbiddenException();
		}

		$file = $this->submissionService->writeFileToCloud($form, $path, $fileFormat);

		$form->setFileId($file->getId());
		$form->setFileFormat($fileFormat);

		$this->formMapper->update($form);

		$filePath = $this->formsService->getFilePath($form);

		return new DataResponse([
			'fileId' => $file->getId(),
			'fileFormat' => $fileFormat,
			'fileName' => $file->getName(),
			'filePath' => $filePath,
		]);
	}

	// private functions

	/**
	 * Insert answers for a question
	 *
	 * @param Form $form
	 * @param int $submissionId
	 * @param array $question
	 * @param string[]|array<array{uploadedFileId: string, uploadedFileName: string}> $answerArray
	 */
	private function storeAnswersForQuestion(Form $form, $submissionId, array $question, array $answerArray) {
		foreach ($answerArray as $answer) {
			$answerEntity = new Answer();
			$answerEntity->setSubmissionId($submissionId);
			$answerEntity->setQuestionId($question['id']);

			$answerText = '';
			$uploadedFile = null;
			// Are we using answer ids as values
			if (in_array($question['type'], Constants::ANSWER_TYPES_PREDEFINED)) {
				// Search corresponding option, skip processing if not found
				$optionIndex = array_search($answer, array_column($question['options'], 'id'));
				if ($optionIndex !== false) {
					$answerText = $question['options'][$optionIndex]['text'];
				} elseif (!empty($question['extraSettings']['allowOtherAnswer']) && strpos($answer, Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX) === 0) {
					$answerText = str_replace(Constants::QUESTION_EXTRASETTINGS_OTHER_PREFIX, '', $answer);
				}
			} elseif ($question['type'] === Constants::ANSWER_TYPE_FILE) {
				$uploadedFile = $this->uploadedFileMapper->getByUploadedFileId($answer['uploadedFileId']);
				$answerEntity->setFileId($uploadedFile->getFileId());

				$userFolder = $this->rootFolder->getUserFolder($form->getOwnerId());
				$path = $this->formsService->getUploadedFilePath($form, $submissionId, $question['id'], $question['name'], $question['text']);

				if ($userFolder->nodeExists($path)) {
					$folder = $userFolder->get($path);
				} else {
					$folder = $userFolder->newFolder($path);
				}
				/** @var \OCP\Files\Folder $folder */

				$file = $userFolder->getById($uploadedFile->getFileId())[0];
				$name = $folder->getNonExistingName($file->getName());
				$file->move($folder->getPath() . '/' . $name);

				$answerText = $name;
			} else {
				$answerText = $answer; // Not a multiple-question, answerText is given answer
			}

			if ($answerText === '') {
				continue;
			}

			$answerEntity->setText($answerText);
			$this->answerMapper->insert($answerEntity);
			if ($uploadedFile) {
				$this->uploadedFileMapper->delete($uploadedFile);
			}
		}
	}

	private function loadFormForSubmission(int $formId, string $shareHash): Form {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSBadRequestException(previous: $e instanceof \Exception ? $e : null);
		}

		// Does the user have access to the form (Either by logged-in user, or by providing public share-hash.)
		try {
			$isPublicShare = false;

			// If hash given, find the corresponding share & check if hash corresponds to given formId.
			if ($shareHash !== '') {
				// public by legacy Link
				if (isset($form->getAccess()['legacyLink']) && $shareHash === $form->getHash()) {
					$isPublicShare = true;
				}

				// Public link share
				$share = $this->shareMapper->findPublicShareByHash($shareHash);
				if ($share->getFormId() === $formId) {
					$isPublicShare = true;
				}
			}
		} catch (DoesNotExistException $e) {
			// $isPublicShare already false.
		} finally {
			// Now forbid, if no public share and no direct share.
			if (!$isPublicShare && !$this->formsService->hasUserAccess($form)) {
				throw new OCSForbiddenException('Not allowed to access this form');
			}
		}

		// Not allowed if form has expired.
		if ($this->formsService->hasFormExpired($form)) {
			throw new OCSForbiddenException('This form is no longer taking answers');
		}

		return $form;
	}

	/**
	 * Helper that retrieves a form if the current user is allowed to edit it
	 * This throws an exception in case either the form is not found or permissions are missing.
	 * @param int $formId The form ID to retrieve
	 * @throws OCSNotFoundException If the form was not found
	 * @throws OCSForbiddenException If the current user has no permission to edit
	 */
	private function getFormIfAllowed(int $formId): Form {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException();
		}

		if ($form->getOwnerId() !== $this->currentUser->getUID()) {
			$this->logger->debug('This form is not owned by the current user');
			throw new OCSForbiddenException();
		}
		return $form;
	}
}
