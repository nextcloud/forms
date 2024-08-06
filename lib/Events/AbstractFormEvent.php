<?php

namespace OCA\Forms\Events;

use OCA\Forms\Db\Form;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

abstract class AbstractFormEvent extends Event implements IWebhookCompatibleEvent {
	protected Form $form;

	public function __construct() {
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
