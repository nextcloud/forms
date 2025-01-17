<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<UploadedFile>
 */
class UploadedFileMapper extends QBMapper {

	/**
	 * AnswerMapper constructor.
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'forms_v2_uploaded_files', UploadedFile::class);
	}

	/**
	 * @param string $uploadedFileId
	 * @return UploadedFile|null
	 */
	public function findByUploadedFileId(string $uploadedFileId): ?UploadedFile {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($uploadedFileId))
			);

		return $this->findEntities($qb)[0] ?? null;
	}

	/**
	 * @param string $uploadedFileId
	 * @throws DoesNotExistException if not found
	 * @return UploadedFile
	 */
	public function getByUploadedFileId(string $uploadedFileId): UploadedFile {
		$uploadedFile = $this->findByUploadedFileId($uploadedFileId);
		if ($uploadedFile === null) {
			throw new DoesNotExistException(sprintf('Uploaded file with id "%s" not found', $uploadedFileId));
		}

		return $uploadedFile;
	}

	/**
	 * @param \DateTimeImmutable $dateTime
	 * @return UploadedFile[]
	 */
	public function findUploadedEarlierThan(\DateTimeImmutable $dateTime): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->lt('created', $qb->createNamedParameter($dateTime->getTimestamp()))
			);

		return $this->findEntities($qb);
	}
}
