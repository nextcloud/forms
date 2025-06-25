<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Db;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\SubmissionMapper;

use Test\TestCase;

class SubmissionMapperTest extends TestCase {

	private SubmissionMapper $mockSubmissionMapper;


	public function setUp(): void {
		parent::setUp();

		$this->mockSubmissionMapper = $this->getMockBuilder(SubmissionMapper::class)
			->disableOriginalConstructor()
			->setMethods(['countSubmissionsWithFilters'])
			->getMock();
	}

	/**
	 * @dataProvider dataHasMultipleFormSubmissionsByUser
	 */
	public function testHasMultipleFormSubmissionsByUser(int $numberOfSubmissions, bool $expected) {
		$this->mockSubmissionMapper->expects($this->once())
			->method('countSubmissionsWithFilters')
			->will($this->returnValue($numberOfSubmissions));

		$form = new Form();
		$form->setId(1);

		$this->assertEquals($expected, $this->mockSubmissionMapper->hasMultipleFormSubmissionsByUser($form, 'user1'));
	}

	public function dataHasMultipleFormSubmissionsByUser() {
		return [
			[
				'numberOfSubmissions' => 0,
				'expected' => false,
			],
			[
				'numberOfSubmissions' => 1,
				'expected' => false,
			],
			[
				'numberOfSubmissions' => 2,
				'expected' => true,
			],
			[
				'numberOfSubmissions' => 3,
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider dataHasFormSubmissionsByUser
	 */
	public function testHasFormSubmissionsByUser(int $numberOfSubmissions, bool $expected) {
		$this->mockSubmissionMapper->expects($this->once())
			->method('countSubmissionsWithFilters')
			->will($this->returnValue($numberOfSubmissions));

		$form = new Form();
		$form->setId(1);

		$this->assertEquals($expected, $this->mockSubmissionMapper->hasFormSubmissionsByUser($form, 'user1'));
	}

	public function dataHasFormSubmissionsByUser() {
		return [
			[
				'numberOfSubmissions' => 0,
				'expected' => false,
			],
			[
				'numberOfSubmissions' => 1,
				'expected' => true,
			],
			[
				'numberOfSubmissions' => 2,
				'expected' => true,
			],
		];
	}

	/**
	 * @dataProvider dataCountSubmissions
	 */
	public function testCountSubmissions(int $numberOfSubmissions, int $expected) {
		$this->mockSubmissionMapper->expects($this->once())
			->method('countSubmissionsWithFilters')
			->will($this->returnValue($numberOfSubmissions));

		$this->assertEquals($expected, $this->mockSubmissionMapper->countSubmissions(1));
	}

	public function dataCountSubmissions() {
		return [
			[
				'numberOfSubmissions' => 0,
				'expected' => 0,
			],
			[
				'numberOfSubmissions' => 1,
				'expected' => 1,
			],
			[
				'numberOfSubmissions' => 20,
				'expected' => 20,
			],
		];
	}
}
