<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Listener;

use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\FormsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Comments\CommentsEntityEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class CommentsEntityListener implements IEventListener {
	public function __construct(
		protected FormMapper $formMapper,
		protected FormsService $formService,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof CommentsEntityEvent) {
			return;
		}

		// Register the 'forms' entity collection so the Comments app can
		// check whether a given form id allows comments.
		$event->addEntityCollection('forms', function ($formId) {
			try {
				$form = $this->formMapper->findById((int)$formId);
			} catch (DoesNotExistException) {
				return false;
			}
			return $this->formService->hasUserAccess($form) && $form->getAllowComments();
		});
	}
}
