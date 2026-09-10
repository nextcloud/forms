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
use OCA\Forms\Service\UploadedFilesShareService;
use OCA\Forms\ShareReview\ShareReviewSource;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Share\IShare;
use OCP\Share\ShareReview\Events\ShareReviewAccessCheckEvent;
use OCP\Share\ShareReview\IPaginatedShareReviewSource;
use OCP\Share\ShareReview\IShareReviewSourceSnapshot;
use OCP\Share\ShareReview\ShareReviewActionContext;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewEntry;
use OCP\Share\ShareReview\ShareReviewPermission;
use OCP\Share\ShareReview\ShareReviewQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ShareReviewSourceTest extends TestCase {
	private ShareMapper|MockObject $shareMapper;
	private FormMapper|MockObject $formMapper;
	private UploadedFilesShareService|MockObject $uploadedFilesShareService;
	private IEventDispatcher|MockObject $eventDispatcher;
	private LoggerInterface|MockObject $logger;
	private IL10N|MockObject $l10n;
	private IUserSession|MockObject $userSession;
	private ShareReviewSource $source;
	/** @var list<object> every event the source dispatched */
	private array $dispatched = [];

	protected function setUp(): void {
		$this->shareMapper = $this->createMock(ShareMapper::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->uploadedFilesShareService = $this->createMock(UploadedFilesShareService::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			fn (string $text, array $params = []): string => empty($params) ? $text : vsprintf($text, $params)
		);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->source = new ShareReviewSource(
			$this->shareMapper,
			$this->formMapper,
			$this->uploadedFilesShareService,
			$this->eventDispatcher,
			$this->logger,
			$this->l10n,
			$this->userSession,
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

	public function testGetDisplayNameIsTranslated(): void {
		$this->assertInstanceOf(IPaginatedShareReviewSource::class, $this->source);
		$this->assertSame('Forms', $this->source->getDisplayName());
	}

	public function testGetSharesStreamsTheFullIdOrderedList(): void {
		$this->mockFindAllForShareReview(array_map(fn (int $id) => $this->makeShareRow(['id' => $id]), range(1, ShareReviewQuery::MAX_LIMIT + 1)));

		$shares = $this->source->getShares();

		$this->assertCount(ShareReviewQuery::MAX_LIMIT + 1, $shares);
		$this->assertSame('501', $shares[ShareReviewQuery::MAX_LIMIT]->id);
	}

	/** @param list<array<string, mixed>> $rows */
	private function mockFindAllForShareReview(array $rows): void {
		$this->shareMapper->method('findAllForShareReview')->willReturnCallback(static function () use ($rows): \Generator {
			yield from $rows;
		});
	}

	public function testQuerySharesReturnsPageWithMapperCounts(): void {
		$query = new ShareReviewQuery(limit: 2, offset: 4, search: 'form');
		$counts = new ShareReviewCounts(10, 3);
		$this->shareMapper->expects($this->once())
			->method('findPageForShareReview')
			->with($query, null)
			->willReturn([$this->makeShareRow(['id' => 5]), $this->makeShareRow(['id' => 6])]);
		$this->shareMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, null)
			->willReturn($counts);

		$page = $this->source->queryShares($query);

		$this->assertSame($counts, $page->counts);
		$this->assertSame(['5', '6'], array_map(static fn (ShareReviewEntry $e) => $e->id, $page->entries));
		$this->assertSame('My Form (Form)', $page->entries[0]->object);
	}

	public function testQuerySharesTranslatesPermissionIdsToNativePermissions(): void {
		$query = new ShareReviewQuery(permissionIds: [ShareReviewSource::PERMISSION_RESULTS, 'deck:manage', ShareReviewSource::PERMISSION_EMBED]);
		$this->shareMapper->expects($this->once())
			->method('findPageForShareReview')
			->with($query, ['results', 'embed'])
			->willReturn([]);
		$this->shareMapper->method('countForShareReview')->willReturn(new ShareReviewCounts(0, 0));

		$this->source->queryShares($query);
	}

	public function testQuerySharesForeignPermissionIdsMatchNothing(): void {
		$query = new ShareReviewQuery(permissionIds: ['deck:manage']);
		$this->shareMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, [])
			->willReturn(new ShareReviewCounts(4, 0));

		$this->assertSame(0, $this->source->countShares($query)->filteredCount);
	}

	public function testReadPermissionIsGrantedByEveryShareAndDisablesTheFilter(): void {
		$query = new ShareReviewQuery(permissionIds: [ShareReviewSource::PERMISSION_READ, 'deck:manage']);
		$this->shareMapper->expects($this->once())
			->method('countForShareReview')
			->with($query, null)
			->willReturn(new ShareReviewCounts(4, 4));

		$this->assertSame(4, $this->source->countShares($query)->filteredCount);
	}

	public function testQuerySharesReturnsEmptyPageOnDbException(): void {
		$this->shareMapper->method('findPageForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$page = $this->source->queryShares(new ShareReviewQuery());

		$this->assertSame([], $page->entries);
		$this->assertSame(0, $page->counts->totalCount);
		$this->assertSame(0, $page->counts->filteredCount);
	}

	public function testCountSharesDelegatesToMapper(): void {
		$query = new ShareReviewQuery(hasExpiration: true);
		$counts = new ShareReviewCounts(12, 5);
		$this->shareMapper->expects($this->once())->method('countForShareReview')->with($query, null)->willReturn($counts);
		$this->shareMapper->expects($this->never())->method('findPageForShareReview');

		$this->assertSame($counts, $this->source->countShares($query));
	}

	public function testCountSharesByTypeMapsUnknownNativeTypesToUser(): void {
		$this->shareMapper->method('countByTypeForShareReview')->willReturn([
			IShare::TYPE_LINK => 2,
			IShare::TYPE_USER => 3,
			99 => 1,
		]);

		$counts = $this->source->countSharesByType(new ShareReviewQuery());

		$this->assertSame([IShare::TYPE_LINK => 2, IShare::TYPE_USER => 4], $counts);
	}

	public function testSerializeAndRestoreRoundTripAShare(): void {
		$this->assertInstanceOf(IShareReviewSourceSnapshot::class, $this->source);
		$this->shareMapper->method('findById')->with(7)->willReturn($this->makeShare(7, ['submit', 'results']));

		$snapshot = $this->source->serializeShare('7');

		$this->assertIsString($snapshot);
		$data = json_decode($snapshot, true);
		$this->assertSame(1, $data['v']);
		$this->assertSame(10, $data['formId']);
		$this->assertSame('bob', $data['shareWith']);
		$this->assertSame(['submit', 'results'], $data['permissions']);

		$form = $this->makeForm();
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->formMapper->method('findById')->with(10)->willReturn($form);
		$restored = $this->makeShare(42, ['submit', 'results']);
		$this->shareMapper->expects($this->once())->method('insert')
			->with($this->callback(static fn (Share $s): bool => $s->getFormId() === 10 && $s->getPermissions() === ['submit', 'results']))
			->willReturn($restored);
		// the form timestamp is bumped, as on the regular share path
		$this->formMapper->expects($this->once())->method('update')->with($form);

		$this->assertTrue($this->source->restoreShare($snapshot));
		[$message, $parameters] = $this->auditEntries()[0];
		$this->assertStringContainsString('restored', $message);
		$this->assertSame('42', $parameters[0]);
	}

	public function testSerializeOfAMissingOrNonCanonicalIdIsNull(): void {
		$this->shareMapper->method('findById')->willThrowException(new DoesNotExistException('gone'));

		$this->assertNull($this->source->serializeShare('7'));
		$this->assertNull($this->source->serializeShare('1e3'));
	}

	public function testRestoreRejectsAForeignOrIncompleteSnapshot(): void {
		$this->eventDispatcher->expects($this->never())->method('dispatchTyped');

		$this->assertFalse($this->source->restoreShare('not json'));
		$this->assertFalse($this->source->restoreShare('{"v":99,"formId":10,"shareType":0,"permissions":[]}'));
		$this->assertFalse($this->source->restoreShare('{"v":1,"formId":10}'));
	}

	public function testRestoreDeniedByTheAccessCheckIsAudited(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->denyAccess('not an operator'));
		$this->shareMapper->expects($this->never())->method('insert');

		$this->assertFalse($this->source->restoreShare('{"v":1,"formId":10,"shareType":0,"shareWith":"bob","permissions":["submit"]}'));
		$this->assertStringContainsString('denied', $this->auditEntries()[0][0]);
	}

	public function testRestoreOfAShareWhoseFormIsGoneIsFalse(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->formMapper->method('findById')->willThrowException(new DoesNotExistException('gone'));
		$this->shareMapper->expects($this->never())->method('insert');

		$this->assertFalse($this->source->restoreShare('{"v":1,"formId":10,"shareType":0,"shareWith":"bob","permissions":["submit"]}'));
	}

	public function testRestoreForwardsTheActionContext(): void {
		$captured = null;
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(function (object $event) use (&$captured): void {
			if ($event instanceof ShareReviewAccessCheckEvent) {
				$captured = $event;
				$event->denyAccess('no');
			}
		});

		$this->source->restoreShare('{"v":1,"formId":10,"shareType":0,"shareWith":"bob","permissions":["submit"]}', new ShareReviewActionContext('alice', ShareReviewAccessCheckEvent::SCOPE_SELF));

		$this->assertSame(ShareReviewAccessCheckEvent::ACTION_RESTORE, $captured->getAction());
		$this->assertSame('alice', $captured->getActingUserId());
		$this->assertSame(ShareReviewAccessCheckEvent::SCOPE_SELF, $captured->getScope());
	}

	public function testCountSharesByInitiatorDelegatesWithTheLimit(): void {
		$this->shareMapper->expects($this->once())->method('countByInitiatorForShareReview')
			->with($this->isInstanceOf(ShareReviewQuery::class), 10, null)
			->willReturn(['alice' => 3, 'bob' => 1]);

		$this->assertSame(['alice' => 3, 'bob' => 1], $this->source->countSharesByInitiator(new ShareReviewQuery(), 10));
	}

	public function testCountSharesByInitiatorRejectsAnOutOfRangeLimit(): void {
		$this->shareMapper->expects($this->never())->method('countByInitiatorForShareReview');

		$this->expectException(\InvalidArgumentException::class);
		$this->source->countSharesByInitiator(new ShareReviewQuery(), ShareReviewQuery::MAX_LIMIT + 1);
	}

	public function testCountSharesByInitiatorRejectsZero(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->source->countSharesByInitiator(new ShareReviewQuery(), 0);
	}

	public function testCountSharesByInitiatorDbErrorIsEmpty(): void {
		$this->shareMapper->method('countByInitiatorForShareReview')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertSame([], $this->source->countSharesByInitiator(new ShareReviewQuery(), 5));
	}

	public function testGetShareReturnsEntry(): void {
		$this->shareMapper->expects($this->once())
			->method('findByIdForShareReview')
			->with(7)
			->willReturn($this->makeShareRow(['id' => 7, 'share_type' => IShare::TYPE_LINK, 'share_with' => 'hash']));

		$entry = $this->source->getShare('7');

		$this->assertNotNull($entry);
		$this->assertSame('7', $entry->id);
		$this->assertSame(IShare::TYPE_LINK, $entry->type);
		$this->assertSame('hash', $entry->recipient);
	}

	public function testGetShareUnknownIdReturnsNull(): void {
		$this->shareMapper->method('findByIdForShareReview')->willReturn(null);

		$this->assertNull($this->source->getShare('7'));
	}

	public function testGetShareRejectsNonDigitIds(): void {
		$this->shareMapper->expects($this->never())->method('findByIdForShareReview');

		$this->assertNull($this->source->getShare('1e3'));
		$this->assertNull($this->source->getShare('abc'));
		$this->assertNull($this->source->getShare(''));
	}

	public function testGetSharesEmpty(): void {
		$this->mockFindAllForShareReview([]);

		$this->assertSame([], $this->source->getShares());
	}

	public function testGetSharesUserShare(): void {
		$this->mockFindAllForShareReview([$this->makeShareRow()]);

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
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['share_type' => IShare::TYPE_LINK, 'share_with' => 'publicHash123'])]
		);

		$shares = $this->source->getShares();

		$this->assertSame(IShare::TYPE_LINK, $shares[0]->type);
		$this->assertSame('publicHash123', $shares[0]->recipient);
	}

	public function testGetSharesGroupShare(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['share_type' => IShare::TYPE_GROUP, 'share_with' => 'developers'])]
		);

		$shares = $this->source->getShares();

		$this->assertSame(IShare::TYPE_GROUP, $shares[0]->type);
		$this->assertSame('developers', $shares[0]->recipient);
	}

	public function testGetSharesCircleShare(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['share_type' => IShare::TYPE_CIRCLE, 'share_with' => 'circle-uid'])]
		);

		$this->assertSame(IShare::TYPE_CIRCLE, $this->source->getShares()[0]->type);
	}

	public function testGetSharesUnknownTypeLogsWarningAndFallsBackToUser(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['share_type' => 99])]
		);
		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(IShare::TYPE_USER, $this->source->getShares()[0]->type);
	}

	public function testGetSharesMissingTitleFallback(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['form_id' => 42, 'form_title' => null, 'form_owner' => null])]
		);

		$shares = $this->source->getShares();

		$this->assertSame('Form 42 (Form)', $shares[0]->object);
	}

	public function testGetSharesExpirationFromForm(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['form_expires' => 1800000000])]
		);

		$this->assertSame(1800000000, $this->source->getShares()[0]->expirationTimestamp);
	}

	public function testGetSharesUsesLastUpdatedWhenSet(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['form_created' => 1700000000, 'form_last_updated' => 1800000000])]
		);

		$this->assertSame(1800000000, $this->source->getShares()[0]->lastModifiedTimestamp);
	}

	public function testGetSharesFallsBackToCreatedTime(): void {
		$this->mockFindAllForShareReview(
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
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permissions_json' => null])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_SUBMIT],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsResultsEmitsOwnPermission(): void {
		$this->mockFindAllForShareReview(
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
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permissions_json' => json_encode(['edit'])])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EDIT],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsResultsDelete(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permissions_json' => json_encode(['results_delete'])])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_RESULTS_DELETE],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsEmbed(): void {
		$this->mockFindAllForShareReview(
			[$this->makeShareRow(['permissions_json' => json_encode(['embed'])])]
		);

		$this->assertSame(
			[ShareReviewSource::PERMISSION_READ, ShareReviewSource::PERMISSION_EMBED],
			$this->permissionIds($this->source->getShares()[0]->permissions)
		);
	}

	public function testPermissionsAllCapabilitiesMapOneToOne(): void {
		$this->mockFindAllForShareReview(
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

	/**
	 * Stand in for the listener: decide the access-check event, let the audit
	 * events the source dispatches around it through, and record everything.
	 */
	private function decideAccessCheck(callable $decide): void {
		$this->dispatched = [];
		$this->eventDispatcher->method('dispatchTyped')->willReturnCallback(
			function (object $event) use ($decide): void {
				$this->dispatched[] = $event;
				if ($event instanceof ShareReviewAccessCheckEvent) {
					$decide($event);
				}
			}
		);
	}

	/** @return list<array{0: string, 1: array}> message and parameters of every audit entry */
	private function auditEntries(): array {
		$entries = [];
		foreach ($this->dispatched as $event) {
			if ($event instanceof CriticalActionPerformedEvent) {
				$entries[] = [$event->getLogMessage(), $event->getParameters()];
			}
		}
		return $entries;
	}

	public function testDeleteShareEventCarriesCanonicalShareIdAndOperatorDefaults(): void {
		$this->decideAccessCheck(static function (): void {
			// leave unhandled — default-deny stops the flow after the capture
		});

		$this->assertFalse($this->source->deleteShare('007'));
		$event = $this->dispatched[0];
		$this->assertInstanceOf(ShareReviewAccessCheckEvent::class, $event);
		$this->assertSame('7', $event->getShareId());
		$this->assertSame(ShareReviewAccessCheckEvent::ACTION_DELETE, $event->getAction());
		$this->assertNull($event->getActingUserId());
		$this->assertSame(ShareReviewAccessCheckEvent::SCOPE_OPERATOR, $event->getScope());
	}

	public function testDeleteShareForwardsTheActionContextIntoTheEvent(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->denyAccess('not the initiator'));

		$this->source->deleteShare('7', new ShareReviewActionContext('alice', ShareReviewAccessCheckEvent::SCOPE_SELF));

		$event = $this->dispatched[0];
		$this->assertInstanceOf(ShareReviewAccessCheckEvent::class, $event);
		$this->assertSame('alice', $event->getActingUserId());
		$this->assertSame(ShareReviewAccessCheckEvent::SCOPE_SELF, $event->getScope());
	}

	public function testDeleteShareEventNotHandledReturnsFalseAndAuditsTheDenial(): void {
		$this->decideAccessCheck(static function (): void {
		});
		$this->shareMapper->expects($this->never())->method('findById');
		$this->shareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->source->deleteShare('7'));
		$this->assertCount(1, $this->auditEntries());
		[$message, $parameters] = $this->auditEntries()[0];
		$this->assertStringContainsString('denied', $message);
		$this->assertSame(['7', ''], $parameters);
	}

	public function testDeleteShareEventDeniedReturnsFalse(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->denyAccess('not in group'));
		$this->shareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->source->deleteShare('7'));
		$this->assertCount(1, $this->auditEntries());
	}

	public function testDeleteShareNotFoundReturnsFalseWithoutAuditingADeletion(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->shareMapper->method('findById')->willThrowException(new DoesNotExistException('not found'));
		$this->shareMapper->expects($this->never())->method('delete');

		$this->assertFalse($this->source->deleteShare('7'));
		$this->assertSame([], $this->auditEntries());
	}

	public function testDeleteShareGrantedDeletesShareBumpsFormAndAudits(): void {
		$share = $this->makeShare();
		$form = $this->makeForm();
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->userSession->method('getUser')->willReturn($user);
		$this->shareMapper->method('findById')->with(7)->willReturn($share);
		$this->formMapper->method('findById')->with(10)->willReturn($form);
		$this->shareMapper->expects($this->once())->method('delete')->with($share);
		$this->formMapper->expects($this->once())->method('update')->with($form);
		// No results permission — the uploaded-files share must not be touched
		$this->uploadedFilesShareService->expects($this->never())->method('removeForCollaborator');

		$this->assertTrue($this->source->deleteShare('7'));
		[$message, $parameters] = $this->auditEntries()[0];
		$this->assertStringContainsString('deleted', $message);
		$this->assertSame(['7', '', IShare::TYPE_USER, 'bob', 'admin'], $parameters);
	}

	public function testDeleteShareAuditsTheActingUserOfTheContextInsteadOfTheSession(): void {
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->shareMapper->method('findById')->willReturn($this->makeShare());
		$this->formMapper->method('findById')->willReturn($this->makeForm());
		$this->userSession->expects($this->never())->method('getUser');

		$this->assertTrue($this->source->deleteShare('7', new ShareReviewActionContext('alice')));
		$this->assertSame('alice', $this->auditEntries()[0][1][4]);
	}

	public function testDeleteShareWithResultsPermissionRevokesUploadedFilesShare(): void {
		$share = $this->makeShare(7, ['results']);
		$form = $this->makeForm();
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->shareMapper->method('findById')->willReturn($share);
		$this->formMapper->method('findById')->willReturn($form);
		$this->uploadedFilesShareService->expects($this->once())
			->method('removeForCollaborator')
			->with($form, $share);
		$this->shareMapper->expects($this->once())->method('delete')->with($share);

		$this->assertTrue($this->source->deleteShare('7'));
	}

	public function testDeleteShareDbErrorReturnsFalseAndAuditsNoDeletion(): void {
		$share = $this->makeShare();
		$form = $this->makeForm();
		$this->decideAccessCheck(static fn (ShareReviewAccessCheckEvent $event) => $event->grantAccess());
		$this->shareMapper->method('findById')->willReturn($share);
		$this->formMapper->method('findById')->willReturn($form);
		$this->shareMapper->method('delete')->willThrowException($this->createMock(Exception::class));
		$this->logger->expects($this->once())->method('error');

		$this->assertFalse($this->source->deleteShare('7'));
		$this->assertSame([], $this->auditEntries());
	}
}
