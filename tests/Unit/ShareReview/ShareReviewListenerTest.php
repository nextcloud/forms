<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\ShareReview;

use OCA\Forms\ShareReview\ShareReviewListener;
use OCA\Forms\ShareReview\ShareReviewSource;
use OCP\Share\ShareReview\RegisterShareReviewSourceEvent;
use OCP\User\Events\UserCreatedEvent;
use PHPUnit\Framework\TestCase;

final class ShareReviewListenerTest extends TestCase {
	private ShareReviewListener $listener;

	protected function setUp(): void {
		parent::setUp();
		$this->listener = new ShareReviewListener();
	}

	public function testHandleRegistersShareReviewSource(): void {
		$event = $this->createMock(RegisterShareReviewSourceEvent::class);
		$event->expects($this->once())
			->method('registerSource')
			->with(ShareReviewSource::class);

		$this->listener->handle($event);
	}

	public function testHandleIgnoresUnrelatedEvent(): void {
		$event = $this->createMock(UserCreatedEvent::class);

		$this->listener->handle($event);
		$this->addToAssertionCount(1);
	}
}
