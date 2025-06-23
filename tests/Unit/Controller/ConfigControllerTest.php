<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\Controller\ConfigController;

use OCA\Forms\Service\ConfigService;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

use Test\TestCase;

class ConfigControllerTest extends TestCase {

	/** @var ConfigController */
	private $configController;

	/** @var ConfigService */
	private $configService;

	/** @var IConfig|MockObject */
	private $config;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IRequest|MockObject */
	private $request;

	public function setUp(): void {
		parent::setUp();

		$this->configService = $this->createMock(ConfigService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);

		$this->configController = new ConfigController(
			'forms',
			$this->configService,
			$this->config,
			$this->logger,
			$this->request
		);
	}

	public function testGetAppConfig() {
		$this->configService->expects($this->once())
			->method('getAppConfig')
			->willReturn([
				'allow' => 'someConfig',
				'permit' => 'all'
			]);

		$this->assertEquals(new DataResponse([
			'allow' => 'someConfig',
			'permit' => 'all'
		]), $this->configController->getAppConfig());
	}

	public function dataUpdateAppConfig() {
		return [
			'booleanConfig' => [
				'configKey' => 'allowPermitAll',
				'configValue' => true,
				'strConfig' => 'true'
			],
			'booleanConfig' => [
				'configKey' => 'allowShowToAll',
				'configValue' => true,
				'strConfig' => 'true'
			],
			'arrayConfig' => [
				'configKey' => 'allowPermitAll',
				'configValue' => [
					'admin',
					'group1'
				],
				'strConfig' => '["admin","group1"]'
			]
		];
	}
	/**
	 * @dataProvider dataUpdateAppConfig
	 *
	 * @param string $configKey
	 * @param mixed $configValue
	 * @param string $strConfig The configValue as json-string
	 */
	public function testUpdateAppConfig(string $configKey, $configValue, string $strConfig) {
		$this->logger->expects($this->once())
			->method('debug');

		$this->config->expects($this->once())
			->method('setAppValue')
			->with('forms', $configKey, $strConfig);

		$this->assertEquals(new DataResponse(), $this->configController->updateAppConfig($configKey, $configValue));
	}

	public function testUpdateAppConfig_unknownKey() {
		$this->logger->expects($this->once())
			->method('debug');

		$this->config->expects($this->never())
			->method('setAppValue');

		$this->assertEquals(new DataResponse('Unknown appConfig key: someUnknownKey', 400), $this->configController->updateAppConfig('someUnknownKey', 'storeThisValue!'));
	}
}
