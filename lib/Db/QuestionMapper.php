<?php
/**
 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Question>
 */
class QuestionMapper extends QBMapper {
	private $optionMapper;

	public function __construct(IDBConnection $db, OptionMapper $optionMapper) {
		parent::__construct($db, 'forms_v2_questions', Question::class);

		$this->optionMapper = $optionMapper;
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
