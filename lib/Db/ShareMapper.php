<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;

/**
 * @extends QBMapper<Share>
 */
class ShareMapper extends QBMapper {
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
	 * Fetch all share rows with their form title, owner and timestamps for ShareReview.
	 *
	 * @return list<array<string, mixed>>
	 * @throws Exception
	 */
	public function findAllForShareReview(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('s.id', 's.form_id', 's.share_type', 's.share_with', 's.permissions_json')
			->selectAlias('f.title', 'form_title')
			->selectAlias('f.owner_id', 'form_owner')
			->selectAlias('f.created', 'form_created')
			->selectAlias('f.last_updated', 'form_last_updated')
			->selectAlias('f.expires', 'form_expires')
			->from($this->getTableName(), 's')
			->leftJoin('s', 'forms_v2_forms', 'f', $qb->expr()->eq('s.form_id', 'f.id'))
			->orderBy('s.id', 'ASC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
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
