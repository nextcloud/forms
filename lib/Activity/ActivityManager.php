<?php

/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
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

namespace OCA\Forms\Activity;

use OCA\Forms\Db\Form;

use OCA\Forms\Service\CirclesService;
use OCP\Activity\IManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Share\IShare;

class ActivityManager {
	
	public function __construct(
		protected string $appName,
		private ?string $userId,
		private IManager $manager,
		private IGroupManager $groupManager,
		private CirclesService $circlesService,
	) {
	}

	/**
	 * Publish a new-Share Activity
	 * @param Form $form The shared form
	 * @param string $shareeId UserId, the form has been shared to
	 */
	public function publishNewShare(Form $form, string $shareeId) {
		$event = $this->manager->generateEvent();
		$event->setApp($this->appName)
			->setType(ActivityConstants::TYPE_NEWSHARE)
			->setAffectedUser($shareeId)
			->setAuthor($this->userId)
			->setSubject(ActivityConstants::SUBJECT_NEWSHARE, [
				'userId' => $this->userId,
				'formTitle' => $form->getTitle(),
				'formHash' => $form->getHash()
			])
			->setObject('form', $form->getId());

		$this->manager->publish($event);
	}

	/**
	 * Publish a new-GroupShare Activity to each affected user
	 * @param Form $form The shared form
	 * @param string $groupId Group the form has been shared to
	 */
	public function publishNewGroupShare(Form $form, string $groupId) {
		$affectedUsers = $this->groupManager->get($groupId)->getUsers();

		foreach ($affectedUsers as $user) {
			$event = $this->manager->generateEvent();
			$event->setApp($this->appName)
				->setType(ActivityConstants::TYPE_NEWSHARE)
				->setAffectedUser($user->getUID())
				->setAuthor($this->userId)
				->setSubject(ActivityConstants::SUBJECT_NEWGROUPSHARE, [
					'userId' => $this->userId,
					'groupId' => $groupId,
					'formTitle' => $form->getTitle(),
					'formHash' => $form->getHash()
				])
				->setObject('form', $form->getId());

			$this->manager->publish($event);
		}
	}

	/**
	 * Publish a new-CircleShare Activity to each affected user
	 *
	 * @param Form $form The shared form
	 * @param string $circleId Circle the form has been shared to
	 */
	public function publishNewCircleShare(Form $form, string $circleId) {
		$users = $this->circlesService->getCircleUsers($circleId);

		foreach ($users as $user) {
			$event = $this->manager->generateEvent();
			$event->setApp($this->appName)
				->setType(ActivityConstants::TYPE_NEWSHARE)
				->setAffectedUser($user)
				->setAuthor($this->userId)
				->setSubject(ActivityConstants::SUBJECT_NEWCIRCLESHARE, [
					'userId' => $this->userId,
					'circleId' => $circleId,
					'formTitle' => $form->getTitle(),
					'formHash' => $form->getHash()
				])
				->setObject('form', $form->getId());

			$this->manager->publish($event);
		}
	}

	/**
	 * Publish a new-Submission Activity
	 * @param Form $form The affected Form
	 * @param string $submitterID ID of the User who submitted the form. Can also be our 'anon-user-'-ID
	 */
	public function publishNewSubmission(Form $form, string $submitterID) {
		$event = $this->manager->generateEvent();
		$event->setApp($this->appName)
			->setType(ActivityConstants::TYPE_NEWSUBMISSION)
			->setAffectedUser($form->getOwnerId())
			->setAuthor($submitterID)
			->setSubject(ActivityConstants::SUBJECT_NEWSUBMISSION, [
				'userId' => $submitterID,
				'formTitle' => $form->getTitle(),
				'formHash' => $form->getHash()
			])
			->setObject('form', $form->getId());

		$this->manager->publish($event);
	}

	/**
	 * Publish a new-Submission Activity for shared forms
	 *
	 * @param Form $form The affected Form
	 * @param string $submitterID ID of the User who submitted the form. Can also be our 'anon-user-'-ID
	 */
	public function publishNewSharedSubmission(Form $form, int $shareType, string $shareWith, string $submitterID) {
		$users = [];
		switch ($shareType) {
			case IShare::TYPE_USER:
				$users[] = $shareWith;
				break;
			case IShare::TYPE_GROUP:
				$group = $this->groupManager->get($shareWith);
				if ($group !== null) {
					$users = array_map(fn (IUser $user) => $user->getUID(), $group->getUsers());
				}
				break;
			case IShare::TYPE_CIRCLE:
				$users = $this->circlesService->getCircleUsers($shareWith);
				break;
		}

		foreach ($users as $userId) {
			$event = $this->manager->generateEvent();
			$event->setApp($this->appName)
				->setType(ActivityConstants::TYPE_NEWSHAREDSUBMISSION)
				->setAffectedUser($userId)
				->setAuthor($submitterID)
				->setSubject(ActivityConstants::SUBJECT_NEWSUBMISSION, [
					'userId' => $submitterID,
					'formTitle' => $form->getTitle(),
					'formHash' => $form->getHash()
				])
				->setObject('form', $form->getId());

			$this->manager->publish($event);
		}
	}
}
