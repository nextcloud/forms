<?php
/**
 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
 *
 * @author Inigo Jiron <ijiron@terpmail.umd.edu>
 * @author Natalie Gilbert <ngilb634@umd.edu>
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

class QuestionMapper extends QBMapper {

	/**
	 * TextMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_questions', Question::class);
	}

	/**
	 * @param int $formId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return Option[]
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
