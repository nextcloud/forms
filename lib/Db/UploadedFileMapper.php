<?php

/**
 * @copyright Copyright (c) 2024 Kostiantyn Miakshyn <molodchick@gmail.com>
 *
 * @author Kostiantyn Miakshyn <molodchick@gmail.com>
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
