<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\ShareReview;

use OCA\Forms\Constants;
use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Helper\FilePathHelper;
use OCP\AppFramework\Db\IMapperException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IShareReviewSource;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use Psr\Log\LoggerInterface;

class ShareReviewSource implements IShareReviewSource {

	public const PERMISSION_READ = 'forms:read';
	public const PERMISSION_EDIT = 'forms:edit';
	public const PERMISSION_SUBMIT = 'forms:submit';
	public const PERMISSION_RESULTS = 'forms:results';
	public const PERMISSION_RESULTS_DELETE = 'forms:results_delete';
	public const PERMISSION_EMBED = 'forms:embed';

	/** @var array<string, ShareReviewPermission>|null */
	private ?array $permissionCatalog = null;

	public function __construct(
		private readonly ShareMapper $shareMapper,
		private readonly FormMapper $formMapper,
		private readonly IManager $shareManager,
		private readonly IRootFolder $rootFolder,
		private readonly FilePathHelper $filePathHelper,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly LoggerInterface $logger,
		private readonly IL10N $l,
	) {
	}

	public function getName(): string {
		return 'Forms';
	}

	/**
	 * @return list<ShareReviewEntry>
	 */
	public function getShares(): array {
		try {
			$rawShares = $this->shareMapper->findAllForShareReview();
		} catch (Exception $e) {
			$this->logger->error('Forms ShareReview: failed to fetch shares: {message}', ['message' => $e->getMessage()]);
			return [];
		}
		return array_map(
			fn (array $share) => $this->buildEntry($share),
			$rawShares,
		);
	}

	public function deleteShare(string $shareId): bool {
		// Digits only, so the ID the access-check event carries is exactly the row being deleted
		// (is_numeric would also accept '1e3' or '7.5', which (int) casts to a different value)
		if (!ctype_digit($shareId)) {
			return false;
		}
		$numericShareId = (int)$shareId;

		$event = new ShareReviewAccessCheckEvent('Forms', (string)$numericShareId);
		$this->eventDispatcher->dispatchTyped($event);

		if (!$event->isHandled() || !$event->isGranted()) {
			return false;
		}

		try {
			$share = $this->shareMapper->findById($numericShareId);
			$form = $this->formMapper->findById($share->getFormId());
		} catch (IMapperException) {
			return false;
		}

		try {
			// Revoke any linked Files share before deleting the Forms share
			if (in_array(Constants::PERMISSION_RESULTS, $share->getPermissions(), true)) {
				$this->removeUploadedFilesShare($form, $share);
			}
			$this->shareMapper->delete($share);
			// Bump the form's last_updated timestamp, matching the regular deletion flow
			$this->formMapper->update($form);
			return true;
		} catch (\Exception $e) {
			$this->logger->error('Forms ShareReview: failed to delete share {id}: {message}', ['id' => $shareId, 'message' => $e->getMessage()]);
			return false;
		}
	}

	/** @param array<string, mixed> $share */
	private function buildEntry(array $share): ShareReviewEntry {
		// last_updated is bumped on every share change of the form, created is the lower bound
		$time = (int)($share['form_last_updated'] ?? 0) ?: (int)($share['form_created'] ?? 0);
		$expires = (int)($share['form_expires'] ?? 0);

		return new ShareReviewEntry(
			id: (string)$share['id'],
			object: $this->resolveObjectName($share),
			initiator: (string)$share['form_owner'],
			type: $this->mapShareType((int)$share['share_type']),
			recipient: (string)$share['share_with'],
			lastModifiedTimestamp: $time,
			permissions: $this->buildPermissions($this->decodePermissions($share)),
			expirationTimestamp: $expires > 0 ? $expires : null,
		);
	}

	/** @param array<string, mixed> $share */
	private function resolveObjectName(array $share): string {
		$title = (string)($share['form_title'] ?? '');
		$formId = (int)($share['form_id'] ?? $share['id']);
		$label = $title !== '' ? $title : $this->l->t('Form %d', [$formId]);
		return $this->l->t('%s (Form)', [$label]);
	}

	private function mapShareType(int $type): int {
		if (in_array($type, Constants::SHARE_TYPES_USED, true)) {
			return $type;
		}
		$this->logger->warning('Forms ShareReview: unknown share type {type}, defaulting to user share', ['type' => $type]);
		return IShare::TYPE_USER;
	}

	/**
	 * @param array<string, mixed> $share
	 * @return list<string>
	 */
	private function decodePermissions(array $share): array {
		// Same fallback to submit permission as OCA\Forms\Db\Share::getPermissions()
		return json_decode((string)($share['permissions_json'] ?? '') ?: 'null', true) ?? [Constants::PERMISSION_SUBMIT];
	}

	/**
	 * @param list<string> $formPermissions
	 * @return list<ShareReviewPermission>
	 */
	private function buildPermissions(array $formPermissions): array {
		$catalog = $this->permissionCatalog();
		// Any share grants seeing the form itself
		$permissions = [$catalog[self::PERMISSION_READ]];
		foreach ([
			Constants::PERMISSION_EDIT => self::PERMISSION_EDIT,
			Constants::PERMISSION_SUBMIT => self::PERMISSION_SUBMIT,
			Constants::PERMISSION_RESULTS => self::PERMISSION_RESULTS,
			Constants::PERMISSION_RESULTS_DELETE => self::PERMISSION_RESULTS_DELETE,
			Constants::PERMISSION_EMBED => self::PERMISSION_EMBED,
		] as $formPermission => $permissionId) {
			if (in_array($formPermission, $formPermissions, true)) {
				$permissions[] = $catalog[$permissionId];
			}
		}
		return $permissions;
	}

	/**
	 * The permission objects are immutable and identical for every share row,
	 * so they are built once per request instead of once per row.
	 *
	 * All permission IDs are namespaced to this app, and labels and hints are
	 * translated from this app's own catalog — the app owning a permission
	 * also owns its wording in every language.
	 *
	 * @return array<string, ShareReviewPermission>
	 */
	private function permissionCatalog(): array {
		return $this->permissionCatalog ??= [
			self::PERMISSION_READ => new ShareReviewPermission(self::PERMISSION_READ, $this->l->t('Read'), priority: 80),
			self::PERMISSION_EDIT => new ShareReviewPermission(self::PERMISSION_EDIT, $this->l->t('Edit form'), priority: 70),
			self::PERMISSION_SUBMIT => new ShareReviewPermission(self::PERMISSION_SUBMIT, $this->l->t('Submit'), $this->l->t('Fill in the form'), 60),
			self::PERMISSION_RESULTS => new ShareReviewPermission(self::PERMISSION_RESULTS, $this->l->t('View results'), priority: 50),
			self::PERMISSION_RESULTS_DELETE => new ShareReviewPermission(self::PERMISSION_RESULTS_DELETE, $this->l->t('Delete results'), priority: 45),
			self::PERMISSION_EMBED => new ShareReviewPermission(self::PERMISSION_EMBED, $this->l->t('Embed'), $this->l->t('Embed the form in external websites'), 35),
		];
	}

	private function removeUploadedFilesShare(Form $form, Share $formShare): void {
		if (!in_array($formShare->getShareType(), [IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_USERGROUP, IShare::TYPE_CIRCLE], true)) {
			return;
		}

		$userFolder = $this->rootFolder->getUserFolder($form->getOwnerId());
		$uploadedFilesFolderPath = $this->filePathHelper->getFormUploadedFilesFolderPath($form);
		try {
			$folder = $userFolder->get($uploadedFilesFolderPath);
		} catch (NotFoundException) {
			return;
		}
		$folderShares = $this->shareManager->getSharesBy($form->getOwnerId(), $formShare->getShareType(), $folder, false, -1);
		foreach ($folderShares as $folderShare) {
			if ($folderShare->getSharedWith() === $formShare->getShareWith()) {
				$this->shareManager->deleteShare($folderShare);
			}
		}
	}
}
