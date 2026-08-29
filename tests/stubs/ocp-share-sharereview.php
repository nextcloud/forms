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

	interface IPaginatedShareReviewSource extends IShareReviewSource {
		public function getDisplayName(): string;
		public function queryShares(ShareReviewQuery $query): ShareReviewPage;
		public function countShares(ShareReviewQuery $query): ShareReviewCounts;
		/** @return array<int, int> */
		public function countSharesByType(ShareReviewQuery $query): array;
		public function getShare(string $shareId): ?ShareReviewEntry;
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

	final class ShareReviewQuery {
		public const SORT_TIME = 'time';
		public const SORT_OBJECT = 'object';
		public const SORT_INITIATOR = 'initiator';
		public const SORT_RECIPIENT = 'recipient';
		public const SORT_TYPE = 'type';
		public const SORTABLE_FIELDS = [self::SORT_TIME, self::SORT_OBJECT, self::SORT_INITIATOR, self::SORT_RECIPIENT, self::SORT_TYPE];
		public const MAX_LIMIT = 500;

		/**
		 * @param self::SORT_* $sortField
		 * @param list<int>|null $shareTypes
		 * @param list<string>|null $objectSearchAny
		 * @param list<string>|null $initiatorIds
		 * @param list<string>|null $recipientIds
		 * @param list<string>|null $permissionIds
		 */
		public function __construct(
			public readonly int $limit = 100,
			public readonly int $offset = 0,
			public readonly ?string $search = null,
			public readonly string $sortField = self::SORT_TIME,
			public readonly bool $sortDescending = true,
			public readonly ?int $modifiedSinceTimestamp = null,
			public readonly ?array $shareTypes = null,
			public readonly ?bool $hasPassword = null,
			public readonly ?bool $hasExpiration = null,
			public readonly ?int $expiresAfterTimestamp = null,
			public readonly ?int $expiresBeforeTimestamp = null,
			public readonly ?string $initiatorSearch = null,
			public readonly ?string $recipientSearch = null,
			public readonly ?string $objectSearch = null,
			public readonly ?array $objectSearchAny = null,
			public readonly ?array $initiatorIds = null,
			public readonly ?array $recipientIds = null,
			public readonly ?array $permissionIds = null,
			public readonly ?array $tokens = null,
		) {
		}
		public function isFiltered(): bool {
			return $this->search !== null
				|| $this->modifiedSinceTimestamp !== null
				|| $this->shareTypes !== null
				|| $this->hasPassword !== null
				|| $this->hasExpiration !== null
				|| $this->expiresAfterTimestamp !== null
				|| $this->expiresBeforeTimestamp !== null
				|| $this->initiatorSearch !== null
				|| $this->recipientSearch !== null
				|| $this->objectSearch !== null
				|| $this->objectSearchAny !== null
				|| $this->initiatorIds !== null
				|| $this->recipientIds !== null
				|| $this->permissionIds !== null
				|| $this->tokens !== null;
		}
	}

	final readonly class ShareReviewCounts {
		public function __construct(
			public int $totalCount,
			public int $filteredCount,
		) {
		}
	}

	final readonly class ShareReviewPage {
		/** @param list<ShareReviewEntry> $entries */
		public function __construct(
			public array $entries,
			public ShareReviewCounts $counts,
		) {
		}
	}
}

namespace OCP\Share\ShareReview\Events {
	use OCP\EventDispatcher\Event;

	class ShareReviewAccessCheckEvent extends Event {
		public const ACTION_DELETE = 'delete';
		public const ACTION_REMEDIATE = 'remediate';
		public const ACTION_RESTORE = 'restore';
		public const SCOPE_OPERATOR = 'operator';
		public const SCOPE_SELF = 'self';

		public function __construct(string $sourceName, string $shareId, string $action = self::ACTION_DELETE, ?string $actingUserId = null, string $scope = self::SCOPE_OPERATOR) {
		}
		public function getSourceName(): string {
			return '';
		}
		public function getShareId(): string {
			return '';
		}
		public function getAction(): string {
			return self::ACTION_DELETE;
		}
		public function getActingUserId(): ?string {
			return null;
		}
		public function getScope(): string {
			return self::SCOPE_OPERATOR;
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
