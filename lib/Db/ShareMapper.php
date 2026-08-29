<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCA\Forms\Constants;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IParameter;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;
use OCP\Share\ShareReview\ShareReviewCounts;
use OCP\Share\ShareReview\ShareReviewQuery;

/**
 * @extends QBMapper<Share>
 */
class ShareMapper extends QBMapper {
	/**
	 * Sort fields of the share-review contract mapped to their column. The time
	 * sort is an expression (see shareReviewTimeExpression()) and resolved
	 * separately; user input never reaches the query other than through this
	 * whitelist and bound parameters.
	 */
	private const SHARE_REVIEW_SORT_COLUMNS = [
		ShareReviewQuery::SORT_OBJECT => 'f.title',
		ShareReviewQuery::SORT_INITIATOR => 'f.owner_id',
		ShareReviewQuery::SORT_RECIPIENT => 's.share_with',
		ShareReviewQuery::SORT_TYPE => 's.share_type',
	];

	/**
	 * ShareMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_shares', Share::class);
	}

	/**
	 * Find a Share
	 * @param int $id
	 * @return Share
	 * @throws MultipleObjectsReturnedException if more than one result
	 * @throws DoesNotExistException if not found
	 */
	public function findById(int $id): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * Find Shares corresponding to a form.
	 * @param int $formId
	 * @return Share[]
	 */
	public function findByForm(int $formId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('share_type', 'ASC'); //Already order by ShareType

		return $this->findEntities($qb);
	}

	/**
	 * Find Public Share by Hash
	 * @param string $hash
	 * @return Share
	 * @throws MultipleObjectsReturnedException if more than one result
	 * @throws DoesNotExistException if not found
	 */
	public function findPublicShareByHash(string $hash): Share {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_LINK, IQueryBuilder::PARAM_INT))
			)
			->andWhere(
				$qb->expr()->eq('share_with', $qb->createNamedParameter($hash, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * Fetch one page of share rows with their form title, owner and timestamps
	 * for ShareReview, sorted, searched and filtered as the query demands.
	 *
	 * @param list<string>|null $formPermissions native form permissions
	 *                                           (Constants::PERMISSION_*) the
	 *                                           share must grant at least one
	 *                                           of; null = no permission filter,
	 *                                           [] = nothing matches
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findPageForShareReview(ShareReviewQuery $query, ?array $formPermissions = null): array {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$this->applyShareReviewFilters($qb, $query, $formPermissions);
		$this->applyShareReviewOrder($qb, $query);
		$qb->setFirstResult($query->offset)
			->setMaxResults($query->limit);

		$result = $qb->executeQuery();
		/** @var list<array<string, mixed>> $rows */
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * All share rows with their form title, owner and timestamps, in the
	 * findPageForShareReview() shape, streamed in immutable id order so the
	 * enumeration stays stable under concurrent edits and the full list is
	 * never held in memory.
	 *
	 * @return \Generator<int, array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllForShareReview(): \Generator {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$qb->orderBy('s.id', 'ASC');
		$result = $qb->executeQuery();
		try {
			while (($row = $result->fetch()) !== false) {
				yield $row;
			}
		} finally {
			$result->closeCursor();
		}
	}

	/**
	 * Fetch one share row for ShareReview by its id, in the same shape as
	 * findPageForShareReview() rows.
	 *
	 * @return array<string, mixed>|null
	 * @throws Exception
	 */
	public function findByIdForShareReview(int $id): ?array {
		$qb = $this->shareReviewQuery();
		$this->selectShareReviewColumns($qb);
		$qb->where($qb->expr()->eq('s.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row === false ? null : $row;
	}

	/**
	 * Count all shares and the shares matching the query's search and filters.
	 * The filtered count is only computed when the query narrows the result.
	 *
	 * @param list<string>|null $formPermissions see findPageForShareReview()
	 * @throws Exception
	 */
	public function countForShareReview(ShareReviewQuery $query, ?array $formPermissions = null): ShareReviewCounts {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from($this->getTableName());
		$result = $qb->executeQuery();
		$total = (int)$result->fetchOne();
		$result->closeCursor();

		if (!$query->isFiltered() && $formPermissions === null) {
			return new ShareReviewCounts($total, $total);
		}

		$qb = $this->shareReviewQuery();
		$qb->select($qb->func()->count('s.id'));
		$this->applyShareReviewFilters($qb, $query, $formPermissions);
		$result = $qb->executeQuery();
		$filtered = (int)$result->fetchOne();
		$result->closeCursor();
		return new ShareReviewCounts($total, $filtered);
	}

	/**
	 * Count the shares matching the query's search and filters per share type.
	 *
	 * @param list<string>|null $formPermissions see findPageForShareReview()
	 * @return array<int, int> native share type to count, zero counts omitted
	 * @throws Exception
	 */
	public function countByTypeForShareReview(ShareReviewQuery $query, ?array $formPermissions = null): array {
		$qb = $this->shareReviewQuery();
		$qb->select('s.share_type')
			->selectAlias($qb->func()->count('s.id'), 'share_count')
			->groupBy('s.share_type');
		$this->applyShareReviewFilters($qb, $query, $formPermissions);

		$result = $qb->executeQuery();
		$counts = [];
		while (($row = $result->fetch()) !== false) {
			$counts[(int)$row['share_type']] = (int)$row['share_count'];
		}
		$result->closeCursor();
		return $counts;
	}

	/**
	 * Shares joined with their form, the base of every share-review query.
	 */
	private function shareReviewQuery(): IQueryBuilder {
		$qb = $this->db->getQueryBuilder();
		$qb->from($this->getTableName(), 's')
			->leftJoin('s', 'forms_v2_forms', 'f', $qb->expr()->eq('s.form_id', 'f.id'));
		return $qb;
	}

	private function selectShareReviewColumns(IQueryBuilder $qb): void {
		$qb->select('s.id', 's.form_id', 's.share_type', 's.share_with', 's.permissions_json')
			->selectAlias('f.title', 'form_title')
			->selectAlias('f.owner_id', 'form_owner')
			->selectAlias('f.created', 'form_created')
			->selectAlias('f.last_updated', 'form_last_updated')
			->selectAlias('f.expires', 'form_expires');
	}

	/**
	 * The share's time as exposed to share review: the form's last update,
	 * falling back to its creation when no update was recorded yet (0 or NULL).
	 */
	private function shareReviewTimeExpression(IQueryBuilder $qb): string {
		return 'COALESCE(NULLIF(' . $qb->getColumnName('f.last_updated') . ', 0), ' . $qb->getColumnName('f.created') . ')';
	}

	/**
	 * Translate the share-review query into WHERE clauses. Forms shares carry no
	 * password, so a password filter for protected shares matches nothing; the
	 * expiration is the form's.
	 *
	 * @param list<string>|null $formPermissions see findPageForShareReview()
	 */
	private function applyShareReviewFilters(IQueryBuilder $qb, ShareReviewQuery $query, ?array $formPermissions): void {
		$expr = $qb->expr();
		// A column that is never NULL, negated: the portable "matches nothing"
		$matchesNothing = $expr->isNull('s.id');

		if ($query->search !== null) {
			$pattern = $this->shareReviewLikePattern($qb, $query->search);
			$qb->andWhere($expr->orX(
				$expr->iLike('f.title', $pattern),
				$expr->iLike('f.owner_id', $pattern),
				$expr->iLike('s.share_with', $pattern),
			));
		}
		if ($query->objectSearch !== null) {
			$qb->andWhere($expr->iLike('f.title', $this->shareReviewLikePattern($qb, $query->objectSearch)));
		}
		if ($query->objectSearchAny !== null) {
			$qb->andWhere($query->objectSearchAny === []
				? $matchesNothing
				: $expr->orX(...array_map(fn (string $term): string => $expr->iLike('f.title', $this->shareReviewLikePattern($qb, $term)), $query->objectSearchAny)));
		}
		$this->applyShareReviewIdentityFilter($qb, 'f.owner_id', $query->initiatorSearch, $query->initiatorIds);
		$this->applyShareReviewIdentityFilter($qb, 's.share_with', $query->recipientSearch, $query->recipientIds);

		if ($query->modifiedSinceTimestamp !== null) {
			$qb->andWhere($expr->gt(
				$qb->createFunction($this->shareReviewTimeExpression($qb)),
				$qb->createNamedParameter($query->modifiedSinceTimestamp, IQueryBuilder::PARAM_INT),
			));
		}
		if ($query->shareTypes !== null) {
			if ($query->shareTypes === []) {
				$qb->andWhere($matchesNothing);
			} else {
				// a NULL share_type is rendered and counted as a user share,
				// so the filter must catch it for that type too
				$typeMatch = $expr->in('s.share_type', $qb->createNamedParameter($query->shareTypes, IQueryBuilder::PARAM_INT_ARRAY));
				$qb->andWhere(in_array(IShare::TYPE_USER, $query->shareTypes, true)
					? $expr->orX($typeMatch, $expr->isNull('s.share_type'))
					: $typeMatch);
			}
		}
		if ($query->tokens !== null) {
			// a form's public hash is the token of its link share; exact
			// match by contract, never a substring
			$qb->andWhere($query->tokens === []
				? $matchesNothing
				: $expr->andX(
					$expr->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_LINK, IQueryBuilder::PARAM_INT)),
					$expr->in('s.share_with', $qb->createNamedParameter($query->tokens, IQueryBuilder::PARAM_STR_ARRAY)),
				));
		}
		if ($query->hasPassword === true) {
			$qb->andWhere($matchesNothing);
		}

		$hasExpiration = $expr->gt('f.expires', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT));
		if ($query->hasExpiration === true) {
			$qb->andWhere($hasExpiration);
		} elseif ($query->hasExpiration === false) {
			$qb->andWhere($expr->orX(
				$expr->isNull('f.expires'),
				$expr->eq('f.expires', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)),
			));
		}
		if ($query->expiresAfterTimestamp !== null) {
			$qb->andWhere($hasExpiration)
				->andWhere($expr->gte('f.expires', $qb->createNamedParameter($query->expiresAfterTimestamp, IQueryBuilder::PARAM_INT)));
		}
		if ($query->expiresBeforeTimestamp !== null) {
			$qb->andWhere($hasExpiration)
				->andWhere($expr->lt('f.expires', $qb->createNamedParameter($query->expiresBeforeTimestamp, IQueryBuilder::PARAM_INT)));
		}

		if ($formPermissions !== null) {
			$this->applyShareReviewPermissionFilter($qb, $formPermissions);
		}
	}

	/**
	 * Scoped substring and exact id list on one identity column, OR-combined
	 * with each other (so a display-name search resolved to ids and a uid
	 * substring both match) and AND-combined with everything else.
	 *
	 * @param list<string>|null $ids
	 */
	private function applyShareReviewIdentityFilter(IQueryBuilder $qb, string $column, ?string $search, ?array $ids): void {
		$predicates = [];
		if ($search !== null) {
			$predicates[] = $qb->expr()->iLike($column, $this->shareReviewLikePattern($qb, $search));
		}
		if ($ids !== null) {
			$predicates[] = $ids === []
				? $qb->expr()->isNull('s.id')
				: $qb->expr()->in($column, $qb->createNamedParameter($ids, IQueryBuilder::PARAM_STR_ARRAY));
		}
		if ($predicates !== []) {
			$qb->andWhere($qb->expr()->orX(...$predicates));
		}
	}

	/**
	 * ANY-of filter on the native form permissions. The JSON column holds a
	 * list of permission names, NULL meaning the submit default; a name is
	 * matched as a quoted JSON string so "results" cannot match
	 * "results_delete".
	 *
	 * @param list<string> $formPermissions
	 */
	private function applyShareReviewPermissionFilter(IQueryBuilder $qb, array $formPermissions): void {
		$expr = $qb->expr();
		if ($formPermissions === []) {
			$qb->andWhere($expr->isNull('s.id'));
			return;
		}
		$permissionsText = $expr->castColumn('s.permissions_json', IQueryBuilder::PARAM_STR);
		$predicates = [];
		foreach ($formPermissions as $permission) {
			$pattern = $qb->createNamedParameter('%' . $this->db->escapeLikeParameter('"' . $permission . '"') . '%');
			$predicates[] = $expr->like($permissionsText, $pattern);
			if ($permission === Constants::PERMISSION_SUBMIT) {
				$predicates[] = $expr->isNull('s.permissions_json');
			}
		}
		$qb->andWhere($expr->orX(...$predicates));
	}

	/**
	 * Case-insensitive substring pattern with the LIKE wildcards of the input
	 * escaped, bound as a parameter.
	 */
	private function shareReviewLikePattern(IQueryBuilder $qb, string $term): IParameter {
		return $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($term) . '%');
	}

	/**
	 * ORDER BY through the sort whitelist, NULL sort keys last in both
	 * directions (databases disagree on the default; the joined form columns
	 * and the time expression are NULL for orphaned shares), and the share id
	 * as tiebreaker in the same direction so equal keys never straddle pages
	 * nondeterministically.
	 */
	private function applyShareReviewOrder(IQueryBuilder $qb, ShareReviewQuery $query): void {
		$direction = $query->sortDescending ? 'DESC' : 'ASC';
		$sortExpression = $query->sortField === ShareReviewQuery::SORT_TIME
			? $this->shareReviewTimeExpression($qb)
			: $qb->getColumnName(self::SHARE_REVIEW_SORT_COLUMNS[$query->sortField]);
		$qb->orderBy($qb->createFunction('CASE WHEN ' . $sortExpression . ' IS NULL THEN 1 ELSE 0 END'), 'ASC');
		$qb->addOrderBy($qb->createFunction($sortExpression), $direction);
		$qb->addOrderBy('s.id', $direction);
	}

	/**
	 * Delete all Shares of a form.
	 * @param int $formId
	 */
	public function deleteByForm(int $formId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}
}
