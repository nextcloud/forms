<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Ferdinand Thiessen <opensource@fthiessen.de>
 *
 * @author Ferdinand Thiessen <opensource@fthiessen.de>
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
namespace OCA\Forms\Tests\Unit\Analytics;

use OCA\Forms\Analytics\AnalyticsDatasource;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AnalyticsDatasourceTest extends TestCase {

	private AnalyticsDatasource $dataSource;

	private IL10N|MockObject $l10n;
	private LoggerInterface|MockObject $logger;
	private FormMapper|MockObject $formMapper;
	private FormsService|MockObject $formsService;
	private SubmissionService|MockObject $submissionService;

	public function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->submissionService = $this->createMock(SubmissionService::class);

		$this->dataSource = new AnalyticsDatasource(
			null,
			$this->l10n,
			$this->logger,
			$this->formMapper,
			$this->formsService,
			$this->submissionService,
		);
	}

	public function testGetName() {
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(fn (string $str) => $str);
		$this->assertEquals('Nextcloud Forms', $this->dataSource->getName());
	}

	public function testGetId() {
		$this->assertEquals(66, $this->dataSource->getId());
	}

	// TODO: Write tests for `getTemplate` and `readData`
}
