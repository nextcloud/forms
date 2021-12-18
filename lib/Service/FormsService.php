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
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Share\IShare;

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

	/** @var IGroupManager */
	private $groupManager;

	/** @var ILogger */
	private $logger;

	/** @var IUser */
	private $currentUser;

	/** @var IUserManager */
	private $userManager;

	public function __construct(ActivityManager $activityManager,
								FormMapper $formMapper,
								OptionMapper $optionMapper,
								QuestionMapper $questionMapper,
								ShareMapper $shareMapper,
								SubmissionMapper $submissionMapper,
								IGroupManager $groupManager,
								ILogger $logger,
								IUserManager $userManager,
								IUserSession $userSession) {
		$this->activityManager = $activityManager;
		$this->formMapper = $formMapper;
		$this->optionMapper = $optionMapper;
		$this->questionMapper = $questionMapper;
		$this->shareMapper = $shareMapper;
		$this->submissionMapper = $submissionMapper;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->userManager = $userManager;

		$this->currentUser = $userSession->getUser();
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
		try {
			$shareEntities = $this->shareMapper->findByForm($formId);
			foreach ($shareEntities as $shareEntity) {
				$share = $shareEntity->read();
				$share['displayName'] = $this->getShareDisplayName($share);
				$shareList[] = $share;
			}
		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $shareList;
		}
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

		// Append canSubmit, to be able to show proper EmptyContent on internal view.
		$result['canSubmit'] = $this->canSubmit($form->getId());

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
	 * Can the user submit a form
	 */
	public function canSubmit($formId) {
		$form = $this->formMapper->findById($formId);
		$access = $form->getAccess();

		// TODO 
		// We cannot control how many time users can submit in public mode / legacyLink
		if (isset($access['legacyLink'])) {
			return true;
		}

		// Owner is always allowed to submit
		if ($this->currentUser->getUID() === $form->getOwnerId()) {
			return true;
		}

		// Refuse access, if SubmitOnce is set and user already has taken part.
		if ($form->getSubmitOnce()) {
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
	 * Check if user has access to this form
	 *
	 * @param integer $formId
	 * @return boolean
	 */
	public function hasUserAccess(int $formId): bool {
		$form = $this->formMapper->findById($formId);
		$access = $form->getAccess();
		$ownerId = $form->getOwnerId();

		// TODO check public access again
		if (isset($access['legacyLink'])) {
			return true;
		}

		// Refuse access, if not public and no user logged in.
		if (!$this->currentUser) {
			return false;
		}

		// Always grant access to owner.
		if ($ownerId === $this->currentUser->getUID()) {
			return true;
		}

		// Now all remaining users are allowed, if permitAll is set.
		if ($access['permitAllUsers']) {
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
		if ($access['permitAllUsers'] && $access['showToAllUsers']) {
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
	 * @param $formId
	 * @return bool
	 */
	public function isSharedToUser(int $formId): bool {
		$shareEntities = $this->shareMapper->findByForm($formId);
		foreach ($shareEntities as $shareEntity) {
			$share = $shareEntity->read();

			// Needs different handling for shareTypes
			switch ($share['shareType']) {
				case IShare::TYPE_USER:
					if ($share['shareWith'] === $this->currentUser->getUID()) {
						return true;
					}
					break;
				case IShare::TYPE_GROUP:
					if ($this->groupManager->isInGroup($this->currentUser->getUID(), $share['shareWith'])) {
						return true;
					}
					break;
				default:
					// Return false below
			}
		}

		// No share found.
		return false;
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
	 * Compares two selected access arrays and creates activities for users.
	 * @param Form $form Related Form
	 * @param array $oldAccess old access-array
	 * @param array $newAccess new access-array
	 */
	public function notifyNewShares(Form $form, array $oldAccess, array $newAccess) {
		$newUsers = array_diff($newAccess['users'], $oldAccess['users']);
		$newGroups = array_diff($newAccess['groups'], $oldAccess['groups']);

		// Create Activities
		foreach ($newUsers as $key => $newUserId) {
			$this->activityManager->publishNewShare($form, $newUserId);
		}
		foreach ($newGroups as $key => $newGroupId) {
			$this->activityManager->publishNewGroupShare($form, $newGroupId);
		}
	}
}
