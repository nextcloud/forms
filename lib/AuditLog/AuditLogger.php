<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\AuditLog;

use OCA\Forms\Db\Share;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Share\IShare;

/**
 * Writes entries to the admin audit log
 */
class AuditLogger {
	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * The operation is only passed on to servers whose CriticalActionPerformedEvent supports it
	 *
	 * @param string $message format string with a %s placeholder for each of the first parameters
	 * @param array<string, mixed> $parameters named values for the placeholders of $message, in order, optionally followed by more context
	 */
	public function log(Operation $operation, string $message, array $parameters = []): void {
		$arguments = [$message, $parameters, false];
		if (method_exists(CriticalActionPerformedEvent::class, 'getOperation')) {
			$arguments[] = $operation->value;
		}
		$this->eventDispatcher->dispatchTyped(new CriticalActionPerformedEvent(...$arguments));
	}

	/**
	 * Logs a share with its share ID and form ID as the first parameters.
	 * The token of a link share is stored as its shareWith and is never logged.
	 *
	 * @param array<string, mixed> $extraParameters
	 */
	public function logShare(Operation $operation, string $message, Share $share, array $extraParameters = []): void {
		$this->log($operation, $message, [
			'shareId' => $share->getId(),
			'formId' => $share->getFormId(),
			'shareType' => $share->getShareType(),
			'shareWith' => $share->getShareType() === IShare::TYPE_LINK ? null : $share->getShareWith(),
			'permissions' => $share->getPermissions(),
		] + $extraParameters);
	}
}
