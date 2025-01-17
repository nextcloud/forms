<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\BackgroundJob;

use OCA\Forms\Db\FormMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

use Psr\Log\LoggerInterface;

class UserDeletedJob extends QueuedJob {

	public function __construct(
		private FormMapper $formMapper,
		ITimeFactory $time,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array $argument
	 */
	public function run($argument): void {
		$ownerId = $argument['owner_id'];
		$this->logger->info('Deleting forms for deleted user {user}', [
			'user' => $ownerId
		]);

		$forms = $this->formMapper->findAllByOwnerId($ownerId);
		foreach ($forms as $form) {
			$this->formMapper->deleteForm($form);
		}
	}
}
