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

use OCP\Activity\IManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

use Psr\Log\LoggerInterface;

class ActivityManager {
	protected $appName;

	/** @var IManager */
	private $manager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var LoggerInterface */
	private $logger;

	/** @var IUser */
	private $currentUser;

	public function __construct(string $appName,
		IManager $manager,
		IGroupManager $groupManager,
		LoggerInterface $logger,
		IUserSession $userSession) {
		$this->appName = $appName;
		$this->manager = $manager;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->currentUser = $userSession->getUser();
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
			->setAuthor($this->currentUser->getUID())
			->setSubject(ActivityConstants::SUBJECT_NEWSHARE, [
				'userId' => $this->currentUser->getUID(),
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
				->setAuthor($this->currentUser->getUID())
				->setSubject(ActivityConstants::SUBJECT_NEWGROUPSHARE, [
					'userId' => $this->currentUser->getUID(),
					'groupId' => $groupId,
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
	 * @param string $submittorId ID of the User who submitted the form. Can also be our 'anon-user-'-ID
	 */
	public function publishNewSubmission(Form $form, string $submittorId) {
		$event = $this->manager->generateEvent();
		$event->setApp($this->appName)
			->setType(ActivityConstants::TYPE_NEWSUBMISSION)
			->setAffectedUser($form->getOwnerId())
			->setAuthor($submittorId)
			->setSubject(ActivityConstants::SUBJECT_NEWSUBMISSION, [
				'userId' => $submittorId,
				'formTitle' => $form->getTitle(),
				'formHash' => $form->getHash()
			])
			->setObject('form', $form->getId());

		$this->manager->publish($event);
	}
}
