<?php

declare(strict_types=1);

/*!
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Exception;

use OCP\AppFramework\Http;

class NoSuchFormException extends \Exception {

	public function __construct($message = '', int $errorCode = Http::STATUS_NOT_FOUND) {
		parent::__construct($message, $errorCode);
	}

}
