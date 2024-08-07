<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\AppInfo;

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Forms\Capabilities;
use OCA\Forms\FormsMigrator;
use OCA\Forms\Listener\AnalyticsDatasourceListener;
use OCA\Forms\Listener\UserDeletedListener;
use OCA\Forms\Middleware\ThrottleFormAccessMiddleware;
use OCA\Forms\Search\SearchProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'forms';

	/**
	 * Application constructor.
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	/**
	 * Registration Logic
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		// Register composer autoloader
		include_once __DIR__ . '/../../vendor/autoload.php';

		$context->registerCapability(Capabilities::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(DatasourceEvent::class, AnalyticsDatasourceListener::class);
		$context->registerMiddleware(ThrottleFormAccessMiddleware::class);
		$context->registerSearchProvider(SearchProvider::class);
		$context->registerUserMigrator(FormsMigrator::class);
	}

	/**
	 * Boot Logic
	 * @param IBootContext $context
	 */
	public function boot(IBootContext $context): void {
		// No boot logic here yet...
	}
}
