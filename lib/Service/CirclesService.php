<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCA\Forms\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Wrapper around circles app API since it is not in a public namespace so we need to make sure that
 * having the app disabled is properly handled
 */
class CirclesService {
	private bool $circlesEnabled;

	private $userCircleCache = [];

	public function __construct(
		IAppManager $appManager,
		private ContainerInterface $container,
		private LoggerInterface $logger,
	) {
		$this->circlesEnabled = $appManager->isEnabledForUser('circles');
	}

	public function isCirclesEnabled(): bool {
		return $this->circlesEnabled;
	}

	public function getCircle(string $circleId): ?Circle {
		if (!$this->circlesEnabled) {
			return null;
		}

		try {
			$circlesManager = $this->container->get(CirclesManager::class);
			// Enforce current user condition since we always want the full list of members
			$circlesManager->startSuperSession();
			return $circlesManager->getCircle($circleId);
		} catch (Throwable $e) {
		}
		return null;
	}

	public function isUserInCircle(string $circleId, string $userId): bool {
		if (!$this->circlesEnabled) {
			return false;
		}

		if (isset($this->userCircleCache[$circleId][$userId])) {
			return $this->userCircleCache[$circleId][$userId];
		}

		try {
			$circlesManager = $this->container->get(CirclesManager::class);
			$federatedUser = $circlesManager->getFederatedUser($userId, Member::TYPE_USER);
			$circlesManager->startSession($federatedUser);
			$circle = $circlesManager->getCircle($circleId);
			$member = $circle->getInitiator();
			$isUserInCircle = $member !== null && $member->getLevel() >= Member::LEVEL_MEMBER;

			if (!isset($this->userCircleCache[$circleId])) {
				$this->userCircleCache[$circleId] = [];
			}
			$this->userCircleCache[$circleId][$userId] = $isUserInCircle;

			return $isUserInCircle;
		} catch (Throwable $e) {
		}
		return false;
	}

	/**
	 * Get the ids of all user which are member of a given circle
	 *
	 * @param string circleId Id of the circle
	 * @return string[]
	 */
	public function getCircleUsers(string $circleId): array {
		if (!$this->circlesEnabled) {
			$this->logger->debug('Teams app is disabled');
			return [];
		}

		$circle = $this->getCircle($circleId);
		if ($circle === null) {
			return [];
		}

		$users = [];
		try {
			$members = $circle->getInheritedMembers();
			$members = array_filter($members, fn ($member) => $member->getUserType() === Member::TYPE_USER);

			$users = array_map(fn ($user) => $user->getUserId(), $members);
		} catch (Throwable $error) {
			$this->logger->debug('Could not fetch users of team', ['error' => $error]);
		}
		return $users;
	}

	public function getUserTeamIds(string $userId): array {
		if (!$this->circlesEnabled) {
			$this->logger->debug('Teams app is disabled');
			return [];
		}

		$teamsManager = $this->container->get(CirclesManager::class);

		try {
			$user = $teamsManager->getLocalFederatedUser($userId);
			$teamsManager->startSession($user);
			$teams = $teamsManager->probeCircles();
			$teamsManager->stopSession();
		} catch (Throwable $error) {
			$this->logger->debug('Could not fetch users of team', ['error' => $error]);
			return [];
		}
		return array_map(fn (Circle $team) => $team->getSingleId(), $teams);
	}
}
