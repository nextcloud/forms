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
		public function registerSource(string $source): void {}
		public function getSources(): array { return []; }
	}


	final class ShareReviewPermission {
		public function __construct(
			public readonly string $id,
			public readonly string $displayName,
			public readonly ?string $hint = null,
			public readonly int $priority = 50,
		) {
		}
	}

	final class ShareReviewEntry {
		public function __construct(
			public readonly string $id,
			public readonly string $object,
			public readonly string $initiator,
			public readonly int $type,
			public readonly string $recipient,
			public readonly int $lastModifiedTimestamp,
			public readonly array $permissions = [],
			public readonly string $action = '',
			public readonly bool $hasPassword = false,
			public readonly ?int $expirationTimestamp = null,
			public readonly ?string $parent = null,
		) {
		}
	}
}

namespace OCP\Share\ShareReview\Events {
	use OCP\EventDispatcher\Event;

	class ShareReviewAccessCheckEvent extends Event {
		public function __construct(string $sourceName, string $shareId) {}
		public function getSourceName(): string { return ''; }
		public function getShareId(): string { return ''; }
		public function grantAccess(): void {}
		public function denyAccess(string $reason): void {}
		public function isHandled(): bool { return false; }
		public function isGranted(): bool { return false; }
		public function getReason(): ?string { return null; }
	}
}
