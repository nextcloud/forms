<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 *
 * @author Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
 * @author René Gieling <github@dartcafe.de>
*
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Forms\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;

class VoteMapper extends QBMapper {

	/**
	 * VoteMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_votes', Vote::class);
	}

	/**
	 * @param int $formId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return Comment[]
	 */

	public function findByForm(int $formId): array {
		$qb = $this->db->getQueryBuilder();

        $qb->select('*')
           ->from($this->getTableName())
           ->where(
               $qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
           );

        return $this->findEntities($qb);
	}
	
	/**
	 * @param int $formId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return array
	 */
	public function findParticipantsByForm(int $formId, $limit = null, $offset = null): array {
		$qb = $this->db->getQueryBuilder();

        $qb->select('user_id')
           ->from($this->getTableName())
           ->where(
               $qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
           );

        return $this->findEntities($qb);
	}

	/**
	 * @param int $formId
	 * @param string $userId
	 */
	 public function deleteByFormAndUser(int $formId, string $userId): void {
		$qb = $this->db->getQueryBuilder();

        $qb->delete($this->getTableName())
           ->where(
               $qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
           )
           ->andWhere(
               $qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
           );

	   $qb->execute();
	}

	/**
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
