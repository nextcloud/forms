<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<SubmissionVerification>
 */
class SubmissionVerificationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_submission_verify', SubmissionVerification::class);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function findBySubmissionId(int $submissionId): SubmissionVerification {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	public function findByTokenHash(string $tokenHash): SubmissionVerification {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('token_hash', $qb->createNamedParameter($tokenHash, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}
}
