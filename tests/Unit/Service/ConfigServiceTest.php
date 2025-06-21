<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Service;

use OCA\Forms\Service\ConfigService;

use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

use PHPUnit\Framework\MockObject\MockObject;

use Test\TestCase;

class ConfigServiceTest extends TestCase {

	/** @var ConfigService */
	private $configService;

	/** @var IConfig|MockObject */
	private $config;

	/** @var IGroupManager|MockObject */
	private $groupManager;

	/** @var IUser|MockObject */
	private $currentUser;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$userSession = $this->createMock(IUserSession::class);

		$this->currentUser = $this->createMock(IUser::class);
		$this->currentUser->expects($this->any())
			->method('getUID')
			->willReturn('currentUser');
		$userSession->expects($this->any())
			->method('getUser')
			->willReturn($this->currentUser);

		$this->configService = new ConfigService(
			'forms',
			$this->config,
			$this->groupManager,
			$userSession
		);
	}

	public function dataGetAppConfig() {
		return [
			'oneFullConfig' => [
				'strConfig' => [
					'allowPermitAll' => 'false',
					'allowPublicLink' => 'false',
					'allowShowToAll' => 'false',
					'creationAllowedGroups' => '["group1", "group2", "nonExisting"]',
					'restrictCreation' => 'true',
				],
				'groupDisplayNames' => [
					'group1' => 'Group No. 1',
					'group2' => 'Group No. 2'
				],
				'expected' => [
					'allowPermitAll' => false,
					'allowPublicLink' => false,
					'allowShowToAll' => false,
					'creationAllowedGroups' => [
						[
							'groupId' => 'group1',
							'displayName' => 'Group No. 1'
						],
						[
							'groupId' => 'group2',
							'displayName' => 'Group No. 2'
						]
					],
					'restrictCreation' => true,

					'canCreateForms' => false
				]
			]
		];
	}
	/**
	 * @dataProvider dataGetAppConfig
	 *
	 * @param array $strConfig JSON Config Strings as stored in AppConfig
	 * @param array $groupDisplayNames DisplayNames for Testing
	 * @param array $expected
	 */
	public function testGetAppConfig(array $strConfig, array $groupDisplayNames, array $expected) {
		// Default Values are set within getAppValue, thus returning this one.
		$this->config->expects($this->any())
			->method('getAppValue')
			->will($this->returnCallback(function ($appName, $configKey, $defaultVal) use ($strConfig) {
				return $strConfig[$configKey];
			}));


		// Mock Group formatting
		$this->groupManager->expects($this->any())
			->method('get')
			->will($this->returnCallback(function ($groupId) use ($groupDisplayNames) {
				if (!array_key_exists($groupId, $groupDisplayNames)) {
					return [];
				}
				$group = $this->createMock(IGroup::class);
				$group->expects($this->once())
					->method('getDisplayName')
					->willReturn($groupDisplayNames[$groupId]);
				return $group;
			}));

		// Return currentUser Groups
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn([]);

		$this->assertEquals($expected, $this->configService->getAppConfig());
	}

	public function dataGetAppConfig_Default() {
		return [
			'defaultValues' => [
				'expected' => [
					'allowPermitAll' => true,
					'allowPublicLink' => true,
					'allowShowToAll' => true,
					'creationAllowedGroups' => [],
					'restrictCreation' => false,
					'canCreateForms' => true
				]
			]
		];
	}
	/**
	 * @dataProvider dataGetAppConfig_Default
	 *
	 * @param array $expected
	 */
	public function testGetAppConfig_Default(array $expected) {
		// Default Values are set within getAppValue, thus returning this one.
		$this->config->expects($this->any())
			->method('getAppValue')
			->will($this->returnCallback(function ($appName, $configKey, $defaultVal) {
				return $defaultVal;
			}));

		$this->assertEquals($expected, $this->configService->getAppConfig());
	}

	public function dataCanCreateForms() {
		return [
			'notRestriced' => [
				'config' => [
					'restrictCreation' => 'false',
				],
				'expected' => true
			],
			'restrictedGroupAllowed' => [
				'config' => [
					'restrictCreation' => 'true',
					'creationAllowedGroups' => '["usersGroup","notUsersGroup"]'
				],
				'expected' => true
			],
			'restrictedNotInGroup' => [
				'config' => [
					'restrictCreation' => 'true',
					'creationAllowedGroups' => '["notUsersGroup"]'
				],
				'expected' => false
			]
		];
	}

	/**
	 * @dataProvider dataCanCreateForms
	 *
	 * @param array $config AppConfig with necessary values
	 * @param bool $expected
	 */
	public function testCanCreateForms(array $config, bool $expected) {
		$this->config->expects($this->any())
			->method('getAppValue')
			->will($this->returnCallback(function ($appName, $configKey, $defaultVal) use ($config) {
				return $config[$configKey];
			}));

		$this->groupManager->expects($this->any())
			->method('getUserGroupIds')
			->with($this->currentUser)
			->willReturn(['usersGroup']);

		$this->assertEquals($expected, $this->configService->canCreateForms());
	}
}
