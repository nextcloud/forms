<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Activity\Settings;

use OCA\Forms\Activity\Settings\FormsActivitySettings;
use OCP\IL10N;
use Test\TestCase;

class SettingsTestClass extends FormsActivitySettings {
	// Two abstract methods exist, that need to be implemented to use the class.
	public function getIdentifier() {
	}
	public function getName() {
	}
}

class FormsActivitySettingsTest extends TestCase {
	private IL10N|MockObject $l10n;
	private SettingsTestClass $activitySettings;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->activitySettings = new SettingsTestClass('forms', $this->l10n);
	}

	public function testGetGroupIdentifier() {
		$this->assertEquals('forms', $this->activitySettings->getGroupIdentifier());
	}

	public function testGetGroupName() {
		$this->l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(fn ($identity) => $identity));
		$this->assertEquals('Forms', $this->activitySettings->getGroupName());
	}

	public function testGetPriority() {
		$this->assertEquals(60, $this->activitySettings->getPriority());
	}

	public function testCanChangeNotification() {
		$this->assertEquals(true, $this->activitySettings->canChangeNotification());
	}

	public function testIsDefaultEnabledNotification() {
		$this->assertEquals(true, $this->activitySettings->isDefaultEnabledNotification());
	}

	public function testCanChangeMail() {
		$this->assertEquals(true, $this->activitySettings->canChangeMail());
	}

	public function testIsDefaultEnabledMail() {
		$this->assertEquals(false, $this->activitySettings->isDefaultEnabledMail());
	}
}
