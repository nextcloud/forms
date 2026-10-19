<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\AuditLog;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;

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
}
