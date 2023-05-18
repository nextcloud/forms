<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Jonas Rittershofer <jotoeri@users.noreply.github.com>
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

namespace OCA\Forms\Controller;

use OCA\Forms\Constants;
use OCA\Forms\Service\ConfigService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

use Psr\Log\LoggerInterface;

class ConfigController extends ApiController {
	protected $appName;

	/** @var ConfigService */
	private $configService;

	/** @var IConfig */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(string $appName,
		ConfigService $configService,
		IConfig $config,
		LoggerInterface $logger,
		IRequest $request) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->configService = $configService;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Get the current AppConfig
	 * @return DataResponse
	 */
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
