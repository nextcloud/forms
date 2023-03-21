<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
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
	 * Delete a share
	 * @param int $id of the share.
	 */
	public function deleteById(int $id): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
		->where(
			$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
		);
		$qb->execute();
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
		$qb->execute();
	}
}
