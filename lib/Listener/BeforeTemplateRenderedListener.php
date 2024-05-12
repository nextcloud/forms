<?php

namespace OCA\Forms\Listener;

use OCA\Text\Event\LoadEditor;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;

/** @template-implements IEventListener<Event|BeforeTemplateRenderedEvent> */
class BeforeTemplateRenderedListener implements IEventListener {
	public function __construct(private IEventDispatcher $eventDispatcher) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$isFormsResponse = $event->getResponse()->getApp() === \OCA\Forms\AppInfo\Application::APP_ID;
		if ($isFormsResponse && class_exists(LoadEditor::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadEditor());
		}
	}
}
