<?php

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Question>
 */
class QuestionMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private OptionMapper $optionMapper,
	) {
		parent::__construct($db, 'forms_v2_questions', Question::class);
	}

	/**
	 * @param int $formId
	 * @throws DoesNotExistException if not found
	 * @return Question[]
	 */
	public function findByForm(int $formId, bool $loadDeleted = false): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			);

		if (!$loadDeleted) {
			// Don't load questions, that are marked as deleted (marked by order==0).
			$qb->andWhere(
				$qb->expr()->neq('order', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			);
		}

		// Sort Questions by order
		$qb->orderBy('order');

		return $this->findEntities($qb);
	}

	/**
	 * @param int $formId
	 */
	public function deleteByForm(int $formId): void {
		$qb = $this->db->getQueryBuilder();

		// First delete corresponding options.
		$questionEntities = $this->findByForm($formId, true); // findByForm - loadDeleted=true
		foreach ($questionEntities as $questionEntity) {
			$this->optionMapper->deleteByQuestion($questionEntity->id);
		}

		// Delete Questions
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	/**
	 * Find Question by its ID
	 * @param int|float $questionId The question ID (int but for 32bit systems PHP uses float)
	 */
	public function findById(int|float $questionId): Question {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}
}
