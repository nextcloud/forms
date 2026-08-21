<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Tests\Unit\Db;

use OCA\Forms\Db\ShareMapper;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ShareMapperTest extends TestCase {
	private IDBConnection|MockObject $db;
	private IQueryBuilder|MockObject $qb;
	private ShareMapper $shareMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->qb = $this->createMock(IQueryBuilder::class);
		$this->qb->method('select')->willReturnSelf();
		$this->qb->method('selectAlias')->willReturnSelf();
		$this->qb->method('from')->willReturnSelf();
		$this->qb->method('leftJoin')->willReturnSelf();
		$this->qb->method('orderBy')->willReturnSelf();
		$this->qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));

		$this->db = $this->createMock(IDBConnection::class);
		$this->db->method('getQueryBuilder')->willReturn($this->qb);

		$this->shareMapper = new ShareMapper($this->db);
	}

	public function testFindAllForShareReviewSelectsSharesJoinedWithForms(): void {
		$rows = [
			[
				'id' => 1,
				'form_id' => 10,
				'share_type' => 0,
				'share_with' => 'bob',
				'permissions_json' => '["submit"]',
				'form_title' => 'My Form',
				'form_owner' => 'alice',
				'form_created' => 1000,
				'form_last_updated' => 2000,
				'form_expires' => 0,
			],
		];

		$this->qb->expects($this->once())
			->method('select')
			->with('s.id', 's.form_id', 's.share_type', 's.share_with', 's.permissions_json');
		$this->qb->expects($this->once())
			->method('from')
			->with('forms_v2_shares', 's');
		$this->qb->expects($this->once())
			->method('leftJoin')
			->with('s', 'forms_v2_forms', 'f', $this->anything());
		$this->qb->expects($this->once())
			->method('orderBy')
			->with('s.id', 'ASC');

		$result = $this->createMock(IResult::class);
		$result->expects($this->once())
			->method('fetchAll')
			->willReturn($rows);
		$result->expects($this->once())
			->method('closeCursor');

		$this->qb->expects($this->once())
			->method('executeQuery')
			->willReturn($result);

		$this->assertSame($rows, $this->shareMapper->findAllForShareReview());
	}

	public function testFindAllForShareReviewWithoutShares(): void {
		$result = $this->createMock(IResult::class);
		$result->method('fetchAll')->willReturn([]);
		$result->expects($this->once())
			->method('closeCursor');
		$this->qb->method('executeQuery')->willReturn($result);

		$this->assertSame([], $this->shareMapper->findAllForShareReview());
	}
}
