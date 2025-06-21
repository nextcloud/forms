<?php

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCA\Forms\Constants;
use OCA\Forms\Service\ConfigService;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Share\IShare;

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
		IDBConnection $db,
		private QuestionMapper $questionMapper,
		private ShareMapper $shareMapper,
		private SubmissionMapper $submissionMapper,
		private ConfigService $configService,
	) {
		parent::__construct($db, 'forms_v2_forms', Form::class);
	}


	/**
	 * @param Entity $entity
	 * @psalm-param Form $entity
	 * @return Form
	 * @throws \OCP\DB\Exception
	 */
	public function insert(Entity $entity): Form {
		$entity->setCreated(time());
		$entity->setLastUpdated(time());
		return parent::insert($entity);
	}

	/**
	 * @param Entity $entity
	 * @psalm-param Form $entity
	 * @return Form
	 * @throws \OCP\DB\Exception
	 */
	public function update(Entity $entity): Form {
		$entity->setLastUpdated(time());
		return parent::update($entity);
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
	 * @param string $queryTerm optional: The search query for universal search
	 * @return Form[]
	 */
	public function findSharedForms(string $userId, array $groups = [], array $teams = [], bool $filterShown = true, ?string $queryTerm = null): array {
		$qbShares = $this->db->getQueryBuilder();
		$qbForms = $this->db->getQueryBuilder();

		$memberships = $qbShares->expr()->orX();
		// share type user and share with current user
		$memberships->add(
			$qbShares->expr()->andX(
				$qbShares->expr()->eq('shares.share_type', $qbShares->createNamedParameter(IShare::TYPE_USER, IQueryBuilder::PARAM_STR, ':share_type_user')),
				$qbShares->expr()->eq('shares.share_with', $qbShares->createNamedParameter($userId, IQueryBuilder::PARAM_STR, ':share_with_user')),
			),
		);
		// share type group and one of the user groups
		if (!empty($groups)) {
			$memberships->add(
				$qbShares->expr()->andX(
					$qbShares->expr()->eq('shares.share_type', $qbShares->createNamedParameter(IShare::TYPE_GROUP, IQueryBuilder::PARAM_STR, ':share_type_group')),
					$qbShares->expr()->in('shares.share_with', $qbShares->createNamedParameter($groups, IQueryBuilder::PARAM_STR_ARRAY, ':share_with_groups')),
				),
			);
		}
		// share type team and one of the user teams
		if (!empty($teams)) {
			$memberships->add(
				$qbShares->expr()->andX(
					$qbShares->expr()->eq('shares.share_type', $qbShares->createNamedParameter(IShare::TYPE_CIRCLE, IQueryBuilder::PARAM_STR, ':share_type_team')),
					$qbShares->expr()->in('shares.share_with', $qbShares->createNamedParameter($teams, IQueryBuilder::PARAM_STR_ARRAY, ':share_with_teams')),
				),
			);
		}

		// build expression for publicly shared forms (default only directly shown)
		if ($this->configService->getAllowPermitAll()) {
			if ($filterShown && $this->configService->getAllowShowToAll()) {
				// Only shown forms
				$access = $qbShares->expr()->in('access_enum', $qbShares->createNamedParameter(Constants::FORM_ACCESS_ARRAY_SHOWN, IQueryBuilder::PARAM_INT_ARRAY, ':access_shown'));
			} elseif ($filterShown === false) {
				// All
				$access = $qbShares->expr()->neq('access_enum', $qbShares->createNamedParameter(Constants::FORM_ACCESS_NOPUBLICSHARE, IQueryBuilder::PARAM_INT, ':access_nopublicshare'));
			}
		}

		// Build the where clause for membership or public access
		$memberOrPublic = isset($access) ? $qbShares->expr()->orX($memberships, $access) : $memberships;

		// Select all DISTINCT IDs of shared forms
		$qbShares->selectDistinct('forms.id')
			->from($this->getTableName(), 'forms')
			->leftJoin('forms', $this->shareMapper->getTableName(), 'shares', $qbShares->expr()->eq('forms.id', 'shares.form_id'))
			->where($memberOrPublic)
			->andWhere($qbShares->expr()->neq('forms.owner_id', $qbShares->createNamedParameter($userId, IQueryBuilder::PARAM_STR, ':owner_id')));

		// Select the whole forms for the DISTINCT shared forms IDs
		$qbForms->select('*')
			->from($this->getTableName())
			->where(
				$qbForms->expr()->in('id', $qbForms->createFunction($qbShares->getSQL())),
			)
			->addOrderBy('last_updated', 'DESC')
			->addOrderBy('created', 'DESC');

		if ($queryTerm) {
			$likeParameter = '%' . $this->db->escapeLikeParameter($queryTerm) . '%';
			$qbForms->andWhere(
				$qbForms->expr()->orX(
					$qbForms->expr()->iLike('title', $qbForms->createNamedParameter($likeParameter, IQueryBuilder::PARAM_STR, ':query_term_title')),
					$qbForms->expr()->iLike('description', $qbForms->createNamedParameter($likeParameter, IQueryBuilder::PARAM_STR, ':query_term_description'))
				)
			);
		}

		// Merge parameters and parameter types from $qbShares and $qbForms
		$qbFormsParams = array_merge($qbShares->getParameters(), $qbForms->getParameters());
		$qbFormsParamTypes = array_merge($qbShares->getParameterTypes(), $qbForms->getParameterTypes());

		$qbForms->setParameters($qbFormsParams, $qbFormsParamTypes);

		return $this->findEntities($qbForms);
	}

	/**
	 * @param string $queryTerm optional: The search query for universal search
	 * @return Form[]
	 */
	public function findAllByOwnerId(string $ownerId, ?string $queryTerm = null): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('owner_id', $qb->createNamedParameter($ownerId))
			)
			//Last updated forms first, then newest forms first
			->addOrderBy('last_updated', 'DESC')
			->addOrderBy('created', 'DESC');

		if ($queryTerm) {
			$likeParameter = '%' . $this->db->escapeLikeParameter($queryTerm) . '%';
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('title', $qb->createNamedParameter($likeParameter, IQueryBuilder::PARAM_STR, ':query_term_title')),
					$qb->expr()->iLike('description', $qb->createNamedParameter($likeParameter, IQueryBuilder::PARAM_STR, ':query_term_description'))
				)
			);
		}

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
