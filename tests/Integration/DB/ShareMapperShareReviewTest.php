<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Integration\DB;

use OCA\Forms\Db\ShareMapper;
use OCA\Forms\Tests\Integration\IntegrationBase;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;
use OCP\Share\ShareReview\ShareReviewQuery;

/**
 * Runs the share-review page/count queries against the real database, so the
 * SQL translation of the ShareReviewQuery contract (sorting, search, filters,
 * counts, LIKE escaping) is verified on every supported database engine.
 *
 * @group DB
 */
class ShareMapperShareReviewTest extends IntegrationBase {
	private ShareMapper $shareMapper;

	protected array $users = [
		'alice' => 'Alice',
		'bob' => 'Bob',
	];

	private function setTestForms(): void {
		$formDefaults = [
			'description' => '',
			'access_enum' => 0,
			'state' => 0,
			'is_anonymous' => false,
			'submit_multiple' => true,
			'show_expiration' => false,
			'submission_message' => '',
			'file_id' => null,
			'file_format' => null,
			'questions' => [],
			'submissions' => [],
		];
		$this->testForms = [
			array_merge($formDefaults, [
				'hash' => 'sharereview_budget',
				'title' => 'Budget Report',
				'owner_id' => 'alice',
				'created' => 1000,
				'last_updated' => 5000,
				'expires' => 0,
				'shares' => [
					['shareType' => IShare::TYPE_USER, 'shareWith' => 'bob', 'permissions' => ['submit']],
					['shareType' => IShare::TYPE_LINK, 'shareWith' => 'hashA', 'permissions' => ['submit', 'results']],
				],
			]),
			array_merge($formDefaults, [
				'hash' => 'sharereview_zeta',
				'title' => 'Zeta Survey',
				'owner_id' => 'bob',
				'created' => 2000,
				'last_updated' => 0,
				'expires' => 9000,
				'shares' => [
					['shareType' => IShare::TYPE_USER, 'shareWith' => 'alice', 'permissions' => ['edit']],
					['shareType' => IShare::TYPE_CIRCLE, 'shareWith' => 'teamX', 'permissions' => ['results_delete']],
				],
			]),
		];
	}

	public function setUp(): void {
		$this->setTestForms();
		parent::setUp();
		$this->shareMapper = \OCP\Server::get(ShareMapper::class);

		// A pre-migration share row without permissions (SQL NULL, the submit
		// default) — the fixture base can only write JSON strings.
		$qb = \OCP\Server::get(IDBConnection::class)->getQueryBuilder();
		$qb->insert('forms_v2_shares')
			->values([
				'form_id' => $qb->createNamedParameter($this->testForms[0]['id'], IQueryBuilder::PARAM_INT),
				'share_type' => $qb->createNamedParameter(IShare::TYPE_GROUP, IQueryBuilder::PARAM_INT),
				'share_with' => $qb->createNamedParameter('devs', IQueryBuilder::PARAM_STR),
				'permissions_json' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			]);
		$qb->executeStatement();
		// registered with the fixture so tearDown() removes it
		$this->testForms[0]['shares'][] = ['shareType' => IShare::TYPE_GROUP, 'shareWith' => 'devs', 'id' => $qb->getLastInsertId()];
	}

	/**
	 * @return list<string> the share_with values of a page, in page order
	 */
	private function recipientsOf(ShareReviewQuery $query): array {
		return array_map(static fn (array $row): string => (string)$row['share_with'], $this->shareMapper->findPageForShareReview($query));
	}

	private function filteredCount(ShareReviewQuery $query, ?array $formPermissions = null): int {
		return $this->shareMapper->countForShareReview($query, $formPermissions)->filteredCount;
	}

	public function testUnfilteredCountsAreEqualAndCoverAllShares(): void {
		$counts = $this->shareMapper->countForShareReview(new ShareReviewQuery());

		$this->assertSame(5, $counts->totalCount);
		$this->assertSame(5, $counts->filteredCount);
	}

	public function testDefaultSortIsTimeDescendingWithIdTiebreaker(): void {
		// Budget Report was updated last (5000); Zeta Survey falls back to created (2000)
		$this->assertSame(['devs', 'hashA', 'bob', 'teamX', 'alice'], $this->recipientsOf(new ShareReviewQuery()));
		$this->assertSame(['bob', 'hashA', 'devs', 'alice', 'teamX'], $this->recipientsOf(new ShareReviewQuery(sortDescending: false)));
	}

	public function testPagination(): void {
		$this->assertSame(['devs', 'hashA'], $this->recipientsOf(new ShareReviewQuery(limit: 2)));
		$this->assertSame(['bob', 'teamX'], $this->recipientsOf(new ShareReviewQuery(limit: 2, offset: 2)));
		$this->assertSame(['alice'], $this->recipientsOf(new ShareReviewQuery(limit: 2, offset: 4)));
		$this->assertSame([], $this->recipientsOf(new ShareReviewQuery(limit: 2, offset: 6)));
	}

	public function testSortByObjectInitiatorRecipientAndType(): void {
		$this->assertSame(['bob', 'hashA', 'devs', 'alice', 'teamX'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_OBJECT, sortDescending: false)));
		$this->assertSame(['bob', 'hashA', 'devs', 'alice', 'teamX'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_INITIATOR, sortDescending: false)));
		$this->assertSame(['teamX', 'alice', 'devs', 'hashA', 'bob'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_INITIATOR, sortDescending: true)));
		$this->assertSame(['alice', 'bob', 'devs', 'hashA', 'teamX'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_RECIPIENT, sortDescending: false)));
		// type order 0 user, 1 group, 3 link, 7 circle; ids ascending within a type
		$this->assertSame(['bob', 'alice', 'devs', 'hashA', 'teamX'], $this->recipientsOf(new ShareReviewQuery(sortField: ShareReviewQuery::SORT_TYPE, sortDescending: false)));
	}

	public function testGlobalSearchSpansTitleOwnerAndRecipient(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(search: 'ZETA')));
		// owner of Zeta Survey (2 shares) plus the recipient bob of Budget Report
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(search: 'bob')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: 'nomatch')));
	}

	public function testLikeWildcardsInSearchAreEscaped(): void {
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: '%')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(search: '_')));
	}

	public function testObjectSearchAnyMatchesAnyOfThePatterns(): void {
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['REPORT', 'survey'])));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['survey', 'nomatch'])));
		// AND with the scoped single substring
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['survey'], objectSearch: 'budget')));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: [])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(objectSearchAny: ['%'])));
	}

	public function testScopedSearches(): void {
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(objectSearch: 'report')));
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(initiatorSearch: 'ALI')));
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(recipientSearch: 'hash')));
		// scoped, not global: 'bob' as recipient only
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(recipientSearch: 'bob')));
	}

	public function testIdListsOrMergeWithScopedSearchAndAndCombineOtherwise(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(initiatorIds: ['bob'])));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(initiatorSearch: 'ali', initiatorIds: ['bob'])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(initiatorIds: [])));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(recipientIds: ['bob', 'teamX'])));
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(recipientIds: ['bob', 'teamX'], initiatorIds: ['alice'])));
	}

	public function testShareTypeFilter(): void {
		$this->assertSame(1, $this->filteredCount(new ShareReviewQuery(shareTypes: [IShare::TYPE_LINK])));
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(shareTypes: [IShare::TYPE_USER, IShare::TYPE_CIRCLE])));
		// a type forms never produces
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(shareTypes: [IShare::TYPE_EMAIL])));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(shareTypes: [])));
	}

	public function testPasswordFilterIsConstantFalse(): void {
		$counts = $this->shareMapper->countForShareReview(new ShareReviewQuery(hasPassword: true));
		$this->assertSame(5, $counts->totalCount);
		$this->assertSame(0, $counts->filteredCount);
		$this->assertSame([], $this->recipientsOf(new ShareReviewQuery(hasPassword: true)));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(hasPassword: false)));
	}

	public function testExpirationFiltersUseTheFormExpiry(): void {
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(hasExpiration: true)));
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(hasExpiration: false)));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(expiresBeforeTimestamp: 10000)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(expiresBeforeTimestamp: 9000)));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(expiresAfterTimestamp: 9000)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(expiresAfterTimestamp: 9001)));
		$this->assertSame(2, $this->filteredCount(new ShareReviewQuery(expiresAfterTimestamp: 8000, expiresBeforeTimestamp: 10000)));
	}

	public function testModifiedSinceUsesLastUpdatedWithCreatedFallback(): void {
		$this->assertSame(3, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: 3000)));
		$this->assertSame(5, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: 1999)));
		$this->assertSame(0, $this->filteredCount(new ShareReviewQuery(modifiedSinceTimestamp: 5000)));
	}

	public function testPermissionFilterMatchesQuotedNamesAndTheNullSubmitDefault(): void {
		$query = new ShareReviewQuery();
		$this->assertSame(1, $this->filteredCount($query, ['results']));
		$this->assertSame(1, $this->filteredCount($query, ['results_delete']));
		// explicit submit on two shares plus the NULL default row
		$this->assertSame(3, $this->filteredCount($query, ['submit']));
		$this->assertSame(2, $this->filteredCount($query, ['edit', 'results_delete']));
		$this->assertSame(0, $this->filteredCount($query, []));
	}

	public function testCountByTypeAppliesFiltersAndOmitsZeroCounts(): void {
		$byType = $this->shareMapper->countByTypeForShareReview(new ShareReviewQuery());
		ksort($byType);
		$this->assertSame([IShare::TYPE_USER => 2, IShare::TYPE_GROUP => 1, IShare::TYPE_LINK => 1, IShare::TYPE_CIRCLE => 1], $byType);

		$byType = $this->shareMapper->countByTypeForShareReview(new ShareReviewQuery(initiatorIds: ['bob']));
		ksort($byType);
		$this->assertSame([IShare::TYPE_USER => 1, IShare::TYPE_CIRCLE => 1], $byType);
	}

	public function testFindByIdCarriesTheFormColumns(): void {
		$row = $this->shareMapper->findByIdForShareReview((int)$this->testForms[1]['shares'][1]['id']);

		$this->assertNotNull($row);
		$this->assertSame('teamX', $row['share_with']);
		$this->assertSame('Zeta Survey', $row['form_title']);
		$this->assertSame('bob', $row['form_owner']);
		$this->assertSame(9000, (int)$row['form_expires']);
		$this->assertNull($this->shareMapper->findByIdForShareReview(0));
	}
}
