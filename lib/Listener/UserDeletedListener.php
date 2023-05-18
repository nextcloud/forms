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

namespace OCA\Forms\Listener;

use OCA\Forms\BackgroundJob\UserDeletedJob;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {

	/** @var IJobList */
	private $jobList;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IJobList $jobList,
		LoggerInterface $logger) {
		$this->jobList = $jobList;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}

		// Set a Cron-Job to delete the Users Forms.
		$this->jobList->add(UserDeletedJob::class, ['owner_id' => $event->getUser()->getUID()]);
	}
}
