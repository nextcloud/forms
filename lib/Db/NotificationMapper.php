<?php
declare(strict_types=1);
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

class NotificationMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_notif', Notification::class);
	}

	/**
	 * @param int $formId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Notification[]
	 */

	 public function findAllByForm(int $formId): array {
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
	 * @param string $userId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @return Notification[]
	 */
	public function findByUserAndForm(int $formId, string $userId): array {
		$qb = $this->db->getQueryBuilder();

		 $qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('form_id', $qb->createNamedParameter($formId, IQueryBuilder::PARAM_INT))
			)
	        ->andWhere(
	        	$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
	        );

		 return $this->findEntities($qb);
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
