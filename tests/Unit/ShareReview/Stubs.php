<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Share\ShareReview {

	/**
	 * Runtime stub for servers that do not ship the ShareReview OCP classes yet.
	 * Only loaded when the real classes are not available.
	 */
	interface IShareReviewSource {
		public function getName(): string;

		public function getShares(): array;

		public function deleteShare(string $shareId): bool;
	}

	class RegisterShareReviewSourceEvent extends \OCP\EventDispatcher\Event {
		/** @var array<int, class-string<IShareReviewSource>> */
		private array $sources = [];

		public function registerSource(string $source): void {
			$this->sources[] = $source;
		}

		public function getSources(): array {
			return $this->sources;
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

	class ShareReviewAccessCheckEvent extends \OCP\EventDispatcher\Event {
		private bool $handled = false;
		private bool $granted = false;
		private ?string $reason = null;

		public function __construct(
			private readonly string $sourceName,
			private readonly string $shareId,
		) {
			parent::__construct();
		}

		public function getSourceName(): string {
			return $this->sourceName;
		}

		public function getShareId(): string {
			return $this->shareId;
		}

		public function grantAccess(): void {
			if ($this->handled && !$this->granted) {
				return;
			}
			$this->handled = true;
			$this->granted = true;
		}

		public function denyAccess(string $reason): void {
			$this->handled = true;
			$this->granted = false;
			$this->reason = $reason;
			$this->stopPropagation();
		}

		public function isHandled(): bool {
			return $this->handled;
		}

		public function isGranted(): bool {
			return $this->granted;
		}

		public function getReason(): ?string {
			return $this->reason;
		}
	}
}
