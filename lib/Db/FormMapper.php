<?php
/**
 * @copyright Copyright (c) 2017 Vinzenz Rosenkranz <vinzenz.rosenkranz@gmail.com>
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

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Form>
 */
class FormMapper extends QBMapper {
	/** @var QuestionMapper */
	private $questionMapper;

	/** @var ShareMapper */
	private $shareMapper;

	/** @var SubmissionMapper */
	private $submissionMapper;

	/**
	 * FormMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(QuestionMapper $questionMapper,
		ShareMapper $shareMapper,
		SubmissionMapper $submissionMapper,
		IDBConnection $db) {
		parent::__construct($db, 'forms_v2_forms', Form::class);
		$this->questionMapper = $questionMapper;
		$this->shareMapper = $shareMapper;
		$this->submissionMapper = $submissionMapper;
	}

	/**
	 * @param int $id
	 * @return Form
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findById(int $id): Form {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @param string $hash
	 * @return Form
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
	 */
	public function findByHash(string $hash): Form {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('hash', $qb->createNamedParameter($hash, IQueryBuilder::PARAM_STR))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @return Form[]
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			//Last updated forms first, then newest forms first
			->addOrderBy('last_updated', 'DESC')
			->addOrderBy('created', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * @return Form[]
	 */
	public function findAllByOwnerId(string $ownerId): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId))
			)
			//Last updated forms first, then newest forms first
			->addOrderBy('last_updated', 'DESC')
			->addOrderBy('created', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Delete a Form including connected Questions, Submissions and shares.
	 * @param Form $form The form instance to delete
	 */
	public function deleteForm(Form $form): void {
		// Delete Submissions(incl. Answers), Questions(incl. Options), Shares and Form.
		$this->submissionMapper->deleteByForm($form->getId());
		$this->shareMapper->deleteByForm($form->getId());
		$this->questionMapper->deleteByForm($form->getId());
		$this->delete($form);
	}
}
