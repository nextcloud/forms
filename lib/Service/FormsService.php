<?php
/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Forms\Service;

use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\OptionMapper;
use OCA\Forms\Db\QuestionMapper;
use OCA\Forms\Db\SubmissionMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\IMapperException;
use OCP\IGroupManager;
use OCP\IUserSession;

/**
 * Trait for getting forms information in a service
 */
class FormsService {
	
	/** @var FormMapper */
	private $formMapper;

	/** @var QuestionMapper */
	private $questionMapper;

	/** @var OptionMapper */
	private $optionMapper;

	/** @var SubmissionMapper */
	private $submissionMapper;
	
	/** @var IGroupManager */
	private $groupManager;

	/** @var IUserSession */
	private $userSession;

	public function __construct(FormMapper $formMapper,
								QuestionMapper $questionMapper,
								OptionMapper $optionMapper,
								SubmissionMapper $submissionMapper,
								IGroupManager $groupManager,
								IUserSession $userSession) {
		$this->formMapper = $formMapper;
		$this->questionMapper = $questionMapper;
		$this->optionMapper = $optionMapper;
		$this->submissionMapper = $submissionMapper;
		$this->groupManager = $groupManager;
		$this->userSession = $userSession;
	}


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

	public function getQuestions(int $formId): array {
		$questionList = [];
		try {
			$questionEntities = $this->questionMapper->findByForm($formId);
			foreach ($questionEntities as $questionEntity) {
				$question = $questionEntity->read();
				$question['options'] = $this->getOptions($question['id']);
				$questionList[] =  $question;
			}
		} catch (DoesNotExistException $e) {
			//handle silently
		} finally {
			return $questionList;
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

		return $form;
	}

	/**
	 * Can the user submit a form
	 */
	public function canSubmit($formId) {
		$form = $this->formMapper->findById($formId);
		$access = $form->getAccess();
		$user = $this->userSession->getUser();

		// We cannot control how many time users can submit in public mode
		if ($access['type'] === 'public') {
			return true;
		}

		// Refuse access, if SubmitOnce is set and user already has taken part.
		if ($form->getSubmitOnce()) {
			$participants = $this->submissionMapper->findParticipantsByForm($form->getId());
			foreach ($participants as $participant) {
				if ($participant === $user->getUID()) {
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
		$user = $this->userSession->getUser();

		if ($access['type'] === 'public') {
			return true;
		}
		
		// Refuse access, if not public and no user logged in.
		if (!$user) {
			return false;
		}

		// Always grant access to owner.
		if ($ownerId === $user->getUID()) {
			return true;
		}

		// Now all remaining users are allowed, if access-type 'registered'.
		if ($access['type'] === 'registered') {
			return true;
		}

		// Selected Access remains.
		// Grant Access, if user is in users-Array.
		if (in_array($user->getUID(), $access['users'])) {
			return true;
		}

		// Check if access granted by group.
		foreach ($access['groups'] as $group) {
			if ($this->groupManager->isInGroup($user->getUID(), $group)) {
				return true;
			}
		}

		// None of the possible access-options matched.
		return false;
	}
}
