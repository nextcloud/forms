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
	 * Retrieves a list of submissions for a specific form.
	 *
	 * @param int $formId The ID of the form whose submissions are being retrieved.
	 * @param string|null $userId An optional user ID to filter the submissions.
	 * @param string|null $searchString An optional search query to filter the submissions.
	 * @param int|null $limit The maximum number of submissions to retrieve, default: all submissions
	 * @param int $offset The number of submissions to skip before starting to retrieve, default: 0
	 *
	 * @return Submission[] An array of Submission objects.
	 * @throws DoesNotExistException If no submissions are found for the given form ID.
	 *
	 */
	public function findByForm(int $formId, ?string $userId = null, ?string $searchString = null, ?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();

		$filters = [
			$qb->expr()->eq('submissions.form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT)),
		];
		if ($userId) {
			$filters[] = $qb->expr()->eq('submissions.user_id', $qb->createNamedParameter($userId));
		}

		// Select all columns from the submissions table
		$qb->selectDistinct('submissions.*')
			->from($this->getTableName(), 'submissions')
			->where(...$filters)
			// Newest submissions first
			->orderBy('submissions.timestamp', 'DESC')
			->setFirstResult($offset)
			->setMaxResults($limit);

		// If a query is provided, join the answers table and filter by the query text
		if (!is_null($searchString) && $searchString !== '') {
			$qb->join(
				'submissions',
				$this->answerMapper->getTableName(),
				'answers',
				$qb->expr()->eq('submissions.id', 'answers.submission_id')
			)
				->andWhere(
					$qb->expr()->orX(
						$qb->expr()->iLike('submissions.user_id', $qb->createNamedParameter('%' . $searchString . '%')),
						$qb->expr()->iLike('answers.text', $qb->createNamedParameter('%' . $searchString . '%')),
					),
				);
		}

		return $this->findEntities($qb);
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
	 * Counts the number of submissions associated with a specific form.
	 *
	 * @param int $formId The ID of the form for which submissions are to be counted.
	 * @param ?string $searchString An optional search string to filter submissions by their answers.
	 * @return int The total number of submissions for the specified form.
	 * @throws \Exception If an error occurs during the count operation.
	 */
	public function countSubmissions(int $formId, ?string $userId = null, ?string $searchString = null): int {
		return $this->countSubmissionsWithFilters($formId, $userId, -1, $searchString);
	}

	/**
	 * Count submissions by form with optional filters.
	 *
	 * @param int $formId The ID of the form for which submissions are to be counted.
	 * @param string|null $userId Optionally limit submissions to those made by the specified user.
	 * @param int $limit The maximum number of submissions to count. If -1, no limit is applied.
	 * @param string|null $searchString An optional search string to filter submissions by their answers.
	 *
	 * @return int The total number of submissions matching the specified filters.
	 *
	 * @throws \Exception If an error occurs during the count operation.
	 */
	protected function countSubmissionsWithFilters(int $formId, ?string $userId = null, int $limit = -1, ?string $searchString = null): int {
		$qb = $this->db->getQueryBuilder();

		$query = $qb->select('submissions.id')
			->from($this->getTableName(), 'submissions')
			->where($qb->expr()->eq('submissions.form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT)))
			->groupBy('submissions.id');

		if (!is_null($userId)) {
			$query->andWhere($qb->expr()->eq('submissions.user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
		}

		if (!is_null($searchString) && $searchString !== '') {
			$query->join(
				'submissions',
				$this->answerMapper->getTableName(),
				'answers',
				$qb->expr()->eq('submissions.id', 'answers.submission_id')
			)
				->andWhere(
					$qb->expr()->orX(
						$qb->expr()->iLike('submissions.user_id', $qb->createNamedParameter('%' . $searchString . '%')),
						$qb->expr()->iLike('answers.text', $qb->createNamedParameter('%' . $searchString . '%')),
					),
				);
		}

		if ($limit !== -1) {
			$query->setMaxResults($limit);
		}

		$result = $query->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return count($rows);
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
