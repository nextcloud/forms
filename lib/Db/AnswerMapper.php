<?php
/**
 * @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Answer>
 */
class AnswerMapper extends QBMapper {

	/**
	 * AnswerMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_answers', Answer::class);
	}

	/**
	 * @param int $submissionId
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 * @return Answer[]
	 */

	public function findBySubmission(int $submissionId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param int $submissionId
	 */
	public function deleteBySubmission(int $submissionId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
		->where(
			$qb->expr()->eq('submission_id', $qb->createNamedParameter($submissionId, IQueryBuilder::PARAM_INT))
		);

		$qb->execute();
	}
}
