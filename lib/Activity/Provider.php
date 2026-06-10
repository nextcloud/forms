<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Activity;

use Exception;
use OCA\Forms\Db\FormMapper;
use OCA\Forms\Service\CirclesService;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IProvider;
use OCP\AppFramework\Db\IMapperException;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class Provider implements IProvider {
	public function __construct(
		protected string $appName,
		private readonly FormMapper $formMapper,
		private readonly IEventMerger $eventMerger,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
		private readonly IURLGenerator $urlGenerator,
		private readonly IUserManager $userManager,
		private readonly IFactory $l10nFactory,
		private readonly CirclesService $circlesService,
	) {
	}

	/**
	 * Beautify the stored Event for Output.
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws UnknownActivityException
	 */
	public function parse($language, IEvent $event, ?IEvent $previousEvent = null): IEvent {
		// Throw Exception, if not our activity. Necessary for workflow of Activity-App.
		if ($event->getApp() !== $this->appName) {
			throw new UnknownActivityException();
		}
		$l10n = $this->l10nFactory->get($this->appName, $language);

		$subjectString = $this->getSubjectString($l10n, $event->getSubject());
		$parameters = $this->getRichParams($l10n, $event->getSubject(), $event->getSubjectParameters());

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
	 * @throws UnknownActivityException
	 */
	public function getSubjectString(IL10N $l10n, string $subject): string {
		switch ($subject) {
			case ActivityConstants::SUBJECT_NEWSHARE:
				return $l10n->t('{user} has shared the form {formTitle} with you');
			case ActivityConstants::SUBJECT_NEWGROUPSHARE:
				return $l10n->t('{user} has shared the form {formTitle} with group {group}');
			case ActivityConstants::SUBJECT_NEWCIRCLESHARE:
				return $l10n->t('{user} has shared the form {formTitle} with team {circle}');
			case ActivityConstants::SUBJECT_NEWSUBMISSION:
				return $l10n->t('Your form {formTitle} was answered by {user}');
			default:
				$this->logger->warning('Some unknown activity has been found: ' . $subject);
				throw new UnknownActivityException();
		}
	}

	/**
	 * Simply insert the parameters into the string. Returns a simple human readable string.
	 * @param string $subjectString The string with placeholders
	 * @param array $parameters Array of Rich-Parameters as created by getRichParams()
	 * @return string
	 */
	public function parseSubjectString(string $subjectString, array $parameters): string {
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
	public function getRichParams(IL10N $l10n, string $subject, array $params): array {
		return match ($subject) {
			ActivityConstants::SUBJECT_NEWSHARE => [
				'user' => $this->getRichUser($l10n, $params['userId']),
				'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash'])
			],
			ActivityConstants::SUBJECT_NEWGROUPSHARE => [
				'user' => $this->getRichUser($l10n, $params['userId']),
				'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash']),
				'group' => $this->getRichGroup($params['groupId'])
			],
			ActivityConstants::SUBJECT_NEWCIRCLESHARE => [
				'user' => $this->getRichUser($l10n, $params['userId']),
				'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash']),
				'circle' => $this->getRichCircle($params['circleId'])
			],
			ActivityConstants::SUBJECT_NEWSUBMISSION => [
				'user' => $this->getRichUser($l10n, $params['userId']),
				'formTitle' => $this->getRichFormTitle($params['formTitle'], $params['formHash'], 'results')
			],
			default => [],
		};
	}

	/**
	 * Turn a userId into an rich-user array.
	 * @param string $userId
	 * @return array
	 */
	public function getRichUser(IL10N $l10n, string $userId): array {
		// Special handling for anonyomous users
		if (str_starts_with($userId, 'anon-user-')) {
			return [
				'type' => 'highlight',
				'id' => $userId,
				// TRANSLATORS Shown as a users display-name
				'name' => $l10n->t('Anonymous user')
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
	public function getRichGroup(string $groupId): array {
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
	 * Turn a circleId into a rich-circle array.
	 *
	 * @param string $circleId
	 * @return array
	 */
	public function getRichCircle(string $circleId): array {
		$displayName = $circleId;
		$link = '';

		$circle = $this->circlesService->getCircle($circleId);
		if (!is_null($circle)) {
			$displayName = $circle->getDisplayName();
			$link = $circle->getUrl();
		}

		return [
			'type' => 'circle',
			'id' => $circleId,
			'name' => $displayName,
			'link' => $link,
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
	public function getRichFormTitle(string $formTitle, string $formHash, string $route = ''): array {
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
		} catch (IMapperException) {
			// Ignore if not found, just use stored title
		}

		$richFormTitle = [
			'type' => 'forms-form',
			'id' => $formHash,
			'name' => $formTitle,
			'link' => $formLink
		];

		return $richFormTitle;
	}

	/**
	 * Selects the appropriate icon, depending on the subject
	 * @param string $subject The events subject
	 * @return string
	 */
	public function getEventIcon(string $subject): string {
		return match ($subject) {
			ActivityConstants::SUBJECT_NEWSHARE, ActivityConstants::SUBJECT_NEWGROUPSHARE => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/shared.svg')),
			ActivityConstants::SUBJECT_NEWSUBMISSION => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/add.svg')),
			default => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('forms', 'forms-dark.svg')),
		};
	}
}
