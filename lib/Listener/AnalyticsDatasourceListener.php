<?php

/**
 * Report Forms App data with the Analytics App
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the LICENSE.md file.
 *
 * @author Marcel Scherello <analytics@scherello.de>
 */
declare(strict_types=1);

namespace OCA\Forms\Listener;

use OCA\Analytics\Datasource\DatasourceEvent;
use OCA\Forms\Analytics\AnalyticsDatasource;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<DatasourceEvent>
 */
class AnalyticsDatasourceListener implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof DatasourceEvent)) {
			// Unrelated
			return;
		}
		$event->registerDatasource(AnalyticsDatasource::class);
	}
}
