<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFormId()
 * @method void setFormId(int $value)
 * @method string getOriginalFileName()
 * @method void setOriginalFileName(string $value)
 * @method int getFileId()
 * @method void setFileId(int $value)
 * @method int getCreated()
 * @method void setCreated(int $value)
 */
class UploadedFile extends Entity {
	protected $formId;
	protected $originalFileName;
	protected $fileId;
	protected $created;

	/**
	 * Answer constructor.
	 */
	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('originalFileName', 'string');
		$this->addType('fileId', 'integer');
		$this->addType('created', 'integer');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'formId' => $this->getFormId(),
			'originalFileName' => $this->getOriginalFileName(),
			'fileId' => $this->getFileId(),
			'created' => $this->getCreated(),
		];
	}
}
