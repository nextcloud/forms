<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
use OCA\Forms\ResponseDefinitions;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\CORS;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
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

/**
 * @psalm-import-type FormsForm from ResponseDefinitions
 * @psalm-import-type FormsOption from ResponseDefinitions
 * @psalm-import-type FormsOrder from ResponseDefinitions
 * @psalm-import-type FormsPartialForm from ResponseDefinitions
 * @psalm-import-type FormsQuestion from ResponseDefinitions
 * @psalm-import-type FormsQuestionType from ResponseDefinitions
 * @psalm-import-type FormsSubmission from ResponseDefinitions
 * @psalm-import-type FormsSubmissions from ResponseDefinitions
 * @psalm-import-type FormsUploadedFile from ResponseDefinitions
 */
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

	// CORS preflight
	/**
	 * Handle CORS options request by calling parent function
	 *
	 * @return void
	 */
	#[ApiRoute(verb: 'OPTIONS', url: '/api/v3/{path}', requirements: ['path' => '.+'])]
	public function preflightedCors() {
		parent::preflightedCors();
	}

	// API v3 methods
	// Forms
	/**
	 * Get all forms available to the user (owned/shared)
	 *
	 * @param string $type The type of forms to retrieve. Defaults to `owned`.
	 *                     Possible values:
	 *                     - `owned`: Forms owned by the user.
	 *                     - `shared`: Forms shared with the user.
	 * @return DataResponse<Http::STATUS_OK, list<FormsPartialForm>, array{}>
	 * @throws OCSBadRequestException wrong form type supplied
	 *
	 * 200: Array containing the partial owned or shared forms
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'GET', url: '/api/v3/forms')]
	public function getForms(string $type = 'owned'): DataResponse {
		$result = [];

		if ($type === 'owned') {
			$forms = $this->formMapper->findAllByOwnerId($this->currentUser->getUID());
			foreach ($forms as $form) {
				$result[] = $this->formsService->getPartialFormArray($form);
			}
		} elseif ($type === 'shared') {
			$forms = $this->formsService->getSharedForms($this->currentUser);
			$result = array_values(array_map(fn (Form $form): array => $this->formsService->getPartialFormArray($form), $forms));
		} else {
			throw new OCSBadRequestException('wrong form type supplied');
		}

		return new DataResponse($result, Http::STATUS_OK);
	}

	/**
	 * Create a new form and return the form
	 * Return a copy of the form if the parameter $fromId is set
	 *
	 * @param ?int $fromId (optional) Id of the form that should be cloned
	 * @return DataResponse<Http::STATUS_CREATED, FormsForm, array{}>
	 * @throws OCSForbiddenException The user is not allowed to create forms
	 *
	 * 201: the created form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms')]
	public function newForm(?int $fromId = null): DataResponse {
		// Check if user is allowed
		if (!$this->configService->canCreateForms()) {
			$this->logger->debug('This user is not allowed to create Forms.');
			throw new OCSForbiddenException('This user is not allowed to create Forms.');
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
			$form->setAllowEditSubmissions(false);
			$form->setShowExpiration(false);
			$form->setExpires(0);
			$form->setIsAnonymous(false);

			$this->formMapper->insert($form);
		} else {
			$oldForm = $this->formsService->getFormIfAllowed($fromId, Constants::PERMISSION_EDIT);

			// Read old form, (un)set new form specific data, extend title
			$formData = $oldForm->read();
			unset($formData['id']);
			unset($formData['created']);
			unset($formData['lastUpdated']);
			unset($formData['state']);
			unset($formData['fileId']);
			unset($formData['fileFormat']);
			unset($formData['lockedBy']);
			unset($formData['lockedUntil']);
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

		return new DataResponse($this->formsService->getForm($form), Http::STATUS_CREATED);
	}

	/**
	 * Read all information to edit a Form (form, questions, options, except submissions/answers)
	 *
	 * @param int $formId Id of the form
	 * @return DataResponse<Http::STATUS_OK, FormsForm, array{}>
	 * @throws OCSBadRequestException Could not find form
	 * @throws OCSForbiddenException User has no permissions to get this form
	 *
	 * 200: the requested form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'GET', url: '/api/v3/forms/{formId}')]
	public function getForm(int $formId): DataResponse {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_SUBMIT);

		return new DataResponse($this->formsService->getForm($form));
	}

	/**
	 * Writes the given key-value pairs into Database
	 *
	 * @param int $formId FormId of form to update
	 * @param array<string, mixed> $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse<Http::STATUS_OK, int|string, array{}>
	 * @throws OCSBadRequestException Could not find new form owner
	 * @throws OCSForbiddenException Empty keyValuePairs provided
	 * @throws OCSForbiddenException Not allowed to update id, hash, created, fileId or lastUpdated. OwnerId only allowed if no other key provided.
	 * @throws OCSForbiddenException User is not allowed to modify the form
	 * @throws OCSNotFoundException Form not found
	 *
	 * 200: the id of the updated form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'PATCH', url: '/api/v3/forms/{formId}')]
	public function updateForm(int $formId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating form: formId: {formId}, values: {keyValuePairs}', [
			'formId' => $formId,
			'keyValuePairs' => $keyValuePairs
		]);

		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$currentUserId = $this->currentUser->getUID();

		if (empty($keyValuePairs)) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSForbiddenException('Empty keyValuePairs, will not update.');
		}

		// Only allow the form owner to set/unset the "archived" state
		$this->checkArchivePermission($form, $currentUserId, $keyValuePairs);

		// Handle form locking/unlocking
		if ($this->isLockingRequest($keyValuePairs)) {
			return $this->handleFormLocking($form, $currentUserId);
		}
		if ($this->isUnlockingRequest($keyValuePairs)) {
			return $this->handleFormUnlocking($form, $currentUserId);
		}

		// Lock form temporary
		$this->formsService->obtainFormLock($form);

		// Handle owner transfer
		if ($this->isOwnerTransferRequest($keyValuePairs)) {
			return $this->handleOwnerTransfer($form, $formId, $currentUserId, $keyValuePairs);
		}

		// Don't allow to change the following attributes
		$this->checkForbiddenKeys($keyValuePairs);

		// Don't allow to change fileId
		$this->checkFileIdUpdate($keyValuePairs);

		// Do not allow changing showToAllUsers or permitAllUsers if disabled
		$this->checkAccessUpdate($keyValuePairs);

		// Process file linking
		if (isset($keyValuePairs['path']) && isset($keyValuePairs['fileFormat'])) {
			$file = $this->submissionService->writeFileToCloud($form, $keyValuePairs['path'], $keyValuePairs['fileFormat']);
			$form->setFileId($file->getId());
			$form->setFileFormat($keyValuePairs['fileFormat']);
		}

		// Process file unlinking
		if (key_exists('fileId', $keyValuePairs) && key_exists('fileFormat', $keyValuePairs) && !isset($keyValuePairs['fileFormat'])) {
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
	 * Delete a form
	 *
	 * @param int $formId the form id
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSForbiddenException User is not allowed to delete the form
	 * @throws OCSNotFoundException Form not found
	 *
	 * 200: the id of the deleted form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'DELETE', url: '/api/v3/forms/{formId}')]
	public function deleteForm(int $formId): DataResponse {
		$this->logger->debug('Delete Form: {formId}', [
			'formId' => $formId,
		]);

		$form = $this->formsService->getFormIfAllowed($formId);
		$this->formMapper->deleteForm($form);

		return new DataResponse($formId);
	}

	// Questions
	/**
	 * Read all questions (including options)
	 *
	 * @param int $formId the form id
	 * @return DataResponse<Http::STATUS_OK, list<FormsQuestion>, array{}>
	 * @throws OCSForbiddenException User has no permissions to get this form
	 * @throws OCSNotFoundException Could not find form
	 *
	 * 200: the questions of the given form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'GET', url: '/api/v3/forms/{formId}/questions')]
	public function getQuestions(int $formId): DataResponse {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException('Could not find form');
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException('User has no permissions to get this form');
		}

		$questionData = $this->formsService->getQuestions($formId);
		$questionData = array_map(static function (array $question) {
			if (empty($question['extraSettings'])) {
				$question['extraSettings'] = new \stdClass();
			}
			return $question;
		}, $questionData);

		return new DataResponse($questionData);
	}

	/**
	 * Read a specific question (including options)
	 *
	 * @param int $formId FormId
	 * @param int $questionId QuestionId
	 * @return DataResponse<Http::STATUS_OK, FormsQuestion, array{}>
	 * @throws OCSBadRequestException Question doesn\'t belong to given Form
	 * @throws OCSForbiddenException User has no permissions to get this form
	 * @throws OCSNotFoundException Could not find form
	 *
	 * 200: the requested question
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[ApiRoute(verb: 'GET', url: '/api/v3/forms/{formId}/questions/{questionId}')]
	public function getQuestion(int $formId, int $questionId): DataResponse {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new OCSNotFoundException('Could not find form');
		}

		if (!$this->formsService->hasUserAccess($form)) {
			$this->logger->debug('User has no permissions to get this form');
			throw new OCSForbiddenException('User has no permissions to get this form');
		}

		$question = $this->formsService->getQuestion($questionId);
		if ($question === null) {
			throw new OCSNotFoundException('Question doesn\'t exist');
		}

		if ($question['formId'] !== $formId) {
			throw new OCSBadRequestException('Question doesn\'t belong to given form');
		}

		if (empty($question['extraSettings'])) {
			$question['extraSettings'] = new \stdClass();
		}

		return new DataResponse($question);
	}

	/**
	 * Add a new question
	 *
	 * @param int $formId the form id
	 * @param FormsQuestionType $type the new question type
	 * @param string $text the new question title
	 * @param ?int $fromId (optional) id of the question that should be cloned
	 * @return DataResponse<Http::STATUS_CREATED, FormsQuestion, array{}>
	 * @throws OCSBadRequestException Invalid type
	 * @throws OCSBadRequestException Datetime question type no longer supported
	 * @throws OCSForbiddenException User has no permissions to get this form
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 201: the created question
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms/{formId}/questions')]
	public function newQuestion(int $formId, ?string $type = null, string $text = '', ?int $fromId = null): DataResponse {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
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

			$response = $this->formsService->getQuestion($question->getId());
			if ($response === null) {
				throw new OCSException('Failed to create question');
			}
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

		if (empty($response['extraSettings'])) {
			$response['extraSettings'] = new \stdClass();
		}

		return new DataResponse($response, Http::STATUS_CREATED);
	}

	/**
	 * Writes the given key-value pairs into Database
	 * Key `order` should only be changed by reorderQuestions() and is not allowed here
	 *
	 * @param int $formId the form id
	 * @param int $questionId id of question to update
	 * @param array<string, mixed> $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSBadRequestException Question doesn\'t belong to given Form
	 * @throws OCSBadRequestException Invalid extraSettings, will not update.
	 * @throws OCSForbiddenException Empty keyValuePairs, will not update
	 * @throws OCSForbiddenException Not allowed to update `id` or `formId`
	 * @throws OCSForbiddenException Please use reorderQuestions() to change order
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException User has no permissions to get this form
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 200: the id of the updated question
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'PATCH', url: '/api/v3/forms/{formId}/questions/{questionId}')]
	public function updateQuestion(int $formId, int $questionId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating question: formId: {formId}, questionId: {questionId}, values: {keyValuePairs}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'keyValuePairs' => $keyValuePairs
		]);

		// Make sure we query the form first to check the user has permissions
		// So the user does not get information about "questions" if they do not even have permissions to the form
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSNotFoundException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			throw new OCSBadRequestException('Question doesn\'t belong to given Form');
		}

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update.');
			throw new OCSBadRequestException('This form is archived and can not be modified');
		}

		//Don't allow to change id or formId
		if (key_exists('id', $keyValuePairs) || key_exists('formId', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update \'id\' or \'formId\'');
			throw new OCSForbiddenException('Not allowed to update \'id\' or \'formId\'');
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
	 * Delete a question
	 *
	 * @param int $formId the form id
	 * @param int $questionId the question id
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSBadRequestException Question doesn\'t belong to given Form
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException User has no permissions to get this form
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 200: the id of the deleted question
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'DELETE', url: '/api/v3/forms/{formId}/questions/{questionId}')]
	public function deleteQuestion(int $formId, int $questionId): DataResponse {
		$this->logger->debug('Mark question as deleted: {questionId}', [
			'questionId' => $questionId,
		]);


		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSNotFoundException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			throw new OCSBadRequestException('Question doesn\'t belong to given Form');
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
	 * Updates the Order of all Questions of a Form
	 *
	 * @param int $formId Id of the form to reorder
	 * @param list<int> $newOrder Array of Question-Ids in new order.
	 * @return DataResponse<Http::STATUS_OK, array<string, FormsOrder>, array{}>
	 * @throws OCSBadRequestException The given array contains duplicates
	 * @throws OCSBadRequestException The length of the given array does not match the number of stored questions
	 * @throws OCSBadRequestException Question doesn't belong to given Form
	 * @throws OCSBadRequestException One question has already been marked as deleted
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException User has no permissions to get this form
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 200: the question ids of the given form in the new order
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'PATCH', url: '/api/v3/forms/{formId}/questions')]
	public function reorderQuestions(int $formId, array $newOrder): DataResponse {
		$this->logger->debug('Reordering Questions on Form {formId} as Question-Ids {newOrder}', [
			'formId' => $formId,
			'newOrder' => $newOrder
		]);

		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
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
				$this->logger->debug('Could not find question {questionId}', [
					'questionId' => $questionId
				]);
				throw new OCSNotFoundException('Could not find question');
			}

			// Abort if a question is not part of the Form.
			if ($questions[$arrayKey]->getFormId() !== $formId) {
				$this->logger->debug('This Question is not part of the given form: {questionId}', [
					'questionId' => $questionId
				]);
				throw new OCSBadRequestException('Question doesn\'t belong to given Form');
			}

			// Abort if a question is already marked as deleted (order==0)
			$oldOrder = $questions[$arrayKey]->getOrder();
			if ($oldOrder === 0) {
				$this->logger->debug('This question has already been marked as deleted: Id: {questionId}', [
					'questionId' => $questions[$arrayKey]->getId()
				]);
				throw new OCSBadRequestException('One question has already been marked as deleted');
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

			$response[(string)$question->getId()] = [
				'order' => $question->getOrder()
			];
		}

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	// Options

	/**
	 * Add a new option to a question
	 *
	 * @param int $formId id of the form
	 * @param int $questionId id of the question
	 * @param list<string> $optionTexts the new option text
	 * @return DataResponse<Http::STATUS_CREATED, list<FormsOption>, array{}> Returns a DataResponse containing the added options
	 * @throws OCSBadRequestException This question is not part ot the given form
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException Current user has no permission to edit
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 201: the created option
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms/{formId}/questions/{questionId}/options')]
	public function newOption(int $formId, int $questionId, array $optionTexts): DataResponse {
		$this->logger->debug('Adding new options: formId: {formId}, questionId: {questionId}, text: {text}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'text' => $optionTexts,
		]);

		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSNotFoundException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			$this->logger->debug('This question is not part of the given form: questionId: {questionId}', [
				'questionId' => $questionId
			]);
			throw new OCSBadRequestException('This question is not part ot the given form');
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

		return new DataResponse($addedOptions, Http::STATUS_CREATED);
	}

	/**
	 * Writes the given key-value pairs into Database
	 *
	 * @param int $formId id of form
	 * @param int $questionId id of question
	 * @param int $optionId id of option to update
	 * @param array<string, mixed> $keyValuePairs Array of key=>value pairs to update.
	 * @return DataResponse<Http::STATUS_OK, int, array{}> Returns the id of the updated option
	 * @throws OCSBadRequestException The given option id doesn't match the question or form
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException Current user has no permission to edit
	 * @throws OCSForbiddenException Empty keyValuePairs, will not update
	 * @throws OCSForbiddenException Not allowed to update id or questionId
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find option or question
	 *
	 * 200: the id of the updated option
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'PATCH', url: '/api/v3/forms/{formId}/questions/{questionId}/options/{optionId}')]
	public function updateOption(int $formId, int $questionId, int $optionId, array $keyValuePairs): DataResponse {
		$this->logger->debug('Updating option: form: {formId}, question: {questionId}, option: {optionId}, values: {keyValuePairs}', [
			'formId' => $formId,
			'questionId' => $questionId,
			'optionId' => $optionId,
			'keyValuePairs' => $keyValuePairs
		]);

		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$option = $this->optionMapper->findById($optionId);
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find option or question');
			throw new OCSNotFoundException('Could not find option or question');
		}

		if ($option->getQuestionId() !== $questionId || $question->getFormId() !== $formId) {
			$this->logger->debug('The given option id doesn\'t match the question or form.');
			throw new OCSBadRequestException('The given option id doesn\'t match the question or form.');
		}

		// Don't allow empty array
		if (sizeof($keyValuePairs) === 0) {
			$this->logger->info('Empty keyValuePairs, will not update');
			throw new OCSForbiddenException('Empty keyValuePairs, will not update');
		}

		//Don't allow to change id or questionId
		if (key_exists('id', $keyValuePairs) || key_exists('questionId', $keyValuePairs)) {
			$this->logger->debug('Not allowed to update id or questionId');
			throw new OCSForbiddenException('Not allowed to update id or questionId');
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
	 * Delete an option
	 *
	 * @param int $formId id of form
	 * @param int $questionId id of question
	 * @param int $optionId id of option to update
	 * @return DataResponse<Http::STATUS_OK, int, array{}> Returns the id of the deleted option
	 * @throws OCSBadRequestException The given option id doesn't match the question or form
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException Current user has no permission to edit
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question or option
	 *
	 * 200: the id of the deleted option
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'DELETE', url: '/api/v3/forms/{formId}/questions/{questionId}/options/{optionId}')]
	public function deleteOption(int $formId, int $questionId, int $optionId): DataResponse {
		$this->logger->debug('Deleting option: {optionId}', [
			'optionId' => $optionId
		]);

		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
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
			throw new OCSBadRequestException('The given option id doesn\'t match the question or form.');
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
	 * Reorder options for a given question
	 * @param int $formId id of form
	 * @param int $questionId id of question
	 * @param list<int> $newOrder Array of option ids in new order.
	 * @return DataResponse<Http::STATUS_OK, array<string, FormsOrder>, array{}>
	 * @throws OCSBadRequestException The given question id doesn't match the form
	 * @throws OCSBadRequestException The given array contains duplicates
	 * @throws OCSBadRequestException The length of the given array does not match the number of stored options
	 * @throws OCSBadRequestException This option is not part of the given question
	 * @throws OCSForbiddenException This form is archived and can not be modified
	 * @throws OCSForbiddenException Current user has no permission to edit
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 200: the options of the question in the new order
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'PATCH', url: '/api/v3/forms/{formId}/questions/{questionId}/options')]
	public function reorderOptions(int $formId, int $questionId, array $newOrder) {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_EDIT);
		$this->formsService->obtainFormLock($form);

		if ($this->formsService->isFormArchived($form)) {
			$this->logger->debug('This form is archived and can not be modified');
			throw new OCSForbiddenException('This form is archived and can not be modified');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question');
			throw new OCSNotFoundException('Could not find question');
		}

		if ($question->getFormId() !== $formId) {
			$this->logger->debug('The given question id doesn\'t match the form.');
			throw new OCSBadRequestException('The given question id doesn\'t match the form.');
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
				throw new OCSNotFoundException('Could not find option');
			}

			// Abort if a option is not part of the question.
			if ($options[$arrayKey]->getQuestionId() !== $questionId) {
				$this->logger->debug('This option is not part of the given question: formId: {formId}', [
					'formId' => $formId
				]);
				throw new OCSBadRequestException('This option is not part of the given question');
			}

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

			$response[(string)$option->getId()] = [
				'order' => $option->getOrder()
			];
		}

		$this->formMapper->update($form);

		return new DataResponse($response);
	}

	// Submissions

	/**
	 * Get all the submissions of a given form
	 *
	 * @param int $formId of the form
	 * @param ?string $query (optional) A search query to filter submissions
	 * @param ?int $limit (optional) The maximum number of submissions to retrieve. Defaults to `null`
	 * @param int $offset (optional) The offset for pagination. Defaults to `0`
	 * @param ?string $fileFormat (optional) The file format that should be used for the download. Defaults to `null`
	 *                            Possible values:
	 *                            - `csv`: Comma-separated value
	 *                            - `ods`: OpenDocument Spreadsheet
	 *                            - `xlsx`: Excel Open XML Spreadsheet
	 * @return DataResponse<Http::STATUS_OK, FormsSubmissions, array{}>|DataDownloadResponse<Http::STATUS_OK, 'text/csv'|'application/vnd.oasis.opendocument.spreadsheet'|'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', array{}>
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSForbiddenException The current user has no permission to get the results for this form
	 *
	 * 200: the submissions of the form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'GET', url: '/api/v3/forms/{formId}/submissions')]
	public function getSubmissions(int $formId, ?string $query = null, ?int $limit = null, int $offset = 0, ?string $fileFormat = null): DataResponse|DataDownloadResponse {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_RESULTS);

		if ($fileFormat !== null) {
			$submissionsData = $this->submissionService->getSubmissionsData($form, $fileFormat);
			$fileName = $this->formsService->getFileName($form, $fileFormat);

			return new DataDownloadResponse($submissionsData, $fileName, Constants::SUPPORTED_EXPORT_FORMATS[$fileFormat]);
		}

		// Load submissions and currently active questions
		if (in_array(Constants::PERMISSION_RESULTS, $this->formsService->getPermissions($form))) {
			$submissions = $this->submissionService->getSubmissions($formId, null, $query, $limit, $offset);
			$filteredSubmissionsCount = $this->submissionMapper->countSubmissions($formId, null, $query);
		} else {
			$userId = $this->currentUser->getUID();
			$submissions = $this->submissionService->getSubmissions($formId, $userId, $query, $limit, $offset);
			$filteredSubmissionsCount = $this->submissionMapper->countSubmissions($formId, $userId, $query);
		}
		$questions = [];
		foreach ($this->formsService->getQuestions($formId) as $question) {
			$questions[$question['id']] = $question;
		}


		// Append Display Names
		$submissions = array_map(function (array $submission) use ($questions) {
			if (!empty($submission['answers'])) {
				$submission['answers'] = array_map(function (array $answer) use ($questions) {
					$name = $questions[$answer['questionId']]['name'];
					if ($name) {
						$answer['questionName'] = $name;
					}
					return $answer;
				}, $submission['answers']);
			}

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
			return $submission;
		}, $submissions);

		$questions = array_map(static function (array $question) {
			if (empty($question['extraSettings'])) {
				$question['extraSettings'] = new \stdClass();
			}
			return $question;
		}, $questions);

		$response = [
			'submissions' => $submissions,
			'questions' => array_values($questions),
			'filteredSubmissionsCount' => $filteredSubmissionsCount,
		];

		return new DataResponse($response);
	}

	/**
	 * Get a specific submission
	 *
	 * @param int $formId of the form
	 * @param int $submissionId of the submission
	 * @return DataResponse<Http::STATUS_OK, FormsSubmission, array{}>
	 * @throws OCSBadRequestException Submission doesn't belong to given form
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSNotFoundException Submission doesn't exist
	 * @throws OCSForbiddenException The current user has no permission to get this submission
	 *
	 * 200: the submissions of the form
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'GET', url: '/api/v3/forms/{formId}/submissions/{submissionId}')]
	public function getSubmission(int $formId, int $submissionId): DataResponse|DataDownloadResponse {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_RESULTS);

		$submission = $this->submissionService->getSubmission($submissionId);
		if ($submission === null) {
			throw new OCSNotFoundException('Submission doesn\'t exist');
		}

		if ($submission['formId'] !== $formId) {
			throw new OCSBadRequestException('Submission doesn\'t belong to given form');
		}

		// Append Display Names
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

		return new DataResponse($submission);
	}

	/**
	 * Delete all submissions of a specified form
	 *
	 * @param int $formId the form id
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSNotFoundException Could not find form
	 * @throws OCSForbiddenException This form is not owned by the current user and user has no `results_delete` permission
	 *
	 * 200: the form id of the deleted submission
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'DELETE', url: '/api/v3/forms/{formId}/submissions')]
	public function deleteAllSubmissions(int $formId): DataResponse {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_RESULTS_DELETE);

		// Delete all submissions (incl. Answers)
		$this->submissionMapper->deleteByForm($formId);
		$this->formMapper->update($form);

		return new DataResponse($formId);
	}

	/**
	 * Process a new submission
	 *
	 * @param int $formId the form id
	 * @param array<string, list<string>> $answers [question_id => arrayOfString]
	 * @param string $shareHash public share-hash -> Necessary to submit on public link-shares.
	 * @return DataResponse<Http::STATUS_CREATED, null, array{}>
	 * @throws OCSBadRequestException At least one submitted answer is not valid
	 * @throws OCSForbiddenException Already submitted
	 * @throws OCSForbiddenException Not allowed to access this form
	 * @throws OCSForbiddenException This form is no longer taking answers
	 * @throws OCSForbiddenException This form is not owned by the current user and user has no `results_delete` permission
	 * @throws OCSNotFoundException Could not find form
	 *
	 * 201: empty response
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[PublicPage()]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms/{formId}/submissions')]
	public function newSubmission(int $formId, array $answers, string $shareHash = ''): DataResponse {
		$this->logger->debug('Inserting submission: formId: {formId}, answers: {answers}, shareHash: {shareHash}', [
			'formId' => $formId,
			'answers' => $answers,
			'shareHash' => $shareHash,
		]);

		$form = $this->formsService->loadFormForSubmission($formId, $shareHash);

		$questions = $this->formsService->getQuestions($formId);
		try {
			// Is the submission valid
			$this->submissionService->validateSubmission($questions, $answers, $form->getOwnerId());
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
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

		return new DataResponse(null, Http::STATUS_CREATED);
	}

	/**
	 * Update an existing submission
	 *
	 * @param int $formId the form id
	 * @param int $submissionId the submission id
	 * @param array<string, list<string>> $answers [question_id => arrayOfString]
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSBadRequestException Can only update submission if allowEditSubmissions is set and the answers are valid
	 * @throws OCSForbiddenException Can only update your own submission
	 *
	 * 200: the id of the updated submission
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[NoCSRFRequired()]
	#[PublicPage()]
	#[ApiRoute(verb: 'PUT', url: '/api/v3/forms/{formId}/submissions/{submissionId}')]
	public function updateSubmission(int $formId, int $submissionId, array $answers): DataResponse {
		$this->logger->debug('Updating submission: formId: {formId}, answers: {answers}', [
			'formId' => $formId,
			'answers' => $answers,
		]);

		// submissions can't be updated on public shares, so passing empty shareHash
		$form = $this->formsService->loadFormForSubmission($formId, '');

		if (!$form->getAllowEditSubmissions()) {
			throw new OCSBadRequestException('Can only update if allowEditSubmissions is set');
		}

		$questions = $this->formsService->getQuestions($formId);
		try {
			// Is the submission valid
			$this->submissionService->validateSubmission($questions, $answers, $form->getOwnerId());
		} catch (\InvalidArgumentException $e) {
			throw new OCSBadRequestException($e->getMessage());
		}

		// get existing submission of this user
		try {
			$submission = $this->submissionMapper->findById($submissionId);
		} catch (DoesNotExistException $e) {
			throw new OCSBadRequestException('Submission doesn\'t exist');
		}

		if ($formId !== $submission->getFormId()) {
			throw new OCSBadRequestException('Submission doesn\'t belong to given form');
		}

		if ($this->currentUser->getUID() !== $submission->getUserId()) {
			throw new OCSForbiddenException('Can only update your own submissions');
		}

		$submission->setTimestamp(time());
		$this->submissionMapper->update($submission);

		// Delete current answers
		$this->answerMapper->deleteBySubmission($submissionId);

		// Process Answers
		foreach ($answers as $questionId => $answerArray) {
			// Search corresponding Question, skip processing if not found
			$questionIndex = array_search($questionId, array_column($questions, 'id'));
			if ($questionIndex === false) {
				continue;
			}

			$question = $questions[$questionIndex];

			$this->storeAnswersForQuestion($form, $submission->getId(), $question, $answerArray);
		}

		//Create Activity
		$this->formsService->notifyNewSubmission($form, $submission);

		return new DataResponse($submissionId);
	}

	/**
	 * Delete a specific submission
	 *
	 * @param int $formId the form id
	 * @param int $submissionId the submission id
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 * @throws OCSBadRequestException Submission doesn't belong to given form
	 * @throws OCSNotFoundException Could not find form or submission
	 * @throws OCSForbiddenException This form is not owned by the current user and user has no `results_delete` permission
	 *
	 * 200: the id of the deleted submission
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'DELETE', url: '/api/v3/forms/{formId}/submissions/{submissionId}')]
	public function deleteSubmission(int $formId, int $submissionId): DataResponse {
		$this->logger->debug('Delete Submission: {submissionId}', [
			'submissionId' => $submissionId,
		]);

		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_RESULTS_DELETE);
		try {
			$submission = $this->submissionMapper->findById($submissionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find submission');
			throw new OCSNotFoundException('Could not find submission');
		}

		if ($formId !== $submission->getFormId()) {
			$this->logger->debug('Submission doesn\'t belong to given form');
			throw new OCSBadRequestException('Submission doesn\'t belong to given form');
		}

		if (
			!in_array(Constants::PERMISSION_RESULTS_DELETE, $this->formsService->getPermissions($form))
			&& $this->currentUser->getUID() !== $submission->getUserId()
		) {
			throw new OCSForbiddenException('Can only delete your own submissions');
		}

		// Delete submission (incl. Answers)
		$this->submissionMapper->deleteById($submissionId);
		$this->formMapper->update($form);

		return new DataResponse($submissionId);
	}

	/**
	 * Export Submissions to the Cloud
	 *
	 * @param int $formId of the form
	 * @param string $path The Cloud-Path to export to
	 * @param string $fileFormat File format used for export
	 * @return DataResponse<Http::STATUS_OK, string, array{}>
	 * @throws OCSForbiddenException The current user has no permission to get the results for this form
	 * @throws OCSNotFoundException Could not find form
	 *
	 * 200: the file name used for storing the submissions
	 */
	#[CORS()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms/{formId}/submissions/export')]
	public function exportSubmissionsToCloud(int $formId, string $path, string $fileFormat = Constants::DEFAULT_FILE_FORMAT) {
		$form = $this->formsService->getFormIfAllowed($formId, Constants::PERMISSION_RESULTS);
		$file = $this->submissionService->writeFileToCloud($form, $path, $fileFormat);

		return new DataResponse($file->getName());
	}

	/**
	 * Uploads a temporary files to the server during form filling
	 *
	 * @param int $formId id of the form
	 * @param int $questionId id of the question
	 * @param string $shareHash hash of the form share
	 * @return DataResponse<Http::STATUS_OK, list<FormsUploadedFile>, array{}>
	 * @throws OCSBadRequestException No files provided
	 * @throws OCSBadRequestException Question doesn't belong to the given form
	 * @throws OCSBadRequestException Invalid file provided
	 * @throws OCSBadRequestException Failed to upload the file
	 * @throws OCSBadRequestException File size exceeds the maximum allowed size
	 * @throws OCSBadRequestException File type is not allowed
	 * @throws OCSForbiddenException Already submitted
	 * @throws OCSNotFoundException Could not find question
	 *
	 * 200: the file id and name of the uploaded file
	 */
	#[CORS()]
	#[PublicPage()]
	#[NoAdminRequired()]
	#[BruteForceProtection(action: 'form')]
	#[ApiRoute(verb: 'POST', url: '/api/v3/forms/{formId}/submissions/files/{questionId}')]
	public function uploadFiles(int $formId, int $questionId, string $shareHash = ''): DataResponse {
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

		$form = $this->formsService->loadFormForSubmission($formId, $shareHash);

		if (!$this->formsService->canSubmit($form)) {
			throw new OCSForbiddenException('Already submitted');
		}

		try {
			$question = $this->questionMapper->findById($questionId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find question with id {questionId}', [
				'questionId' => $questionId
			]);
			throw new OCSNotFoundException('Could not find question');
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

	// private functions

	/**
	 * Insert answers for a question
	 *
	 * @param Form $form
	 * @param int $submissionId
	 * @param array $question
	 * @param string[]|array<array{uploadedFileId: string, uploadedFileName: string}> $answerArray
	 */
	private function storeAnswersForQuestion(Form $form, $submissionId, array $question, array $answerArray): void {
		foreach ($answerArray as $answer) {
			$answerEntity = new Answer();
			$answerEntity->setSubmissionId($submissionId);
			$answerEntity->setQuestionId($question['id']);

			$answerText = '';
			$uploadedFile = null;
			// Are we using answer ids as values
			if (in_array($question['type'], Constants::ANSWER_TYPES_PREDEFINED) && $question['type'] !== Constants::ANSWER_TYPE_LINEARSCALE) {
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

	/**
	 * Throws if forbidden keys are present in update
	 */
	private function checkForbiddenKeys(array $keyValuePairs): void {
		$forbiddenKeys = [
			'id', 'hash', 'ownerId', 'created', 'lastUpdated', 'lockedBy', 'lockedUntil'
		];
		foreach ($forbiddenKeys as $key) {
			if (array_key_exists($key, $keyValuePairs)) {
				$this->logger->info("Not allowed to update {$key}");
				throw new OCSForbiddenException("Not allowed to update {$key}");
			}
		}
	}

	/**
	 * Throws if fileId is present in update
	 */
	private function checkFileIdUpdate(array $keyValuePairs): void {
		if (isset($keyValuePairs['fileId'])) {
			$this->logger->info('Not allowed to update fileId');
			throw new OCSForbiddenException('Not allowed to update fileId');
		}
	}

	/**
	 * Throws if access keys are being updated when not allowed
	 */
	private function checkAccessUpdate(array $keyValuePairs): void {
		if (isset($keyValuePairs['access'])) {
			$showAll = $keyValuePairs['access']['showToAllUsers'] ?? false;
			$permitAll = $keyValuePairs['access']['permitAllUsers'] ?? false;
			if (($showAll && !$this->configService->getAllowShowToAll())
				|| ($permitAll && !$this->configService->getAllowPermitAll())) {
				$this->logger->info('Not allowed to update showToAllUsers or permitAllUsers');
				throw new OCSForbiddenException();
			}
		}
	}

	/**
	 * Checks if the current user is allowed to archive/unarchive the form
	 */
	private function checkArchivePermission(Form $form, string $currentUserId, array $keyValuePairs): void {
		$isArchived = $this->formsService->isFormArchived($form);
		$owner = $currentUserId === $form->getOwnerId();
		$onlyState = sizeof($keyValuePairs) === 1 && key_exists('state', $keyValuePairs);

		// Only check if the request is trying to change the archived state
		if ($onlyState && $keyValuePairs['state'] === Constants::FORM_STATE_ARCHIVED) {
			// Trying to archive
			if (!$owner || $isArchived) {
				$this->logger->debug('Only the form owner can archive the form, and only if it is not already archived');
				throw new OCSForbiddenException('Only the form owner can archive the form, and only if it is not already archived');
			}
		} elseif ($onlyState && $keyValuePairs['state'] === Constants::FORM_STATE_CLOSED) {
			// Trying to unarchive
			if (!$owner || !$isArchived) {
				$this->logger->debug('Only the form owner can unarchive the form, and only if it is currently archived');
				throw new OCSForbiddenException('Only the form owner can unarchive the form, and only if it is currently archived');
			}
		}
		// All other updates are allowed (including updates that do not touch the state)
	}

	private function isLockingRequest(array $keyValuePairs): bool {
		return sizeof($keyValuePairs) === 1
			&& array_key_exists('lockedUntil', $keyValuePairs)
			&& $keyValuePairs['lockedUntil'] === 0;
	}

	private function isUnlockingRequest(array $keyValuePairs): bool {
		return sizeof($keyValuePairs) === 1
			&& array_key_exists('lockedUntil', $keyValuePairs)
			&& is_null($keyValuePairs['lockedUntil']);
	}

	private function isOwnerTransferRequest(array $keyValuePairs): bool {
		return sizeof($keyValuePairs) === 1 && key_exists('ownerId', $keyValuePairs);
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 */
	private function handleFormLocking(Form $form, string $currentUserId): DataResponse {
		if ($currentUserId !== $form->getOwnerId() || ($form->getLockedBy() !== null && $currentUserId !== $form->getLockedBy())) {
			$this->logger->debug('Only the form owner can lock the form permanently');
			throw new OCSForbiddenException('Only the form owner can lock the form permanently');
		}
		if (
			$form->getLockedBy() !== null
			&& $form->getLockedBy() !== $currentUserId
			&& $form->getLockedUntil() >= time()
		) {
			$this->logger->debug('Form is currently locked by another user.');
			throw new OCSForbiddenException('Form is currently locked by another user.');
		}
		if ($form->getLockedUntil() === 0) {
			$this->logger->debug('Form is already locked completely.');
			throw new OCSBadRequestException('Form is already locked completely.');
		}
		$form->setLockedBy($form->getOwnerId());
		$form->setLockedUntil(0);
		$this->formMapper->update($form);
		return new DataResponse($form->getId());
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, int, array{}>
	 */
	private function handleFormUnlocking(Form $form, string $currentUserId): DataResponse {
		if ($currentUserId !== $form->getOwnerId() && $currentUserId !== $form->getLockedBy() && $form->getLockedUntil() !== 0) {
			$this->logger->debug('Only the form owner or the user who obtained the lock can unlock the form');
			throw new OCSForbiddenException('Only the form owner or the user who obtained the lock can unlock the form');
		}
		$form->setLockedBy(null);
		$form->setLockedUntil(null);
		$this->formMapper->update($form);
		return new DataResponse($form->getId());
	}

	/**
	 * @return DataResponse<Http::STATUS_OK, string, array{}>
	 */
	private function handleOwnerTransfer(Form $form, int $formId, string $currentUserId, array $keyValuePairs): DataResponse {
		if ($currentUserId !== $form->getOwnerId()) {
			$this->logger->debug('Only the form owner can transfer ownership');
			throw new OCSForbiddenException('Only the form owner can transfer ownership');
		}
		$this->logger->debug('Updating owner: formId: {formId}, userId: {uid}', [
			'formId' => $formId,
			'uid' => $keyValuePairs['ownerId']
		]);
		$user = $this->userManager->get($keyValuePairs['ownerId']);
		if ($user == null) {
			$this->logger->debug('Could not find new form owner');
			throw new OCSBadRequestException('Could not find new form owner');
		}
		$form->setOwnerId($keyValuePairs['ownerId']);
		$form->setLockedBy(null);
		$form->setLockedUntil(null);
		$this->formMapper->update($form);
		return new DataResponse($form->getOwnerId());
	}
}
