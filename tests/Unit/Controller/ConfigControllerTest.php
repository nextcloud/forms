<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Controller;

use OCA\Forms\AuditLog\AuditLogger;
use OCA\Forms\AuditLog\Operation;
use OCA\Forms\Controller\ConfigController;
use OCA\Forms\Service\ConfigService;
use OCA\Forms\Tests\Unit\AuditLog\AuditEvents;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class ConfigControllerTest extends TestCase {

	private ConfigController $configController;
	private ConfigService|MockObject $configService;
	private IAppConfig|MockObject $config;
	private LoggerInterface|MockObject $logger;
	private IRequest|MockObject $request;
	private IEventDispatcher|MockObject $eventDispatcher;

	public function setUp(): void {
		parent::setUp();

		$this->configService = $this->createMock(ConfigService::class);
		$this->config = $this->createMock(IAppConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);

		$this->configController = new ConfigController(
			'forms',
			$this->configService,
			$this->config,
			$this->logger,
			$this->request,
			new AuditLogger($this->eventDispatcher),
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

	public static function dataUpdateAppConfig() {
		return [
			'booleanAllowPermitAll' => [
				'configKey' => 'allowPermitAll',
				'configValue' => true,
			],
			'booleanAllowShowToAll' => [
				'configKey' => 'allowShowToAll',
				'configValue' => true,
			],
			'booleanAllowCustomPublicShareTokens' => [
				'configKey' => 'allowCustomPublicShareTokens',
				'configValue' => true,
			],
			'arrayCreationAllowedGroups' => [
				'configKey' => 'creationAllowedGroups',
				'configValue' => [
					'admin',
					'group1'
				],
			]
		];
	}
	/**
	 * @dataProvider dataUpdateAppConfig
	 *
	 * @param string $configKey
	 * @param mixed $configValue
	 */
	public function testUpdateAppConfig(string $configKey, $configValue) {
		$this->logger->expects($this->once())
			->method('debug');

		if (is_array($configValue)) {
			$this->config->expects($this->once())
				->method('setAppValueArray')
				->with($configKey, $configValue);
		} else {
			$this->config->expects($this->once())
				->method('setAppValueBool')
				->with($configKey, $configValue);
		}

		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (CriticalActionPerformedEvent $event) use ($configKey, $configValue): bool {
				$this->assertTrue(AuditEvents::hasOperation($event, Operation::ConfigUpdated->value));
				$this->assertSame('Forms setting %s was changed', $event->getLogMessage());
				$this->assertSame(['configKey' => $configKey, 'configValue' => $configValue], $event->getParameters());
				return true;
			}));

		$this->assertEquals(new DataResponse(), $this->configController->updateAppConfig($configKey, $configValue));
	}

	public function testUpdateAppConfigInvalidValueIsNotLogged(): void {
		$this->config->expects($this->once())
			->method('setAppValueInt')
			->willThrowException(new \InvalidArgumentException());
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->assertEquals(
			new DataResponse('Invalid value for confirmationEmailRateLimit', 400),
			$this->configController->updateAppConfig('confirmationEmailRateLimit', -1)
		);
	}

	public function testUpdateAppConfig_unknownKey() {
		$this->logger->expects($this->once())
			->method('debug');

		$this->config->expects($this->never())
			->method('setAppValueBool');
		$this->config->expects($this->never())
			->method('setAppValueArray');
		$this->eventDispatcher->expects($this->never())
			->method('dispatchTyped');

		$this->assertEquals(new DataResponse('Unknown appConfig key: someUnknownKey', 400), $this->configController->updateAppConfig('someUnknownKey', 'storeThisValue!'));
	}

	public function testUpdateAppConfigRejectsConfirmationEmailWhenMailIsNotConfigured() {
		$this->logger->expects($this->once())
			->method('debug');

		$this->configService->expects($this->once())
			->method('isMailConfigured')
			->willReturn(false);

		$this->config->expects($this->never())
			->method('setAppValue');

		$this->assertEquals(
			new DataResponse('Mail server is not configured', 400),
			$this->configController->updateAppConfig('allowConfirmationEmail', true)
		);
	}
}
