<?php

/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;

/**
 * Trait for getting forms information in a service
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
	 * @return array
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
	 * @return array
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
	 * @return array
	 */
	public function getQuestion(int $questionId): array {
		$question = [];
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
		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $question;
		}
	}

	/**
	 * Load shares corresponding to form
	 *
	 * @param integer $formId
	 * @return array
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
	 * @return array
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
		];

		// Append submissionCount if currentUser has permissions to see results
		if (in_array(Constants::PERMISSION_RESULTS, $result['permissions'])) {
			$result['submissionCount'] = $this->submissionMapper->countSubmissions($form->getId());
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
	 * Get current users permissions on a form
	 *
	 * @param Form $form
	 * @return array
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
	 * Can the current user see results of a form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function canSeeResults(Form $form): bool {
		return in_array(Constants::PERMISSION_RESULTS, $this->getPermissions($form));
	}

	/**
	 * Can the current user delete results of a form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function canDeleteResults(Form $form): bool {
		// Check permissions
		if (!in_array(Constants::PERMISSION_RESULTS_DELETE, $this->getPermissions($form))) {
			return false;
		}

		// Do not allow deleting results on archived forms
		return !$this->isFormArchived($form);
	}

	/**
	 * Can the user submit a form
	 *
	 * @param Form $form
	 * @return boolean
	 */
	public function canSubmit(Form $form): bool {
		// We cannot control how many time users can submit if public link / legacyLink available
		if ($this->hasPublicLink($form)) {
			return true;
		}

		// Owner is always allowed to submit
		if ($this->currentUser->getUID() === $form->getOwnerId()) {
			return true;
		}

		// Refuse access, if SubmitMultiple is not set and user already has taken part.
		if (
			!$form->getSubmitMultiple() &&
			$this->submissionMapper->hasFormSubmissionsByUser($form, $this->currentUser->getUID())
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
		$access = $form->getAccess();

		if (isset($access['legacyLink'])) {
			return true;
		}

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

		// Shown if permitall and showntoall are both set.
		if ($form->getAccess()['permitAllUsers'] &&
			$form->getAccess()['showToAllUsers'] &&
			$this->configService->getAllowPermitAll() &&
			$this->configService->getAllowShowToAll()) {

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
	}

	/**
	 * Creates activities for new submissions on a form
	 *
	 * @param Form $form Related Form
	 * @param string $submitter The ID of the user who submitted the form. Can also be our 'anon-user-'-ID
	 */
	public function notifyNewSubmission(Form $form, Submission $submission): void {
		$shares = $this->getShares($form->getId());
		$this->activityManager->publishNewSubmission($form, $submission->getUserId());

		foreach ($shares as $share) {
			if (!in_array(Constants::PERMISSION_RESULTS, $share['permissions'])) {
				continue;
			}

			$this->activityManager->publishNewSharedSubmission($form, $share['shareType'], $share['shareWith'], $submission->getUserId());
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
	public function areExtraSettingsValid(array $extraSettings, string $questionType) {
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

		if ($questionType === Constants::ANSWER_TYPE_MULTIPLE) {
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
		}
		return true;
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
