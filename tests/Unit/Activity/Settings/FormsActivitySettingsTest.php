<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Forms\Tests\Unit\Activity\Settings;

use OCA\Forms\Activity\Settings\FormsActivitySettings;
use OCP\IL10N;

use PHPUnit\Framework\MockObject\MockClass;
use Test\TestCase;

class SettingsTestClass extends FormsActivitySettings {
	// Two abstract methods exist, that need to be implemented to use the class.
	public function getIdentifier() {
	}
	public function getName() {
	}
}

class FilterTest extends TestCase {
	/** @var IL10N|MockObject */
	private $l10n;

	/** @var SettingsTestClass|MockClass */
	private $activitySettings;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->activitySettings = new SettingsTestClass('forms', $this->l10n);
	}

	public function testGetGroupIdentifier() {
		$this->assertEquals('forms', $this->activitySettings->getGroupIdentifier());
	}

	public function testGetGroupName() {
		$this->l10n->expects($this->once())
			->method('t')
			->will($this->returnCallback(function ($identity) {
				return $identity;
			}));
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
