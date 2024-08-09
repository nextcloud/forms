<?php

namespace OCA\Forms\Events;

use OCA\Forms\Db\Form;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

class FormSubmittedEvent extends AbstractFormEvent implements IWebhookCompatibleEvent {
	public function __construct(Form $form) {
		parent::__construct();
		$this->form = $form;
	}
}
