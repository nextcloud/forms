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
		$this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
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
				'strConfig' => "true"
			],
			'arrayConfig' => [
				'configKey' => 'allowPermitAll',
				'configValue' => [
					"admin",
					"group1"
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
