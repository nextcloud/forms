<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Option>
 */
class OptionMapper extends QBMapper {

	/**
	 * OptionMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_options', Option::class);
	}

	/**
	 * @param int|float $questionId
	 * @return Option[]
	 */
	public function findByQuestion(int|float $questionId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId))
			)
			->orderBy('order')
			->addOrderBy('id');

		return $this->findEntities($qb);
	}

	public function deleteByQuestion(int $questionId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId))
			);

		$qb->executeStatement();
	}

	/**
	 * @param int|float $optionId The option ID (int but for 32bit systems PHP will use float)
	 */
	public function findById(int|float $optionId): Option {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($optionId))
			);

		return $this->findEntity($qb);
	}
}
