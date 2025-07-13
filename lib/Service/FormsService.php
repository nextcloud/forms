<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Service;

use OCA\Forms\Activity\ActivityManager;
use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\Question;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\Submission;
use OCA\Forms\Db\SubmissionMapper;
use OCA\Forms\Events\FormSubmittedEvent;
use OCA\Forms\Exception\NoSuchFormException;
use OCA\Forms\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Search\ISearchQuery;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Trait for getting forms information in a service
 * @psalm-import-type FormsQuestion from ResponseDefinitions
 * @psalm-import-type FormsOption from ResponseDefinitions
 * @psalm-import-type FormsForm from ResponseDefinitions
 * @psalm-import-type FormsPermission from ResponseDefinitions
 * @psalm-import-type FormsShare from ResponseDefinitions
 */
class FormsService {
	private ?IUser $currentUser;

	public function __construct(
		IUserSession $userSession,
		private ActivityManager $activityManager,
		private FormMapper $formMapper,
		private OptionMapper $optionMapper,
		private QuestionMapper $questionMapper,
		private ShareMapper $shareMapper,
		private SubmissionMapper $submissionMapper,
		private ConfigService $configService,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private ISecureRandom $secureRandom,
		private CirclesService $circlesService,
		private IRootFolder $rootFolder,
		private IL10N $l10n,
		private LoggerInterface $logger,
		private IEventDispatcher $eventDispatcher,
	) {
		$this->currentUser = $userSession->getUser();
	}

	/**
	 * Create a new Form Hash
	 */
	public function generateFormHash(): string {
		return $this->secureRandom->generate(
			16,
			ISecureRandom::CHAR_HUMAN_READABLE
		);
	}

	/**
	 * Load options corresponding to question
	 *
	 * @param integer $questionId
	 * @return list<FormsOption>
	 */
	private function getOptions(int $questionId): array {
		$optionList = [];
		try {
			$optionEntities = $this->optionMapper->findByQuestion($questionId);
			foreach ($optionEntities as $optionEntity) {
				$optionList[] = $optionEntity->read();
			}
		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $optionList;
		}
	}

	/**
	 * Load questions corresponding to form
	 *
	 * @param integer $formId
	 * @return list<FormsQuestion>
	 */
	public function getQuestions(int $formId): array {
		$questionList = [];
		try {
			$questionEntities = $this->questionMapper->findByForm($formId);
			foreach ($questionEntities as $questionEntity) {
				$question = $questionEntity->read();
				$question['options'] = $this->getOptions($question['id']);
				$question['accept'] = [];
				if ($question['type'] === Constants::ANSWER_TYPE_FILE) {
					if ($question['extraSettings']['allowedFileTypes'] ?? null) {
						$question['accept'] = array_map(function (string $fileType) {
							return str_contains($fileType, '/') ? $fileType : $fileType . '/*';
						}, $question['extraSettings']['allowedFileTypes']);
					}

					if ($question['extraSettings']['allowedFileExtensions'] ?? null) {
						foreach ($question['extraSettings']['allowedFileExtensions'] as $extension) {
							$question['accept'][] = '.' . $extension;
						}
					}
				}

				$questionList[] = $question;
			}
		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $questionList;
		}
	}

	/**
	 * Load specific question
	 *
	 * @param integer $questionId id of the question
	 * @return ?FormsQuestion
	 */
	public function getQuestion(int $questionId): ?array {
		try {
			$questionEntity = $this->questionMapper->findById($questionId);
			$question = $questionEntity->read();
			$question['options'] = $this->getOptions($question['id']);
			$question['accept'] = [];
			if ($question['type'] === Constants::ANSWER_TYPE_FILE) {
				if ($question['extraSettings']['allowedFileTypes'] ?? null) {
					$question['accept'] = array_map(function (string $fileType) {
						return str_contains($fileType, '/') ? $fileType : $fileType . '/*';
					}, $question['extraSettings']['allowedFileTypes']);
				}

				if ($question['extraSettings']['allowedFileExtensions'] ?? null) {
					foreach ($question['extraSettings']['allowedFileExtensions'] as $extension) {
						$question['accept'][] = '.' . $extension;
					}
				}
			}
			return $question;
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * Load shares corresponding to form
	 *
	 * @param integer $formId
	 * @return list<FormsShare>
	 */
	public function getShares(int $formId): array {
		$shareList = [];

		$shareEntities = $this->shareMapper->findByForm($formId);
		foreach ($shareEntities as $shareEntity) {
			$share = $shareEntity->read();
			$share['displayName'] = $this->getShareDisplayName($share);
			$shareList[] = $share;
		}

		return $shareList;
	}

	/**
	 * Get a form data
	 *
	 * @param Form $form
	 * @return FormsForm
	 * @throws IMapperException
	 */
	public function getForm(Form $form): array {
		$result = $form->read();
		$result['questions'] = $this->getQuestions($form->getId());
		$result['shares'] = $this->getShares($form->getId());

		// Append permissions for current user.
		$result['permissions'] = $this->getPermissions($form);
		// Append canSubmit, to be able to show proper EmptyContent on internal view.
		$result['canSubmit'] = $this->canSubmit($form);

		// Append submissionCount if currentUser has permissions to see results
		if (in_array(Constants::PERMISSION_RESULTS, $result['permissions'])) {
			$result['submissionCount'] = $this->submissionMapper->countSubmissions($form->getId());
		} elseif ($this->currentUser) {
			$userSubmissionCount = $this->submissionMapper->countSubmissions($form->getId(), $this->currentUser->getUID());
			if ($userSubmissionCount > 0) {
				$result['submissionCount'] = $userSubmissionCount;
				// Append `results` permission if user has submitted to the form
				$result['permissions'][] = Constants::PERMISSION_RESULTS;
			}
		}

		if ($result['fileId']) {
			try {
				$result['filePath'] = $this->getFilePath($form);
				// If file was deleted, set filePath to null
			} catch (NotFoundException $e) {
				$result['filePath'] = null;
			}
		}

		return $result;
	}

	/**
	 * Create partial form, as returned by Forms-Lists.
	 *
	 * @param Form $form
	 * @return array
	 * @throws IMapperException
	 */
	public function getPartialFormArray(Form $form): array {
		$result = [
			'id' => $form->getId(),
			'hash' => $form->getHash(),
			'title' => $form->getTitle(),
			'expires' => $form->getExpires(),
			'lastUpdated' => $form->getLastUpdated(),
			'permissions' => $this->getPermissions($form),
			'partial' => true,
			'state' => $form->getState(),
			'lockedBy' => $form->getLockedBy(),
			'lockedUntil' => $form->getLockedUntil(),
		];

		// Append submissionCount if currentUser has permissions to see results
		if (in_array(Constants::PERMISSION_RESULTS, $result['permissions'])) {
			$result['submissionCount'] = $this->submissionMapper->countSubmissions($form->getId());
		} else {
			$userSubmissionCount = $this->submissionMapper->countSubmissions($form->getId(), $this->currentUser->getUID());
			if ($userSubmissionCount > 0) {
				$result['submissionCount'] = $userSubmissionCount;
				// Append `results` permission if user has submitted to the form
				$result['permissions'][] = Constants::PERMISSION_RESULTS;
			}
		}

		return $result;
	}

	/**
	 * Get a form data without sensitive informations
	 *
	 * @param Form $form
	 * @return array
	 * @throws IMapperException
	 */
	public function getPublicForm(Form $form): array {
		$formData = $this->getForm($form);

		// Remove sensitive data
		unset($formData['access']);
		unset($formData['ownerId']);
		unset($formData['shares']);
		unset($formData['fileId']);
		unset($formData['filePath']);
		unset($formData['fileFormat']);

		return $formData;
	}

	/**
	 * Helper that retrieves a form if the current user is allowed to edit it
	 * This throws an exception in case either the form is not found or permissions are missing.
	 * @param int $formId The form ID to retrieve
	 * @throws NoSuchFormException If the form was not found or the current user has no permission to edit
	 */
	public function getFormIfAllowed(int $formId, string $permissions = 'all'): Form {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new NoSuchFormException('Could not find form');
		}

		switch ($permissions) {
			case Constants::PERMISSION_SUBMIT:
				if (!$this->hasUserAccess($form)) {
					$this->logger->debug('User has no permissions to get this form');
					throw new NoSuchFormException('User has no permissions to get this form', Http::STATUS_FORBIDDEN);
				}
				break;
			case Constants::PERMISSION_RESULTS:
				if (!$this->canSeeResults($form)) {
					$this->logger->debug('The current user has no permission to get the results for this form');
					throw new NoSuchFormException('The current user has no permission to get the results for this form', Http::STATUS_FORBIDDEN);
				}
				break;
			case Constants::PERMISSION_RESULTS_DELETE:
				if (!$this->canDeleteResults($form)) {
					$this->logger->debug('This form is not owned by the current user and user has no `results_delete` permission');
					throw new NoSuchFormException('This form is not owned by the current user and user has no `results_delete` permission', Http::STATUS_FORBIDDEN);
				}
				break;
			case Constants::PERMISSION_EDIT:
				if (!$this->canEditForm($form)) {
					$this->logger->debug('This form is not owned by the current user and user has no `edit` permission');
					throw new NoSuchFormException('This form is not owned by the current user and user has no `edit` permission', Http::STATUS_FORBIDDEN);
				}
				break;
			default:
				// By default we request full permissions
				if ($form->getOwnerId() !== $this->currentUser->getUID()) {
					$this->logger->debug('This form is not owned by the current user');
					throw new NoSuchFormException('This form is not owned by the current user', Http::STATUS_FORBIDDEN);
				}
				break;
		}
		return $form;
	}

	public function loadFormForSubmission(int $formId, string $shareHash): Form {
		try {
			$form = $this->formMapper->findById($formId);
		} catch (IMapperException $e) {
			$this->logger->debug('Could not find form');
			throw new NoSuchFormException('Could not find form');
		}

		// Does the user have access to the form (Either by logged-in user, or by providing public share-hash.)
		try {
			$isPublicShare = false;

			// If hash given, find the corresponding share & check if hash corresponds to given formId.
			if ($shareHash !== '') {
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
			if (!$isPublicShare && !$this->hasUserAccess($form)) {
				throw new NoSuchFormException('Not allowed to access this form', Http::STATUS_FORBIDDEN);
			}
		}

		// Not allowed if form has expired.
		if ($this->hasFormExpired($form)) {
			throw new OCSForbiddenException('This form is no longer taking answers');
		}

		return $form;
	}

	/**
	 * Locks the given form for the current user for a duration of 15 minutes.
	 *
	 * @param Form $form The form instance to lock.
	 */
	public function obtainFormLock(Form $form): void {
		// Only lock if not locked or locked by current user, or lock has expired
		if (
			$form->getLockedBy() !== null
			&& $form->getLockedBy() !== $this->currentUser->getUID()
			&& ($form->getLockedUntil() >= time() || $form->getLockedUntil() === 0)
		) {
			throw new OCSForbiddenException('Form is currently locked by another user.');
		}

		$form->setLockedBy($this->currentUser->getUID());
		$form->setLockedUntil(time() + 15 * 60);
	}

	/**
	 * Get current users permissions on a form
	 *
	 * @param Form $form
	 * @return list<FormsPermission>
	 */
	public function getPermissions(Form $form): array {
		if (!$this->currentUser) {
			return [];
		}

		// Owner is allowed to do everything
		if ($this->currentUser->getUID() === $form->getOwnerId()) {
			return Constants::PERMISSION_ALL;
		}

		$permissions = [];
		$shares = $this->getSharesWithUser($form->getId(), $this->currentUser->getUID());
		foreach ($shares as $share) {
			$permissions = array_merge($permissions, $share->getPermissions());
		}

		// Fall back to submit permission if access is granted to all users
		if (count($permissions) === 0) {
			$access = $form->getAccess();
			if ($access['permitAllUsers'] && $this->configService->getAllowPermitAll()) {
				$permissions = [Constants::PERMISSION_SUBMIT];
			}
		}

		return array_values(array_unique($permissions));
	}

	/**
	 * Can the current user edit a form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function canEditForm(Form $form): bool {
		return in_array(Constants::PERMISSION_EDIT, $this->getPermissions($form));
	}

	/**
	 * Determines if the current user has permission to view the results of a given form.
	 *
	 * A user can see the results of a form if they have made at least one submission
	 * to the form or possess the required permission to view results.
	 *
	 * @param Form $form The form for which the results visibility is being checked.
	 * @return bool True if the user can see the results, false otherwise.
	 */
	public function canSeeResults(Form $form): bool {
		return $this->submissionMapper->countSubmissions($form->getId(), $this->currentUser->getUID()) > 0
			|| in_array(Constants::PERMISSION_RESULTS, $this->getPermissions($form));
	}

	/**
	 * Determines if the current user has permission to delete the results of a given form.
	 *
	 * A user can delete the results of a form if the form is not archived and one of the following conditions is met:
	 * - The user has the "results_delete" permission.
	 * - The user has not submitted any responses, and the form allows editing.
	 * - The form is not archived.
	 *
	 * @param Form $form The form for which the results deletion permission is being checked.
	 * @return bool True if the user can delete the results, false otherwise.
	 */
	public function canDeleteResults(Form $form): bool {
		// Do not allow deleting results on archived forms
		if ($this->isFormArchived($form)) {
			return false;
		}

		// Allow deleting results if the current user has the "results_delete" permission
		if (in_array(Constants::PERMISSION_RESULTS_DELETE, $this->getPermissions($form))) {
			return true;
		}

		// Allow deleting results if the current user has already submitted
		if ($form->getAllowEditSubmissions() && $this->submissionMapper->countSubmissions($form->getId(), $this->currentUser->getUID()) > 0) {
			return true;
		}

		return false;
	}

	/**
	 * Can the user submit a form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function canSubmit(Form $form): bool {
		// We cannot control how many time users can submit if public link available
		if ($this->hasPublicLink($form)) {
			return true;
		}

		// Owner is always allowed to submit
		if ($this->currentUser->getUID() === $form->getOwnerId()) {
			return true;
		}

		// Refuse access, if submitMultiple is not set and user already has taken part.
		if (
			!$form->getSubmitMultiple()
			&& $this->submissionMapper->hasFormSubmissionsByUser($form, $this->currentUser->getUID())
		) {
			return false;
		}

		return true;
	}

	/**
	 * Searching Shares for public link
	 *
	 * @param Form $form
	 * @return boolean
	 */
	private function hasPublicLink(Form $form): bool {
		$shareEntities = $this->shareMapper->findByForm($form->getId());
		foreach ($shareEntities as $shareEntity) {
			if ($shareEntity->getShareType() === IShare::TYPE_LINK) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if current user has access to this form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function hasUserAccess(Form $form): bool {
		$access = $form->getAccess();
		$ownerId = $form->getOwnerId();

		// Refuse access, if no user logged in.
		if (!$this->currentUser) {
			return false;
		}

		// Always grant access to owner.
		if ($ownerId === $this->currentUser->getUID()) {
			return true;
		}

		// Now all remaining users are allowed, if permitAll is set.
		if ($access['permitAllUsers'] && $this->configService->getAllowPermitAll()) {
			return true;
		}

		// Selected Access remains.
		if ($this->isSharedToUser($form->getId())) {
			return true;
		}

		// None of the possible access-options matched.
		return false;
	}

	/**
	 * Get all forms shared to the user
	 * @param IUser $user User to query shared forms for
	 * @param bool $filterShown Set to false to also include forms shared but not visible on sidebar
	 */
	public function getSharedForms(IUser $user, bool $filterShown = true): array {
		$groups = $this->groupManager->getUserGroupIds($user);
		$teams = $this->circlesService->getUserTeamIds($user->getUID());
		$forms = $this->formMapper->findSharedForms(
			$user->getUID(),
			$groups,
			$teams,
			$filterShown,
		);

		// filter expired forms
		$forms = array_filter($forms, fn (Form $form): bool => $this->isSharedFormShown($form));
		return $forms;
	}

	/**
	 * Is the shared form shown on sidebar to the user.
	 *
	 * @param Form $form
	 * @return bool
	 */
	private function isSharedFormShown(Form $form): bool {
		// Dont show expired forms if user isn't allowed to see results.
		if ($this->hasFormExpired($form) && !$this->canSeeResults($form)) {
			return false;
		}

		// Shown if permitAll and showToAll are both set.
		if ($form->getAccess()['permitAllUsers']
			&& $form->getAccess()['showToAllUsers']
			&& $this->configService->getAllowPermitAll()
			&& $this->configService->getAllowShowToAll()) {

			return true;
		}
		return true;
	}

	/**
	 * Checking all selected shares
	 *
	 * @param int $formId
	 * @return bool
	 */
	private function isSharedToUser(int $formId): bool {
		$shareEntities = $this->getSharesWithUser($formId, $this->currentUser->getUID());
		return count($shareEntities) > 0;
	}

	/**
	 * Check if the form is archived
	 * If a form is archived no changes are allowed
	 */
	public function isFormArchived(Form $form): bool {
		return $form->getState() === Constants::FORM_STATE_ARCHIVED;
	}

	/**
	 * Check if the form was closed or archived or has expired.
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function hasFormExpired(Form $form): bool {
		// Check for form state first
		if ($form->getState() !== Constants::FORM_STATE_ACTIVE) {
			return true;
		}
		return ($form->getExpires() !== 0 && $form->getExpires() < time());
	}

	/**
	 * Get DisplayNames to Shares
	 *
	 * @param array $share
	 * @return string
	 */
	public function getShareDisplayName(array $share): string {
		$displayName = '';

		switch ($share['shareType']) {
			case IShare::TYPE_USER:
				$user = $this->userManager->get($share['shareWith']);
				if ($user instanceof IUser) {
					$displayName = $user->getDisplayName();
				}
				break;
			case IShare::TYPE_GROUP:
				$group = $this->groupManager->get($share['shareWith']);
				if ($group instanceof IGroup) {
					$displayName = $group->getDisplayName();
				}
				break;
			case IShare::TYPE_CIRCLE:
				$circle = $this->circlesService->getCircle($share['shareWith']);
				if (!is_null($circle)) {
					$displayName = $circle->getDisplayName();
				}
				break;
			default:
				// Preset Empty.
		}

		return $displayName;
	}

	/**
	 * Creates activities for sharing to users.
	 * @param Form $form Related Form
	 * @param Share $share The new Share
	 */
	public function notifyNewShares(Form $form, Share $share): void {
		try {
			switch ($share->getShareType()) {
				case IShare::TYPE_USER:
					$this->activityManager->publishNewShare($form, $share->getShareWith());
					break;
				case IShare::TYPE_GROUP:
					$this->activityManager->publishNewGroupShare($form, $share->getShareWith());
					break;
				case IShare::TYPE_CIRCLE:
					$this->activityManager->publishNewCircleShare($form, $share->getShareWith());
					break;
				default:
					// Do nothing.
			}
		} catch (\Exception $e) {
			// Handle exceptions silently, as this is not critical.
			// We don't want to break the share creation process just because of an activity error.
			$this->logger->error(
				'Error while publishing new share activity',
				[$e]
			);

		}

	}

	/**
	 * Creates activities for new submissions on a form
	 *
	 * @param Form $form Related Form
	 * @param string $submitter The ID of the user who submitted the form. Can also be our 'anon-user-'-ID
	 */
	public function notifyNewSubmission(Form $form, Submission $submission): void {
		$shares = $this->getShares($form->getId());
		try {
			$this->activityManager->publishNewSubmission($form, $submission->getUserId());
		} catch (\Exception $e) {
			// Handle exceptions silently, as this is not critical.
			// We don't want to break the submission process just because of an activity error.
			$this->logger->error(
				'Error while publishing new submission activity',
				[$e]
			);
		}

		foreach ($shares as $share) {
			if (!in_array(Constants::PERMISSION_RESULTS, $share['permissions'])) {
				continue;
			}
			try {
				$this->activityManager->publishNewSharedSubmission($form, $share['shareType'], $share['shareWith'], $submission->getUserId());
			} catch (\Exception $e) {
				// Handle exceptions silently, as this is not critical.
				// We don't want to break the submission process just because of an activity error.
				$this->logger->error(
					'Error while publishing new shared submission activity',
					[$e]
				);
			}
		}

		$this->eventDispatcher->dispatchTyped(new FormSubmittedEvent($form, $submission));
	}

	/**
	 * Return shares of a form shared with given user
	 *
	 * @param int $formId The form to query shares for
	 * @param string $userId The user to check if shared with
	 * @return Share[]
	 */
	private function getSharesWithUser(int $formId, string $userId): array {
		$shareEntities = $this->shareMapper->findByForm($formId);

		return array_filter($shareEntities, function ($shareEntity) use ($userId) {
			$share = $shareEntity->read();

			// Needs different handling for shareTypes
			switch ($share['shareType']) {
				case IShare::TYPE_USER:
					if ($share['shareWith'] === $userId) {
						return true;
					}
					break;
				case IShare::TYPE_GROUP:
					if ($this->groupManager->isInGroup($userId, $share['shareWith'])) {
						return true;
					}
					break;
				case IShare::TYPE_CIRCLE:
					if ($this->circlesService->isUserInCircle($share['shareWith'], $userId)) {
						return true;
					}
					break;
				default:
					return false;
			}
		});
	}

	/*
	 * Validates the extraSettings
	 *
	 * @param array $extraSettings input extra settings
	 * @param string $questionType the question type
	 * @return bool if the settings are valid
	 */
	public function areExtraSettingsValid(array $extraSettings, string $questionType): bool {
		if (count($extraSettings) === 0) {
			return true;
		}

		// Ensure only allowed keys are set
		switch ($questionType) {
			case Constants::ANSWER_TYPE_DROPDOWN:
				$allowed = Constants::EXTRA_SETTINGS_DROPDOWN;
				break;
			case Constants::ANSWER_TYPE_MULTIPLE:
			case Constants::ANSWER_TYPE_MULTIPLEUNIQUE:
				$allowed = Constants::EXTRA_SETTINGS_MULTIPLE;
				break;
			case Constants::ANSWER_TYPE_SHORT:
				$allowed = Constants::EXTRA_SETTINGS_SHORT;
				break;
			case Constants::ANSWER_TYPE_FILE:
				$allowed = Constants::EXTRA_SETTINGS_FILE;
				break;
			case Constants::ANSWER_TYPE_DATE:
				$allowed = Constants::EXTRA_SETTINGS_DATE;
				break;
			case Constants::ANSWER_TYPE_TIME:
				$allowed = Constants::EXTRA_SETTINGS_TIME;
				break;
			case Constants::ANSWER_TYPE_LINEARSCALE:
				$allowed = Constants::EXTRA_SETTINGS_LINEARSCALE;
				break;
			default:
				$allowed = [];
		}
		// Number of keys in extraSettings but not in allowed (but not the other way round)
		$diff = array_diff(array_keys($extraSettings), array_keys($allowed));
		if (count($diff) > 0) {
			return false;
		}

		// Check type of extra settings
		foreach ($extraSettings as $key => $value) {
			if (!in_array(gettype($value), $allowed[$key])) {
				// Not allowed type
				return false;
			}
		}

		// Validate extraSettings for specific question types
		if ($questionType === Constants::ANSWER_TYPE_DATE) {
			// Ensure dateMin and dateMax don't overlap
			if (isset($extraSettings['dateMin']) && isset($extraSettings['dateMax'])
				&& $extraSettings['dateMin'] > $extraSettings['dateMax']) {
				return false;
			}
		} elseif ($questionType === Constants::ANSWER_TYPE_TIME) {
			$format = Constants::ANSWER_PHPDATETIME_FORMAT['time'];

			// Validate timeMin format
			if (isset($extraSettings['timeMin'])) {
				$timeMinString = $extraSettings['timeMin'];
				$timeMinDate = \DateTime::createFromFormat($format, $timeMinString);
				if (!$timeMinDate || $timeMinDate->format($format) !== $timeMinString) {
					return false;
				}
			}

			// Validate timeMax format
			if (isset($extraSettings['timeMax'])) {
				$timeMaxString = $extraSettings['timeMax'];
				$timeMaxDate = \DateTime::createFromFormat($format, $timeMaxString);
				if (!$timeMaxDate || $timeMaxDate->format($format) !== $timeMaxString) {
					return false;
				}
			}

			// Ensure timeMin and timeMax don't overlap
			if (isset($extraSettings['timeMin']) && isset($extraSettings['timeMax'])
				&& $timeMinDate > $timeMaxDate) {
				return false;
			}
		} elseif ($questionType === Constants::ANSWER_TYPE_MULTIPLE) {
			// Ensure limits are sane
			if (isset($extraSettings['optionsLimitMax']) && isset($extraSettings['optionsLimitMin'])
				&& $extraSettings['optionsLimitMax'] < $extraSettings['optionsLimitMin']) {
				return false;
			}

			// Special handling of short input for validation
		} elseif ($questionType === Constants::ANSWER_TYPE_SHORT && isset($extraSettings['validationType'])) {
			// Ensure input validation type is known
			if (!in_array($extraSettings['validationType'], Constants::SHORT_INPUT_TYPES)) {
				return false;
			}

			// For custom validation we need to sanitize the regex
			if ($extraSettings['validationType'] === 'regex') {
				// regex is required for "custom" input validation type
				if (!isset($extraSettings['validationRegex'])) {
					return false;
				}

				// regex option must be a string
				if (!is_string($extraSettings['validationRegex'])) {
					return false;
				}

				// empty regex matches every thing, this happens also when a new question is created
				if (strlen($extraSettings['validationRegex']) === 0) {
					return true;
				}

				// general pattern of a valid regex
				$VALID_REGEX = '/^\/(.+)\/([smi]{0,3})$/';
				// pattern to look for unescaped slashes
				$REGEX_UNESCAPED_SLASHES = '/(?<=(^|[^\\\\]))(\\\\\\\\)*\\//';

				$matches = [];
				// only pattern with delimiters and supported modifiers (by PHP *and* JS)
				if (@preg_match($VALID_REGEX, $extraSettings['validationRegex'], $matches) !== 1) {
					return false;
				}

				// We use slashes as delimters, so unescaped slashes within the pattern are **not** allowed
				if (@preg_match($REGEX_UNESCAPED_SLASHES, $matches[1]) === 1) {
					return false;
				}

				// Try to compile the given pattern, `preg_match` will return false if the pattern is invalid
				if (@preg_match($extraSettings['validationRegex'], 'some string') === false) {
					return false;
				}
			}

			// Special handling of linear scale validation
		} elseif ($questionType === Constants::ANSWER_TYPE_LINEARSCALE) {
			// Ensure limits are sane
			if (isset($extraSettings['optionsLowest']) && ($extraSettings['optionsLowest'] < 0 || $extraSettings['optionsLowest'] > 1)
				|| isset($extraSettings['optionsHighest']) && ($extraSettings['optionsHighest'] < 2 || $extraSettings['optionsHighest'] > 10)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get list of forms
	 *
	 * @param ISearchQuery $query the query to search the forms
	 * @return Form[] list of forms that match the query
	 */
	public function search(ISearchQuery $query): array {
		$formsList = [];
		$groups = $this->groupManager->getUserGroupIds($this->currentUser);
		$teams = $this->circlesService->getUserTeamIds($this->currentUser->getUID());

		try {
			$ownedForms = $this->formMapper->findAllByOwnerId($this->currentUser->getUID(), $query->getTerm());
			$sharedForms = $this->formMapper->findSharedForms(
				$this->currentUser->getUID(),
				$groups,
				$teams,
				true,
				$query->getTerm()
			);
			$formsList = array_merge($ownedForms, $sharedForms);
		} catch (DoesNotExistException $e) {
			// silent catch
		}
		return $formsList;
	}

	public function getFilePath(Form $form): ?string {
		$fileId = $form->getFileId();

		if ($fileId === null) {
			return null;
		}

		$folder = $this->rootFolder->getUserFolder($form->getOwnerId());
		$nodes = $folder->getById($fileId);

		if (empty($nodes)) {
			throw new NotFoundException('File not found');
		}

		$internalPath = array_shift($nodes)->getPath();

		return $folder->getRelativePath($internalPath);
	}

	public function getFileName(Form $form, string $fileFormat): string {
		if (!isset(Constants::SUPPORTED_EXPORT_FORMATS[$fileFormat])) {
			throw new \InvalidArgumentException('Invalid file format');
		}

		// TRANSLATORS Appendix for CSV-Export: 'Form Title (responses).csv'
		$fileName = $form->getTitle() . ' (' . $this->l10n->t('responses') . ').' . $fileFormat;

		return self::normalizeFileName($fileName);
	}

	public function getFormUploadedFilesFolderPath(Form $form): string {
		return implode('/', [
			Constants::FILES_FOLDER,
			self::normalizeFileName($form->getId() . ' - ' . $form->getTitle()),
		]);
	}

	public function getUploadedFilePath(Form $form, int $submissionId, int $questionId, ?string $questionName, string $questionText): string {

		return implode('/', [
			$this->getFormUploadedFilesFolderPath($form),
			$submissionId,
			self::normalizeFileName($questionId . ' - ' . ($questionName ?: $questionText))
		]);
	}

	public function getTemporaryUploadedFilePath(Form $form, Question $question): string {
		return implode('/', [
			Constants::UNSUBMITTED_FILES_FOLDER,
			microtime(true),
			self::normalizeFileName($form->getId() . ' - ' . $form->getTitle()),
			self::normalizeFileName($question->getId() . ' - ' . ($question->getName() ?: $question->getText()))
		]);
	}

	private static function normalizeFileName(string $fileName): string {
		return trim(str_replace(Constants::FILENAME_INVALID_CHARS, '-', $fileName));
	}
}
