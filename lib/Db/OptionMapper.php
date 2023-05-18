<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
 *
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

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Option>
 */
class OptionMapper extends QBMapper {

	/**
	 * OptionMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_options', Option::class);
	}

	/**
	 * @param int $questionId
	 * @throws DoesNotExistException if not found
	 * @return Option[]
	 */

	public function findByQuestion(int $questionId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId))
			)
			->orderBy('id');

		return $this->findEntities($qb);
	}

	public function deleteByQuestion(int $questionId): void {
		$qb = $this->db->getQueryBuilder();

		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('question_id', $qb->createNamedParameter($questionId))
			);

		$qb->executeStatement();
	}

	public function findById(int $optionId): Option {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($optionId))
			);

		return $this->findEntity($qb);
	}
}
