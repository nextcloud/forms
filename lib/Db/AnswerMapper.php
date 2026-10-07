<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Answer>
 */
class AnswerMapper extends QBMapper {
	private const CHUNK_SIZE = 1000;

	/**
	 * AnswerMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_answers', Answer::class);
	}

	/**
	 * @param int $submissionId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return Answer[]
	 */
	public function findBySubmission(int $submissionId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param list<int> $submissionIds
	 * @return Answer[]
	 */
	public function findBySubmissions(array $submissionIds): array {
		$answers = [];

		foreach (array_chunk(array_unique($submissionIds), self::CHUNK_SIZE) as $submissionIdsChunk) {
			$qb = $this->db->getQueryBuilder();

			$qb->select('*')
				->from($this->getTableName())
				->where(
					$qb->expr()->in('submission_id', $qb->createNamedParameter($submissionIdsChunk, IQueryBuilder::PARAM_INT_ARRAY))
				);

			$answers[] = $this->findEntities($qb);
		}

		return array_merge([], ...$answers);
	}

	/**
	 * @param int $submissionId
	 */
	public function deleteBySubmission(int $submissionId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}
