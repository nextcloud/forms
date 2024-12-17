<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Activity\Settings;

use OCA\Forms\Activity\Settings\NewSharedSubmission;
use OCP\IL10N;

use PHPUnit\Framework\MockObject\MockClass;
use Test\TestCase;

class NewSharedSubmissionTest extends TestCase {
	/** @var IL10N|MockObject */
	private $l10n;

	/** @var NewSharedSubmission|MockClass */
	private $newShare;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->newShare = new NewSharedSubmission('forms', $this->l10n);
	}

	public function testGetGroupIdentifier() {
		$this->assertEquals('forms', $this->newShare->getGroupIdentifier());
	}

	public function testGetGroupName() {
		$this->l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(function ($identity) {
				return $identity;
			}));
		$this->assertEquals('Forms', $this->newShare->getGroupName());
	}

	public function testGetIdentifier() {
		$this->assertEquals('forms_newsharedsubmission', $this->newShare->getIdentifier());
	}

	public function testGetName() {
		$this->l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(function ($identity) {
				return $identity;
			}));
		$this->assertEquals('Someone <strong>answered</strong> a shared form', $this->newShare->getName());
	}

	public function testGetPriority() {
		$this->assertEquals(62, $this->newShare->getPriority());
	}
}
