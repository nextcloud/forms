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

use OCA\Forms\Db\OptionMapper;

class QuestionMapper extends QBMapper {

	private $optionMapper;

	public function __construct(IDBConnection $db, OptionMapper $optionMapper) {
		parent::__construct($db, 'forms_v2_questions', Question::class);

		$this->optionMapper = $optionMapper;
	}

	/**
	 * @param int $formId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
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

		$qb->execute();
	}

	public function findById(int $questionId): Question {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($questionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

}
