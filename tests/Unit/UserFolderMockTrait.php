<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit;

use OCP\Files\Folder;
use OCP\Files\IUserFolder;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * IRootFolder::getUserFolder() is typed as IUserFolder since Nextcloud 36,
 * older versions expect a Folder. Mock whichever interface exists.
 */
trait UserFolderMockTrait {
	private function createUserFolderMock(): Folder&MockObject {
		$className = interface_exists(IUserFolder::class) ? IUserFolder::class : Folder::class;
		return $this->createMock($className);
	}
}
