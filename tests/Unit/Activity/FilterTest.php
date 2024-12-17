<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		parent::setUp();
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
