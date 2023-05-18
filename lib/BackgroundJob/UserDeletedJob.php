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

namespace OCA\Forms\BackgroundJob;

use OCA\Forms\Db\FormMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

use Psr\Log\LoggerInterface;

class UserDeletedJob extends QueuedJob {

	/** @var FormMapper */
	private $formMapper;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(FormMapper $formMapper,
		ITimeFactory $time,
		LoggerInterface $logger) {
		parent::__construct($time);

		$this->formMapper = $formMapper;
		$this->logger = $logger;
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
