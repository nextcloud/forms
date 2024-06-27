<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Abdrii Ilkiv <ailkiv@users.noreply.github.com>
 *
 * @author Abdrii Ilkiv <ailkiv@users.noreply.github.com>
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
