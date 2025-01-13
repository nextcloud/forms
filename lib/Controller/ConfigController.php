<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Controller;

use OCA\Forms\Constants;
use OCA\Forms\Service\ConfigService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

use Psr\Log\LoggerInterface;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class ConfigController extends ApiController {
	public function __construct(
		protected $appName,
		private ConfigService $configService,
		private IConfig $config,
		private LoggerInterface $logger,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get the current AppConfig
	 * @return DataResponse
	 */
	#[FrontpageRoute(verb: 'GET', url: '/config')]
	public function getAppConfig(): DataResponse {
		return new DataResponse($this->configService->getAppConfig());
	}

	/**
	 * Update values on appConfig.
	 * Admin required, thus not checking separately.
	 *
	 * @param string $configKey AppConfig Key to store
	 * @param mixed $configValues Corresponding AppConfig Value
	 *
	 */
	#[FrontpageRoute(verb: 'PATCH', url: '/config')]
	public function updateAppConfig(string $configKey, $configValue): DataResponse {
		$this->logger->debug('Updating AppConfig: {configKey} => {configValue}', [
			'configKey' => $configKey,
			'configValue' => $configValue
		]);

		// Check for allowed keys
		if (!in_array($configKey, Constants::CONFIG_KEYS)) {
			return new DataResponse('Unknown appConfig key: ' . $configKey, Http::STATUS_BAD_REQUEST);
		}

		// Set on DB
		$this->config->setAppValue($this->appName, $configKey, json_encode($configValue));

		return new DataResponse();
	}
}
