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

use OCA\Analytics\Datasource\IDatasource;
use OCA\Forms\Analytics\AnalyticsDatasource;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;
use OCA\Forms\Service\SubmissionService;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AnalyticsDatasourceTest extends TestCase {

	private IL10N|MockObject $l10n;
	private LoggerInterface|MockObject $logger;
	private FormMapper|MockObject $formMapper;
	private FormsService|MockObject $formsService;
	private SubmissionService|MockObject $submissionService;

	public function setUp(): void {
		parent::setUp();

		if (!\class_exists(IDatasource::class)) {
			#$this->markTestSkipped('The analytics app is not installed!');
			#return;
		}

		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->formMapper = $this->createMock(FormMapper::class);
		$this->formsService = $this->createMock(FormsService::class);
		$this->submissionService = $this->createMock(SubmissionService::class);
	}

	protected function mockDatasource() {
		return new AnalyticsDatasource(
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
		$this->assertEquals('Nextcloud Forms', $this->mockDatasource()->getName());
	}

	public function testGetId() {
		$this->assertEquals(66, $this->mockDatasource()->getId());
	}

	public function testGetTemplate() {
		// Mock form object
		$form = $this->createMock(\OCA\Forms\Db\Form::class);
		$form->method('getId')->willReturn(1);
		$form->method('getTitle')->willReturn('Sample Form');

		// Mock findAllByOwnerId to return an array of forms
		$this->formMapper->method('findAllByOwnerId')->willReturn([$form]);

		// Mock translation method
		$this->l10n->method('t')->will($this->returnArgument(0));

		// Call getTemplate and assert the result
		$template = $this->analyticsDatasource->getTemplate();

		$expectedTemplate = [
			['id' => 'formId', 'name' => 'Select form', 'type' => 'tf', 'placeholder' => '1-Sample Form/'],
			['id' => 'timestamp', 'name' => 'Timestamp of data load', 'placeholder' => 'false-No/true-Yes', 'type' => 'tf']
		];

		$this->assertEquals($expectedTemplate, $template);
	}

	public function testReadData() {
		// Mock questions
		$questions = [
			['id' => 1, 'text' => 'Question 1'],
			['id' => 2, 'text' => 'Question 2']
		];
		$this->formsService->method('getQuestions')->willReturn($questions);

		// Mock submissions
		$submissions = [
			['answers' => [
				['questionId' => 1, 'text' => 'Answer 1'],
				['questionId' => 2, 'text' => 'Answer 2']
			]],
			['answers' => [
				['questionId' => 1, 'text' => 'Answer 1']
			]]
		];
		$this->submissionService->method('getSubmissions')->willReturn($submissions);

		// Mock translation method
		$this->l10n->method('t')->will($this->returnArgument(0));

		// Define options
		$options = ['formId' => 123];

		// Call readData and assert the result
		$data = $this->analyticsDatasource->readData($options);

		$expectedData = [
			'header' => ['Question', 'Answer', 'Count'],
			'dimensions' => ['Question', 'Answer'],
			'data' => [
				['Question 1', 'Answer 1', 2],
				['Question 2', 'Answer 2', 1]
			],
			'error' => 0
		];

		$this->assertEquals($expectedData, $data);
	}
}
