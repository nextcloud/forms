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
namespace OCA\Forms\Tests\Unit\BackgroundJob;

use OCA\Forms\BackgroundJob\UserDeletedJob;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\FormMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class UserDeletedJobTest extends TestCase {
	/** @var UserDeletedJob */
	private $userDeletedJob;

	/** @var FormMapper|MockObject */
	private $formMapper;

	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->formMapper = $this->createMock(FormMapper::class);
		$time = $this->createMock(ITimeFactory::class);
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
		$this->userDeletedJob = new UserDeletedJob($this->formMapper, $time, $this->logger);
	}

	public function testHandle() {
		$form = $this->createMock(Form::class);
		$this->formMapper->expects($this->once())
			->method('findAllByOwnerId')
			->willReturn([$form, $form, $form]);
		$this->formMapper->expects($this->exactly(3))
			->method('deleteForm')
			->with($form);

		$this->userDeletedJob->run(['owner_id' => 'someUser']);
	}
}
