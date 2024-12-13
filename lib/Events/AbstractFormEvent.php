<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Events;

use OCA\Forms\Db\Form;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

if (interface_exists(\OCP\EventDispatcher\IWebhookCompatibleEvent::class)) {
	abstract class AbstractFormEvent extends Event implements IWebhookCompatibleEvent {
		public function __construct(
			protected Form $form,
		) {
			parent::__construct();
		}

		public function getForm(): Form {
			return $this->form;
		}

		/**
		 * @inheritDoc
		 */
		public function getWebhookSerializable(): array {
			return $this->form->read();
		}
	}

} else {
	// need this block as long as NC < 30 is supported
	abstract class AbstractFormEvent extends Event {
		public function __construct(
			protected Form $form,
		) {
			parent::__construct();
		}

		public function getForm(): Form {
			return $this->form;
		}

		public function getWebhookSerializable(): array {
			return $this->form->read();
		}
	}
}
