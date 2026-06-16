<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Search;

use OCA\Forms\AppInfo\Application;
use OCA\Forms\Db\Form;
use OCA\Forms\Service\FormsService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;

class SearchProvider implements IProvider {
	/**
	 * @psalm-suppress PossiblyUnusedMethod
	 */
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly FormsService $formsService,
	) {
	}

	public function getId(): string {
		return 'forms';
	}

	public function getName(): string {
		return $this->l10n->t('Forms');
	}

	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$forms = $this->formsService->search($query);

		$results = array_map(fn (Form $form) => [
			'object' => $form,
			'entry' => new FormsSearchResultEntry($form, $this->urlGenerator)
		], $forms);

		$resultEntries = array_map(fn (array $result) => $result['entry'], $results);

		return SearchResult::complete(
			$this->l10n->t('Forms'),
			$resultEntries
		);
	}

	public function getOrder(string $route, array $routeParameters): int {
		if (str_contains($route, Application::APP_ID)) {
			// Active app, prefer my results
			return -1;
		}
		return 77;
	}
}
