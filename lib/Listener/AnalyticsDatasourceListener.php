<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
