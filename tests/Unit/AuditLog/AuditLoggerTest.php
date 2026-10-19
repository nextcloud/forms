<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\AuditLog;

use OCA\Forms\AuditLog\AuditLogger;
use OCA\Forms\AuditLog\Operation;
use OCA\Forms\Db\Share;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AuditLoggerTest extends TestCase {
	private IEventDispatcher|MockObject $eventDispatcher;
	private AuditLogger $auditLogger;

	protected function setUp(): void {
		parent::setUp();
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->auditLogger = new AuditLogger($this->eventDispatcher);
	}

	private function expectAuditEvent(string $operation, string $message, array $parameters): void {
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event) use ($operation, $message, $parameters): bool {
				$this->assertTrue(AuditEvents::hasOperation($event, $operation));
				$this->assertSame($message, $event->getLogMessage());
				$this->assertSame($parameters, $event->getParameters());
				$this->assertFalse($event->getObfuscateParameters());
				return true;
			}));
	}

	public function testLog(): void {
		$this->expectAuditEvent('forms.form.deleted', 'Form %s of "%s" was deleted', ['formId' => 1, 'ownerId' => 'alice']);

		$this->auditLogger->log(Operation::FormDeleted, 'Form %s of "%s" was deleted', ['formId' => 1, 'ownerId' => 'alice']);
	}

	public function testLogShare(): void {
		$share = new Share();
		$share->setId(8);
		$share->setFormId(5);
		$share->setShareType(IShare::TYPE_GROUP);
		$share->setShareWith('staff');
		$share->setPermissions(['submit', 'results']);

		$this->expectAuditEvent('forms.share.updated', 'Share %s of form %s was updated', [
			'shareId' => 8,
			'formId' => 5,
			'shareType' => IShare::TYPE_GROUP,
			'shareWith' => 'staff',
			'permissions' => ['submit', 'results'],
			'tokenChanged' => false,
		]);

		$this->auditLogger->logShare(Operation::ShareUpdated, 'Share %s of form %s was updated', $share, ['tokenChanged' => false]);
	}

	public function testLogShareNeverLogsLinkShareToken(): void {
		$share = new Share();
		$share->setId(9);
		$share->setFormId(5);
		$share->setShareType(IShare::TYPE_LINK);
		$share->setShareWith('secretToken123');
		$share->setPermissions(['submit']);

		$this->expectAuditEvent('forms.share.created', 'Share %s of form %s was created', [
			'shareId' => 9,
			'formId' => 5,
			'shareType' => IShare::TYPE_LINK,
			'shareWith' => null,
			'permissions' => ['submit'],
		]);

		$this->auditLogger->logShare(Operation::ShareCreated, 'Share %s of form %s was created', $share);
	}
}
