<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\ShareReview;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Db\Share;
use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Helper\FilePathHelper;
use OCA\Forms\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private MockObject $shareMapper;
	private MockObject $formMapper;
	private MockObject $shareManager;
	private MockObject $rootFolder;
	private MockObject $filePathHelper;
	private MockObject $eventDispatcher;
	private MockObject $logger;
	private MockObject $l;
	private ShareReviewSource $source;

	protected function setUp(): void {
		parent::setUp();
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->shareManager = $this->createMock(IManager::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->filePathHelper = $this->createMock(FilePathHelper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')->willReturnCallback(
			function (string $text, array $params = []): string {
				return empty($params) ? $text : vsprintf($text, $params);
			}
		);
		$this->source = new ShareReviewSource(
			$this->shareMapper,
			$this->formMapper,
			$this->shareManager,
			$this->rootFolder,
			$this->filePathHelper,
			$this->eventDispatcher,
			$this->logger,
			$this->l,
		);
	}

	/** @param array<string, mixed> $overrides */
	private function makeShareRow(array $overrides = []): array {
		return array_merge([
			'id' => 1,
			'form_id' => 10,
			'share_type' => IShare::TYPE_USER,
			'share_with' => 'bob',
			'permissions_json' => json_encode(['submit']),
			'form_title' => 'My Form',
			'form_owner' => 'alice',
			'form_created' => 1700000000,
			'form_last_updated' => 1700000000,
			'form_expires' => 0,
		], $overrides);
	}

	private function makeShare(int $id = 7, array $permissions = ['submit'], int $shareType = IShare::TYPE_USER): Share {
		$share = new Share();
		$share->setId($id);
		$share->setFormId(10);
		$share->setShareType($shareType);
		$share->setShareWith('bob');
		$share->setPermissions($permissions);
		return $share;
	}

	private function makeForm(): Form {
		$form = new Form();
		$form->setId(10);
		$form->setOwnerId('alice');
		return $form;
	}

	public function testGetName(): void {
		$this->assertSame('Forms', $this->source->getName());
	}

	public function testGetSharesEmpty(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn([]);

		$this->assertSame([], $this->source->getShares());
	}

	public function testGetSharesUserShare(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn([$this->makeShareRow()]);

		$shares = $this->source->getShares();

		$this->assertCount(1, $shares);
		$share = $shares[0];
		$this->assertInstanceOf(ShareReviewEntry::class, $share);
		$this->assertSame('1', $share->id);
		$this->assertSame('My Form (Form)', $share->object);
		$this->assertSame('alice', $share->initiator);
		$this->assertSame(IShare::TYPE_USER, $share->type);
		$this->assertSame('bob', $share->recipient);
		$this->assertSame([ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_SUBMIT], $this->permissionIds($share->permissions));
		$this->assertFalse($share->hasPassword);
		$this->assertSame(1700000000, $share->lastModifiedTimestamp);
		$this->assertNull($share->expirationTimestamp);
		$this->assertSame('', $share->action);
	}

	public function testGetSharesLinkShare(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['share_type' => IShare::TYPE_LINK, 'share_with' => 'publicHash123'])]
		);

		$shares = $this->source->getShares();

		$this->assertSame(IShare::TYPE_LINK, $shares[0]->type);
		$this->assertSame('publicHash123', $shares[0]->recipient);
	}

	public function testGetSharesGroupShare(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['share_type' => IShare::TYPE_GROUP, 'share_with' => 'developers'])]
		);

		$shares = $this->source->getShares();

		$this->assertSame(IShare::TYPE_GROUP, $shares[0]->type);
		$this->assertSame('developers', $shares[0]->recipient);
	}

	public function testGetSharesCircleShare(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['share_type' => IShare::TYPE_CIRCLE, 'share_with' => 'circle-uid'])]
		);

		$this->assertSame(IShare::TYPE_CIRCLE, $this->source->getShares()[0]->type);
	}

	public function testGetSharesUnknownTypeLogsWarningAndFallsBackToUser(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['share_type' => 99])]
		);
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(IShare::TYPE_USER, $this->source->getShares()[0]->type);
	}

	public function testGetSharesMissingTitleFallback(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['form_id' => 42, 'form_title' => null, 'form_owner' => null])]
		);

		$shares = $this->source->getShares();

		$this->assertSame('Form 42 (Form)', $shares[0]->object);
	}

	public function testGetSharesExpirationFromForm(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['form_expires' => 1800000000])]
		);

		$this->assertSame(1800000000, $this->source->getShares()[0]->expirationTimestamp);
	}

	public function testGetSharesUsesLastUpdatedWhenSet(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['form_created' => 1700000000, 'form_last_updated' => 1800000000])]
		);

		$this->assertSame(1800000000, $this->source->getShares()[0]->lastModifiedTimestamp);
	}

	public function testGetSharesFallsBackToCreatedTime(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['form_created' => 1700000000, 'form_last_updated' => 0])]
		);

		$this->assertSame(1700000000, $this->source->getShares()[0]->lastModifiedTimestamp);
	}

	public function testGetSharesReturnsEmptyOnDbException(): void {
		$this->shareMapper->method('findAllForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->getShares());
	}

	public function testPermissionsDefaultToSubmit(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permissions_json' => null])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_SUBMIT],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsResultsEmitsOwnPermission(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permissions_json' => json_encode(['results'])])]
		);

		$permissions = $this->source->getShares()[0]->permissions;
		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_RESULTS],
			$this->permissionIds($permissions)
		);
		$this->assertSame('View results', $permissions[1]->displayName);
	}

	public function testPermissionsEdit(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permissions_json' => json_encode(['edit'])])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EDIT],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsResultsDelete(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permissions_json' => json_encode(['results_delete'])])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_RESULTS_DELETE],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsEmbed(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permissions_json' => json_encode(['embed'])])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EMBED],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsAllCapabilitiesMapOneToOne(): void {
		$this->shareMapper->method('findAllForShareReview')->willReturn(
			[$this->makeShareRow(['permissions_json' => json_encode(['edit', 'embed', 'results', 'results_delete', 'submit'])])]
		);

		$this->assertSame(
			[
				ShareReviewSource::PERMISSION_READ,
				ShareReviewSource::PERMISSION_EDIT,
				ShareReviewSource::PERMISSION_SUBMIT,
				ShareReviewSource::PERMISSION_RESULTS,
				ShareReviewSource::PERMISSION_RESULTS_DELETE,
				ShareReviewSource::PERMISSION_EMBED,
			],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionIdentifiers(): void {
		$this->assertSame('forms:read', ShareReviewSource::PERMISSION_READ);
		$this->assertSame('forms:edit', ShareReviewSource::PERMISSION_EDIT);
		$this->assertSame('forms:submit', ShareReviewSource::PERMISSION_SUBMIT);
		$this->assertSame('forms:results', ShareReviewSource::PERMISSION_RESULTS);
		$this->assertSame('forms:results_delete', ShareReviewSource::PERMISSION_RESULTS_DELETE);
		$this->assertSame('forms:embed', ShareReviewSource::PERMISSION_EMBED);
	}

	/**
	 * @param list<ShareReviewPermission> $permissions
	 * @return list<string>
	 */
	private function permissionIds(array $permissions): array {
		return array_map(static fn (ShareReviewPermission $permission): string => $permission->id, $permissions);
	}

	public function testDeleteShareNonNumericReturnsFalse(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->deleteShare('abc'));
	}

	public function testDeleteShareRejectsNonDigitNumericForms(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');
		$this->shareMapper->expects($this->never())->method('findById');

		// is_numeric-style inputs whose (int) cast differs from the literal string
		$this->assertFalse($this->source->deleteShare('1e3'));
		$this->assertFalse($this->source->deleteShare('7.5'));
		$this->assertFalse($this->source->deleteShare('-1'));
		$this->assertFalse($this->source->deleteShare(' 7'));
		$this->assertFalse($this->source->deleteShare(''));
	}

	public function testDeleteShareEventCarriesCanonicalShareId(): void {
		$capturedShareId = null;
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event) use (&$capturedShareId): void {
				$capturedShareId = $event->getShareId();
				// leave unhandled — default-deny stops the flow after the capture
			});

		$this->assertFalse($this->source->deleteShare('007'));
		$this->assertSame('7', $capturedShareId);
	}

	public function testDeleteShareEventNotHandledReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ShareReviewAccessCheckEvent::class));
		$this->shareMapper->expects($this->never())->method('findById');
		$this->shareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareEventDeniedReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->denyAccess('not in group');
			});
		$this->shareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareNotFoundReturnsFalse(): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareMapper->method('findById')->willThrowException(new DoesNotExistException('not found'));
		$this->shareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->source->deleteShare('7'));
	}

	public function testDeleteShareGrantedDeletesShareAndBumpsForm(): void {
		$share = $this->makeShare();
		$form = $this->makeForm();
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareMapper->method('findById')->with(7)->willReturn($share);
		$this->formMapper->method('findById')->with(10)->willReturn($form);
		$this->shareMapper->expects($this->once())->method('delete')->with($share);
		$this->formMapper->expects($this->once())->method('update')->with($form);
		// No results permission — the uploaded-files share must not be touched
		$this->shareManager->expects($this->never())->method('getSharesBy');

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareWithResultsPermissionRevokesUploadedFilesShare(): void {
		$share = $this->makeShare(7, ['results']);
		$form = $this->makeForm();
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareMapper->method('findById')->willReturn($share);
		$this->formMapper->method('findById')->willReturn($form);

		$folder = $this->createMock(Folder::class);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->with('/Forms/uploads')->willReturn($folder);
		$this->rootFolder->method('getUserFolder')->with('alice')->willReturn($userFolder);
		$this->filePathHelper->method('getFormUploadedFilesFolderPath')->with($form)->willReturn('/Forms/uploads');

		$matchingFolderShare = $this->createMock(IShare::class);
		$matchingFolderShare->method('getSharedWith')->willReturn('bob');
		$otherFolderShare = $this->createMock(IShare::class);
		$otherFolderShare->method('getSharedWith')->willReturn('carol');
		$this->shareManager->method('getSharesBy')
			->with('alice', IShare::TYPE_USER, $folder, false, -1)
			->willReturn([$matchingFolderShare, $otherFolderShare]);
		$this->shareManager->expects($this->once())->method('deleteShare')->with($matchingFolderShare);
		$this->shareMapper->expects($this->once())->method('delete')->with($share);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareWithResultsPermissionOnLinkShareSkipsFilesShare(): void {
		$share = $this->makeShare(7, ['results'], IShare::TYPE_LINK);
		$form = $this->makeForm();
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareMapper->method('findById')->willReturn($share);
		$this->formMapper->method('findById')->willReturn($form);
		$this->rootFolder->expects($this->never())->method('getUserFolder');
		$this->shareMapper->expects($this->once())->method('delete')->with($share);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareUploadedFilesFolderMissingStillDeletes(): void {
		$share = $this->makeShare(7, ['results']);
		$form = $this->makeForm();
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareMapper->method('findById')->willReturn($share);
		$this->formMapper->method('findById')->willReturn($form);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->willThrowException(new NotFoundException());
		$this->rootFolder->method('getUserFolder')->willReturn($userFolder);
		$this->filePathHelper->method('getFormUploadedFilesFolderPath')->willReturn('/Forms/uploads');
		$this->shareManager->expects($this->never())->method('getSharesBy');
		$this->shareMapper->expects($this->once())->method('delete')->with($share);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareDbErrorReturnsFalse(): void {
		$share = $this->makeShare();
		$form = $this->makeForm();
		$this->eventDispatcher->method('dispatchTyped')
			->willReturnCallback(function (ShareReviewAccessCheckEvent $event): void {
				$event->grantAccess();
			});
		$this->shareMapper->method('findById')->willReturn($share);
		$this->formMapper->method('findById')->willReturn($form);
		$this->shareMapper->method('delete')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->deleteShare('7'));
	}
}
