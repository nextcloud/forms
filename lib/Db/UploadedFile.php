<?php

declare(strict_types=1);

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

use OCP\AppFramework\Db\Entity;

/**
 * @method integer getFormId()
 * @method void setFormId(integer $value)
 * @method string getOriginalFileName()
 * @method void setOriginalFileName(string $value)
 * @method integer getFileId()
 * @method void setFileId(integer $value)
 * @method integer getCreated()
 * @method void setCreated(integer $value)
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
