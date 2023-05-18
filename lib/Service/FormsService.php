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
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Security\ISecureRandom;
use OCP\Share\IShare;

use Psr\Log\LoggerInterface;

/**
 * Trait for getting forms information in a service
 */
class FormsService {

	/** @var ActivityManager */
	private $activityManager;
	
	/** @var FormMapper */
	private $formMapper;

	/** @var OptionMapper */
	private $optionMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var ShareMapper */
	private $shareMapper;

	/** @var SubmissionMapper */
	private $submissionMapper;

	/** @var ConfigService */
	private $configService;

	/** @var IGroupManager */
	private $groupManager;

	/** @var LoggerInterface */
	private $logger;

	/** @var IUser */
	private $currentUser;

	/** @var IUserManager */
	private $userManager;

	/** @var ISecureRandom */
	private $secureRandom;

	public function __construct(ActivityManager $activityManager,
		FormMapper $formMapper,
		OptionMapper $optionMapper,
		QuestionMapper $questionMapper,
		ShareMapper $shareMapper,
		SubmissionMapper $submissionMapper,
		ConfigService $configService,
		IGroupManager $groupManager,
		LoggerInterface $logger,
		IUserManager $userManager,
		IUserSession $userSession,
		ISecureRandom $secureRandom) {
		$this->activityManager = $activityManager;
		$this->formMapper = $formMapper;
		$this->optionMapper = $optionMapper;
		$this->questionMapper = $questionMapper;
		$this->shareMapper = $shareMapper;
		$this->submissionMapper = $submissionMapper;
		$this->configService = $configService;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->secureRandom = $secureRandom;

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
	public function getOptions(int $questionId): array {
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
				$questionList[] = $question;
			}
		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $questionList;
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
	 * @param integer $id
	 * @return array
	 * @throws IMapperException
	 */
	public function getForm(int $id): array {
		$form = $this->formMapper->findById($id);
		$result = $form->read();
		$result['questions'] = $this->getQuestions($id);
		$result['shares'] = $this->getShares($id);

		// Append permissions for current user.
		$result['permissions'] = $this->getPermissions($id);
		// Append canSubmit, to be able to show proper EmptyContent on internal view.
		$result['canSubmit'] = $this->canSubmit($form->getId());

		// Append submissionCount if currentUser has permissions to see results
		if (in_array(Constants::PERMISSION_RESULTS, $result['permissions'])) {
			$result['submissionCount'] = $this->submissionMapper->countSubmissions($id);
		}

		return $result;
	}

	/**
	 * Create partial form, as returned by Forms-Lists.
	 *
	 * @param integer $id
	 * @return array
	 * @throws IMapperException
	 */
	public function getPartialFormArray(int $id): array {
		$form = $this->formMapper->findById($id);

		$result = [
			'id' => $form->getId(),
			'hash' => $form->getHash(),
			'title' => $form->getTitle(),
			'expires' => $form->getExpires(),
			'lastUpdated' => $form->getLastUpdated(),
			'permissions' => $this->getPermissions($form->getId()),
			'partial' => true
		];

		// Append submissionCount if currentUser has permissions to see results
		if (in_array(Constants::PERMISSION_RESULTS, $result['permissions'])) {
			$result['submissionCount'] = $this->submissionMapper->countSubmissions($id);
		}

		return $result;
	}

	/**
	 * Get a form data without sensitive informations
	 *
	 * @param integer $id
	 * @return array
	 * @throws IMapperException
	 */
	public function getPublicForm(int $id): array {
		$form = $this->getForm($id);

		// Remove sensitive data
		unset($form['access']);
		unset($form['ownerId']);
		unset($form['shares']);

		return $form;
	}

	/**
	 * Get current users permissions on a form
	 *
	 * @param integer $formId
	 * @return array
	 */
	public function getPermissions(int $formId): array {
		$form = $this->formMapper->findById($formId);

		if (!$this->currentUser) {
			return [];
		}

		// Owner is allowed to do everything
		if ($this->currentUser->getUID() === $form->getOwnerId()) {
			return Constants::PERMISSION_ALL;
		}

		$permissions = [];
		$shares = $this->getSharesWithUser($formId, $this->currentUser->getUID());
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
	 * Can the current user see results of a form
	 *
	 * @param int $formId
	 * @return boolean
	 */
	public function canSeeResults(int $formId): bool {
		return in_array(Constants::PERMISSION_RESULTS, $this->getPermissions($formId));
	}

	/**
	 * Can the user submit a form
	 *
	 * @param integer $formId
	 * @return boolean
	 */
	public function canSubmit(int $formId): bool {
		$form = $this->formMapper->findById($formId);

		// We cannot control how many time users can submit if public link / legacyLink available
		if ($this->hasPublicLink($formId)) {
			return true;
		}

		// Owner is always allowed to submit
		if ($this->currentUser->getUID() === $form->getOwnerId()) {
			return true;
		}

		// Refuse access, if SubmitMultiple is not set and user already has taken part.
		if (!$form->getSubmitMultiple()) {
			$participants = $this->submissionMapper->findParticipantsByForm($form->getId());
			foreach ($participants as $participant) {
				if ($participant === $this->currentUser->getUID()) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Searching Shares for public link
	 *
	 * @param integer $formId
	 * @return boolean
	 */
	public function hasPublicLink(int $formId): bool {
		$form = $this->formMapper->findById($formId);
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
	 * @param integer $formId
	 * @return boolean
	 */
	public function hasUserAccess(int $formId): bool {
		$form = $this->formMapper->findById($formId);
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
		if ($this->isSharedToUser($formId)) {
			return true;
		}

		// None of the possible access-options matched.
		return false;
	}

	/**
	 * Is the form shown on sidebar to the user.
	 *
	 * @param int $formId
	 * @return bool
	 */
	public function isSharedFormShown(int $formId): bool {
		$form = $this->formMapper->findById($formId);
		$access = $form->getAccess();

		// Dont show here to owner, as its in the owned list anyways.
		if ($form->getOwnerId() === $this->currentUser->getUID()) {
			return false;
		}

		// Dont show expired forms.
		if ($this->hasFormExpired($form->getId())) {
			return false;
		}

		// Shown if permitall and showntoall are both set.
		if ($access['permitAllUsers'] &&
			$access['showToAllUsers'] &&
			$this->configService->getAllowPermitAll()) {
			return true;
		}

		// Shown if user in List of Shared Users/Groups
		if ($this->isSharedToUser($formId)) {
			return true;
		}

		// No Reason found to show form.
		return false;
	}

	/**
	 * Checking all selected shares
	 *
	 * @param int $formId
	 * @return bool
	 */
	public function isSharedToUser(int $formId): bool {
		$shareEntities = $this->getSharesWithUser($formId, $this->currentUser->getUID());
		return count($shareEntities) > 0;
	}

	/*
	 * Has the form expired?
	 *
	 * @param int $formId The id of the form to check.
	 * @return boolean
	 */
	public function hasFormExpired(int $formId): bool {
		$form = $this->formMapper->findById($formId);
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
			default:
				// Do nothing.
		}
	}

	/**
	 * Return shares of a form shared with given user
	 *
	 * @param int $formId The form to query shares for
	 * @param string $userId The user to check if shared with
	 * @return array
	 */
	protected function getSharesWithUser(int $formId, string $userId): array {
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
				default:
					return false;
			}
		});
	}

	/**
	 * Update lastUpdated timestamp for the given form
	 *
	 * @param int $formId The form to update
	 */
	public function setLastUpdatedTimestamp(int $formId): void {
		$form = $this->formMapper->findById($formId);
		$form->setLastUpdated(time());
		$this->formMapper->update($form);
	}
}
