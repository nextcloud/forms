<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Events;

use OCA\Forms\Db\Form;
use OCA\Forms\Db\Submission;

class FormSubmittedEvent extends AbstractFormEvent {
	public const TRIGGER_CREATED = 'created';
	public const TRIGGER_UPDATED = 'updated';
	public const TRIGGER_VERIFIED = 'verified';

	public function __construct(
		Form $form,
		private Submission $submission,
		private string $trigger = self::TRIGGER_CREATED,
	) {
		parent::__construct($form);
	}

	public function getSubmission(): Submission {
		return $this->submission;
	}

	public function getTrigger(): string {
		return $this->trigger;
	}

	public function isNewSubmission(): bool {
		return $this->trigger === self::TRIGGER_CREATED;
	}

	public function getWebhookSerializable(): array {
		return [
			'form' => $this->form->read(),
			'submission' => $this->submission->read(),
			'trigger' => $this->trigger,
		];
	}
}
