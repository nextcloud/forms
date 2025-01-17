<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Listener;

use OCA\Forms\BackgroundJob\UserDeletedJob;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/**
 * @implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	public function __construct(
		private IJobList $jobList,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}

		// Set a Cron-Job to delete the Users Forms.
		$this->jobList->add(UserDeletedJob::class, ['owner_id' => $event->getUser()->getUID()]);
	}
}
