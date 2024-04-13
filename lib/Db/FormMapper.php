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

use OCA\Forms\Constants;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * @extends QBMapper<Form>
 */
class FormMapper extends QBMapper {
	/**
	 * FormMapper constructor.
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(
		private QuestionMapper $questionMapper,
		private ShareMapper $shareMapper,
		private SubmissionMapper $submissionMapper,
		private LoggerInterface $logger,
		IDBConnection $db,
	) {
		parent::__construct($db, 'forms_v2_forms', Form::class);
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
	 * Get forms shared with the user
	 * @param string $userId The user ID
	 * @param string[] $groups IDs of groups the user is memeber of
	 * @param string[] $teams IDs of teams the user is memeber of
	 * @param bool $filterShown Set to false to also include forms shared but not visible on sidebar
	 * @return Form[]
	 */
	public function findSharedForms(string $userId, array $groups = [], array $teams = [], bool $filterShown = true): array {
		$qbForms = $this->db->getQueryBuilder();
		$qbShares = $this->db->getQueryBuilder();

		$memberships = $qbShares->expr()->orX();
		// share type user and share with current user
		$memberships->add(
			$qbShares->expr()->andX(
				$qbShares->expr()->eq('share_type', $qbShares->createNamedParameter(IShare::TYPE_USER)),
				$qbShares->expr()->eq('share_with', $qbShares->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
			),
		);
		// share type group and one of the user groups
		if (!empty($groups)) {
			$memberships->add(
				$qbShares->expr()->andX(
					$qbShares->expr()->eq('share_type', $qbShares->createNamedParameter(IShare::TYPE_GROUP)),
					$qbShares->expr()->in('share_with', $qbShares->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY)),
				),
			);
		}
		// share type team and one of the user teams
		if (!empty($teams)) {
			$memberships->add(
				$qbShares->expr()->andX(
					$qbShares->expr()->eq('share_type', $qbShares->createNamedParameter(IShare::TYPE_CIRCLE)),
					$qbShares->expr()->in('share_with', $qbShares->createNamedParameter($teams, IQueryBuilder::PARAM_STR_ARRAY)),
				),
			);
		}

		// get form_id's that are shared to user
		$qbShares->selectDistinct('form_id')
			->from('forms_v2_shares')
			->where($memberships);
		$sharedFormIdsResult = $qbShares->executeQuery();
		$sharedFormIds = [];
		for ($i = 0; $i < $sharedFormIdsResult->rowCount(); $i++) {
			$sharedFormIds[] = $sharedFormIdsResult->fetchOne();
		}

		// build expression for publicy shared forms (default only directly shown)
		if ($filterShown) {
			$access = $qbForms->expr()->in('access_enum', $qbForms->createNamedParameter(Constants::FORM_ACCESS_ARRAY_SHOWN, IQueryBuilder::PARAM_INT_ARRAY));
		} else {
			$access = $qbForms->expr()->in('access_enum', $qbForms->createNamedParameter(Constants::FORM_ACCESS_ARRAY_PERMIT, IQueryBuilder::PARAM_INT_ARRAY));
		}

		$whereTerm = $qbForms->expr()->orX();
		$whereTerm->add($qbForms->expr()->in('id', $qbForms->createNamedParameter($sharedFormIds, IQueryBuilder::PARAM_INT_ARRAY)));
		$whereTerm->add($access);

		$qbForms->select('*')
			->from($this->getTableName())
			// user is member of or form is publicly shared
			->where($whereTerm)
			// ensure not to include owned forms
			->andWhere($qbForms->expr()->neq('owner_id', $qbForms->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			//Last updated forms first, then newest forms first
			->addOrderBy('last_updated', 'DESC')
			->addOrderBy('created', 'DESC');

		return $this->findEntities($qbForms);
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
