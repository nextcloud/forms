<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\AppInfo;

use OCA\Forms\AppInfo\Application;
use OCA\Forms\ShareReview\ShareReviewListener;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Share\ShareReview\RegisterShareReviewSourceEvent;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
	public function testRegisterRegistersEventListeners(): void {
		$registeredListeners = [];

		$context = $this->createMock(IRegistrationContext::class);
		$context->method('registerEventListener')
			->willReturnCallback(function (string $event, string $listener) use (&$registeredListeners): void {
				$registeredListeners[$event] = $listener;
			});

		(new Application())->register($context);

		$this->assertSame(
			ShareReviewListener::class,
			$registeredListeners[RegisterShareReviewSourceEvent::class] ?? null,
		);
	}
}
