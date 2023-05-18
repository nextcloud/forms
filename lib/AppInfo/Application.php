<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Forms\AppInfo;

use OCA\Forms\Capabilities;
use OCA\Forms\FormsMigrator;
use OCA\Forms\Listener\UserDeletedListener;
use OCA\Forms\Middleware\PublicCorsMiddleware;
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
		$context->registerUserMigrator(FormsMigrator::class);
		$context->registerMiddleware(PublicCorsMiddleware::class);
	}

	/**
	 * Boot Logic
	 * @param IBootContext $context
	 */
	public function boot(IBootContext $context): void {
		// No boot logic here yet...
	}
}
