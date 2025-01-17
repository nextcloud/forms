<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Events;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;

class FormSubmittedEvent extends AbstractFormEvent {
	public function __construct(
		Form $form,
		private Submission $submission,
	) {
		parent::__construct($form);
	}

	public function getWebhookSerializable(): array {
		return [
			'form' => $this->form->read(),
			'submission' => $this->submission->read(),
		];
	}
}
