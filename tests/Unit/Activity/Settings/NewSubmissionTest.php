<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Activity\Settings;

use OCA\Forms\Activity\Settings\NewSubmission;
use OCP\IL10N;
use Test\TestCase;

class NewSubmissionTest extends TestCase {
	private IL10N|MockObject $l10n;
	private NewSubmission $newShare;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->newShare = new NewSubmission('forms', $this->l10n);
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
		$this->assertEquals('forms_newsubmission', $this->newShare->getIdentifier());
	}

	public function testGetName() {
		$this->l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(function ($identity) {
				return $identity;
			}));
		$this->assertEquals('Someone <strong>answered</strong> a form', $this->newShare->getName());
	}

	public function testGetPriority() {
		$this->assertEquals(61, $this->newShare->getPriority());
	}
}
