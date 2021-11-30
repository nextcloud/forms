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
namespace OCA\Forms\Tests\Unit\Activity;

use OCA\Forms\Activity\Filter;

use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class FilterTest extends TestCase {

	/** @var Filter */
	private $filter;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	public function setUp(): void {
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->filter = new Filter('forms', $this->l10n, $this->urlGenerator);
	}

	public function testGetIdentifier() {
		$this->assertEquals('forms', $this->filter->getIdentifier());
	}

	public function testGetName() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Forms')
			->willReturn('Forms');
		$this->assertEquals('Forms', $this->filter->getName());
	}

	public function testGetIcon() {
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('forms', 'forms-dark.svg')
			->willReturn('apps/forms/img/forms-dark.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteUrl')
			->will($this->returnCallback(function ($path) {
				return 'http://localhost/' . $path;
			}));
		$this->assertEquals('http://localhost/apps/forms/img/forms-dark.svg', $this->filter->getIcon());
	}

	public function testGetPriority() {
		$this->assertEquals(60, $this->filter->getPriority());
	}

	public function testAllowedApps() {
		$this->assertEquals(['forms'], $this->filter->allowedApps());
	}

	public function testFilterTypes() {
		$data = ['forms_newshare', 'forms_newsubmission'];
		$this->assertEquals($data, $this->filter->filterTypes($data));
	}
}
