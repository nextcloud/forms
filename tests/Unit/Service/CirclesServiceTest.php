<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCA\Forms\Service\CirclesService;
use OCP\App\IAppManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Test\TestCase;
use Throwable;

class CirclesServiceText extends TestCase {
	/** @var ContainerInterface|MockObject */
	private $container;
	/** @var IAppManager|MockObject */
	private $appManager;
	/** @var LoggerInterface|MockObject */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->container = $this->createMock(ContainerInterface::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->appManager = $this->createMock(IAppManager::class);
	}

	public function dataIsEnabled() {
		return [
			'not-enabled' => [
				false,
				false
			],
			'enabled' => [
				true,
				true
			]
		];
	}

	/**
	 * @dataProvider dataIsEnabled
	 */
	public function testIsEnabled($enabled, $expected) {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('circles')
			->willReturn($enabled);
		$circlesService = new CirclesService($this->appManager, $this->container, $this->logger);
		$this->assertEquals($circlesService->isCirclesEnabled(), $expected);
	}

	public function dataGetCircle() {
		$circle = $this->createMock(Circle::class);

		return [
			'valid-circle' => [
				true,
				false,
				$circle,
				$circle
			],
			'invalid-circle' => [
				true,
				true,
				$circle,
				null,
			],
			'disabled-app' => [
				false,
				false,
				$circle,
				null
			]
		];
	}

	/**
	 * @dataProvider dataGetCircle
	 */
	public function testGetCircle($enabled, $throws, $circle, $expected) {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('circles')
			->willReturn($enabled);

		$circlesService = new CirclesService($this->appManager, $this->container, $this->logger);
		$circlesManager = $this->createMock(CirclesManager::class);
		$this->container->expects($enabled ? $this->once() : $this->never())
			->method('get')
			->with(CirclesManager::class)
			->willReturn($circlesManager);
		$e = $circlesManager->expects($enabled ? $this->once() : $this->never())
			->method('getCircle')
			->with('circle');
		if ($throws) {
			$e->will($this->throwException($this->createMock(Throwable::class)));
		} else {
			$e->willReturn($circle);
		}

		$this->assertEquals($circlesService->getCircle('circle'), $expected);
	}

	public function testGetCircleUsers_circlesDisabled() {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('circles')
			->willReturn(false);

		$circlesService = $this->getMockBuilder(CirclesService::class)
			->onlyMethods(['getCircle'])
			->setConstructorArgs([$this->appManager, $this->container, $this->logger])
			->getMock();

		$circlesService->expects($this->never())
			->method('getCircle');

		$this->assertEquals($circlesService->getCircleUsers('some'), []);
	}

	public function testGetCircleUsers_circleNotFound() {
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('circles')
			->willReturn(true);

		$circlesService = $this->getMockBuilder(CirclesService::class)
			->onlyMethods(['getCircle'])
			->setConstructorArgs([$this->appManager, $this->container, $this->logger])
			->getMock();

		$circlesService->expects($this->once())
			->method('getCircle')
			->with('noCircle')
			->willReturn(null);

		$this->assertEquals($circlesService->getCircleUsers('noCircle'), []);
	}

	public function testGetCircleUsers() {
		$userNames = ['user1', 'user2', 'user3'];

		$member = $this->createMock(Member::class);
		$member->expects($this->exactly(4))
			->method('getUserType')
			->willReturnOnConsecutiveCalls(Member::TYPE_USER, Member::TYPE_GROUP, Member::TYPE_USER, Member::TYPE_USER);
		$member->expects($this->exactly(3))
			->method('getUserId')
			->willReturnOnConsecutiveCalls(...$userNames);

		$members = [$member, $member, $member, $member];
		$circle = $this->createMock(Circle::class);
		$circle->expects($this->once())
			->method('getInheritedMembers')
			->willReturn($members);

		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('circles')
			->willReturn(true);

		$circlesService = $this->getMockBuilder(CirclesService::class)
			->onlyMethods(['getCircle'])
			->setConstructorArgs([$this->appManager, $this->container, $this->logger])
			->getMock();

		$circlesService->expects($this->once())
			->method('getCircle')
			->with('circle')
			->willReturn($circle);

		$this->assertEqualsCanonicalizing($circlesService->getCircleUsers('circle'), $userNames);
	}
}
