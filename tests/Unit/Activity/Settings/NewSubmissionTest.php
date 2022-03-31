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

use OCA\Forms\Activity\Settings\NewSubmission;
use OCP\IL10N;

use PHPUnit\Framework\MockObject\MockClass;
use Test\TestCase;

class NewSubmissionTest extends TestCase {
	/** @var IL10N|MockObject */
	private $l10n;

	/** @var NewSubmission|MockClass */
	private $newShare;

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
