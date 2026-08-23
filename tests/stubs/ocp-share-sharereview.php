<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// reference for: use OCP\Share\ShareReview\IShareReviewSource;
// Keep in sync with the classes shipped by the Nextcloud server (OCP).

namespace OCP\Share\ShareReview {
	use OCP\EventDispatcher\Event;

	interface IShareReviewSource {
		public function getName(): string;
		public function getShares(): array;
		public function deleteShare(string $shareId): bool;
	}

	class RegisterShareReviewSourceEvent extends Event {
		public function registerSource(string $source): void {
		}
		public function getSources(): array {
			return [];
		}
	}

	final readonly class ShareReviewPermission {
		public function __construct(
			public string $id,
			public string $displayName,
			public ?string $hint = null,
			public int $priority = 50,
		) {
		}
	}

	final readonly class ShareReviewEntry {
		public function __construct(
			public string $id,
			public string $object,
			public string $initiator,
			public int $type,
			public string $recipient,
			public int $lastModifiedTimestamp,
			public array $permissions = [],
			public string $action = '',
			public bool $hasPassword = false,
			public ?int $expirationTimestamp = null,
			public ?string $parent = null,
		) {
		}
	}
}

namespace OCP\Share\ShareReview\Events {
	use OCP\EventDispatcher\Event;

	class ShareReviewAccessCheckEvent extends Event {
		public function __construct(string $sourceName, string $shareId) {
		}
		public function getSourceName(): string {
			return '';
		}
		public function getShareId(): string {
			return '';
		}
		public function grantAccess(): void {
		}
		public function denyAccess(string $reason): void {
		}
		public function isHandled(): bool {
			return false;
		}
		public function isGranted(): bool {
			return false;
		}
		public function getReason(): ?string {
			return null;
		}
	}
}
