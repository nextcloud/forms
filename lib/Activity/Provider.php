<?php
/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license GNU AGPL version 3 or any later version
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

namespace OCA\Forms\Activity;

use Exception;

use OCA\Forms\Db\FormMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IProvider;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;

class Provider implements IProvider {
	private $appName;

	/** @var FormMapper */
	private $formMapper;

	/** @var IEventMerger */
	private $eventMerger;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IL10N */
	private $l10n;

	/** @var ILogger */
	private $logger;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IUserManager */
	private $userManager;

	public function __construct(string $appName,
								FormMapper $formMapper,
								IEventMerger $eventMerger,
								IGroupManager $groupManager,
								IL10N $l10n,
								ILogger $logger,
								IURLGenerator $urlGenerator,
								IUserManager $userManager) {
		$this->appName = $appName;
		$this->formMapper = $formMapper;
		$this->eventMerger = $eventMerger;
		$this->groupManager = $groupManager;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
		$this->userManager = $userManager;
	}

	/**
	 * Beautify the stored Event for Output.
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null): IEvent {
		// Throw Exception, if not our activity. Necessary for workflow of Activity-App.
		if ($event->getApp() !== $this->appName) {
			throw new \InvalidArgumentException();
		}

		$subjectString = $this->getSubjectString($event->getSubject());
		$parameters = $this->getRichParams($event->getSubject(), $event->getSubjectParameters());

		$event->setParsedSubject($this->parseSubjectString($subjectString, $parameters));
		$event->setRichSubject($subjectString, $parameters);
		$event->setIcon($this->getEventIcon($event->getSubject()));

		// For Subject NewShare, merge by users
		if ($event->getSubject() === ActivityConstants::SUBJECT_NEWSUBMISSION) {
			$event = $this->eventMerger->mergeEvents('user', $event, $previousEvent);
		}

		return $event;
	}

	/**
	 * Provide the translated string with placeholders
	 * @param $subject The events subject
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	private function getSubjectString(string $subject): string {
		switch ($subject) {
			case ActivityConstants::SUBJECT_NEWSHARE:
				return $this->l10n->t('{user} has shared the form {formTitle} with you');

			case ActivityConstants::SUBJECT_NEWGROUPSHARE:
				return $this->l10n->t('{user} has shared the form {formTitle} with group {group}');

			case ActivityConstants::SUBJECT_NEWSUBMISSION:
				return $this->l10n->t('{user} answered your form {formTitle}');

			default:
				$this->logger->warning('Some unknown activity has been found: ' . $subject);
				throw new \InvalidArgumentException();
		}
	}

	/**
	 * Simply insert the parameters into the string. Returns a simple human readable string.
	 * @param string $subjectString The string with placeholders
	 * @param array $parameters Array of Rich-Parameters as created by getRichParams()
	 * @return string
	 */
	private function parseSubjectString(string $subjectString, array $parameters): string {
		$placeholders = [];
		$replacements = [];
		foreach ($parameters as $paramKey => $paramValue) {
			$placeholders[] = '{' . $paramKey . '}';
			$replacements[] = $paramValue['name'];
		}
		return str_replace($placeholders, $replacements, $subjectString);
	}

	/**
	 * Create the necessary Rich-Params out of the given SubjectParameters as stored on Activity-Db.
	 * @param string $subject The stored Subject as defined in ActivityConstants
	 * @param array $params Array of stored SubjectParameters
	 * @return array
	 */
	private function getRichParams(string $subject, array $params): array {
		switch ($subject) {
			case ActivityConstants::SUBJECT_NEWSHARE:
				return [
					'user' => $this->getRichUser($params['userId']),
					'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash'])
				];
			case ActivityConstants::SUBJECT_NEWGROUPSHARE:
				return [
					'user' => $this->getRichUser($params['userId']),
					'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash']),
					'group' => $this->getRichGroup($params['groupId'])
				];
			case ActivityConstants::SUBJECT_NEWSUBMISSION:
				return [
					'user' => $this->getRichUser($params['userId']),
					'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash'], 'results')
				];
			default:
				return [];
		}
	}

	/**
	 * Turn a userId into an rich-user array.
	 * @param string $userId
	 * @return array
	 */
	private function getRichUser(string $userId): array {
		// Special handling for anonyomous users
		if (substr($userId, 0, 10) === 'anon-user-') {
			return [
				'type' => 'highlight',
				'id' => $userId,
				'name' => $this->l10n->t('Anonymous user')
			];
		}

		// Get Displayname, if user still exists. Otherwise just show stored userId
		$user = $this->userManager->get($userId);
		$displayName = '';
		if ($user === null) {
			$displayName = $userId;
		} else {
			$displayName = $user->getDisplayName();
		}

		return [
			'type' => 'user',
			'id' => $userId,
			'name' => $displayName
		];
	}

	/**
	 * Turn a groupId into an rich-group array.
	 * @param string $groupId
	 * @return array
	 */
	private function getRichGroup(string $groupId): array {
		// Get Displayname, if group still exists. Otherwise just show stored groupId
		$group = $this->groupManager->get($groupId);
		$displayName = '';
		if ($group === null) {
			$displayName = $groupId;
		} else {
			$displayName = $group->getDisplayName();
		}

		return [
			'type' => 'user-group',
			'id' => $groupId,
			'name' => $displayName
		];
	}

	/**
	 * Turn formTitle and formHash into the Rich-Array.
	 * Link is default to Form-Submission, but can be forwarded to specific route.
	 * @param string $formTitle Stored Form-Title. Only used as fallback, if form not found
	 * @param string $formHash Hash of the form
	 * @param string $route Optional Path of specific route to append to hash-url
	 * @return array
	 */
	private function getRichFormTitle(string $formTitle, string $formHash, string $route = ''): array {
		// Base-url to Forms app. Fallback in case of MapperException.
		$formLink = $this->urlGenerator->linkToRouteAbsolute('forms.page.index');

		try {
			// Overwrite formTitle if form is found (i.e. still exists).
			$formTitle = $this->formMapper->findbyHash($formHash)->getTitle();

			// Append hash and route
			$formLink .= $formHash;
			if ($route !== '') {
				$formLink .= '/' . $route;
			}
		} catch (IMapperException $e) {
			// Ignore if not found, just use stored title
		}

		return [
			'type' => 'highlight',
			'id' => $formHash,
			'name' => $formTitle,
			'link' => $formLink
		];
	}

	/**
	 * Selects the appropriate icon, depending on the subject
	 * @param string $subject The events subject
	 * @return string
	 */
	private function getEventIcon(string $subject): string {
		switch ($subject) {
			case ActivityConstants::SUBJECT_NEWSHARE:
			case ActivityConstants::SUBJECT_NEWGROUPSHARE:
				return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/shared.svg'));

			case ActivityConstants::SUBJECT_NEWSUBMISSION:
				return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/add.svg'));

			default:
				return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('forms', 'forms-dark.svg'));
		}
	}
}
