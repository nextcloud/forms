<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Submission>
 */
class SubmissionMapper extends QBMapper {
	/**
	 * SubmissionMapper constructor.
	 * @param IDBConnection $db
	 * @param AnswerMapper $answerMapper
	 */
	public function __construct(
		IDBConnection $db,
		private AnswerMapper $answerMapper,
	) {
		parent::__construct($db, 'forms_v2_submissions', Submission::class);
	}

	/**
	 * @param int $formId
	 * @throws DoesNotExistException if not found
	 * @return Submission[]
	 */
	public function findByForm(int $formId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			)
			//Newest submissions first
			->orderBy('timestamp', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @param int $formId
	 * @param string $userId
	 *
	 * @return Submission
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByFormAndUser(int $formId, string $userId): Submission {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			)
			//Newest submissions first
			->orderBy('timestamp', 'DESC');

		return $this->findEntity($qb);
	}

	/**
	 * @param int $id
	 * @return Submission
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findById(int $id): Submission {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * Сhecks if there are multiple form submissions by user
	 * @param Form $form of the form to count submissions
	 * @param string $userId ID of the user to count submissions
	 */
	public function hasMultipleFormSubmissionsByUser(Form $form, string $userId): bool {
		return $this->countSubmissionsWithFilters($form->getId(), $userId, 2) >= 2;
	}

	/**
	 * Сhecks if there are form submissions by user
	 * @param Form $form of the form to count submissions
	 * @param string $userId ID of the user to count submissions
	 */
	public function hasFormSubmissionsByUser(Form $form, string $userId): bool {
		return (bool)$this->countSubmissionsWithFilters($form->getId(), $userId, 1);
	}

	/**
	 * Count submissions by form
	 * @param int $formId ID of the form to count submissions
	 * @throws \Exception
	 */
	public function countSubmissions(int $formId): int {
		return $this->countSubmissionsWithFilters($formId, null, -1);
	}

	/**
	 * Count submissions by form with optional filters
	 * @param int $formId ID of the form to count submissions
	 * @param string|null $userId optionally limit submissions to the one of that user
	 * @param int $limit allows to limit the query selection. If -1, the restriction is ignored
	 * @throws \Exception
	 */
	protected function countSubmissionsWithFilters(int $formId, ?string $userId = null, int $limit = -1): int {
		$qb = $this->db->getQueryBuilder();

		$query = $qb->select($qb->func()->count('*', 'num_submissions'))
			->from($this->getTableName())
			->where($qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT)));
		if (!is_null($userId)) {
			$query->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		}
		if ($limit !== -1) {
			$query->setMaxResults($limit);
		}

		$result = $query->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return (int)($row['num_submissions'] ?? 0);
	}

	/**
	 * Delete the Submission, including answers.
	 * @param int $id of the submission to delete
	 */
	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		// First delete corresponding answers.
		$submissionEntity = $this->findById($id);
		$this->answerMapper->deleteBySubmission($submissionEntity->getId());

		//Delete Submission
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}

	/**
	 * Delete all Submissions corresponding to form, including answers.
	 * @param int $formId
	 */
	public function deleteByForm(int $formId): void {
		$qb = $this->db->getQueryBuilder();

		// First delete corresponding answers.
		$submissionEntities = $this->findByForm($formId);
		foreach ($submissionEntities as $submissionEntity) {
			$this->answerMapper->deleteBySubmission($submissionEntity->id);
		}

		//Delete Submissions
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			);

		$qb->executeStatement();
	}
}
