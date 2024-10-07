<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getFormId()
 * @method void setFormId(integer $value)
 * @method string getUserId()
 * @method void setUserId(string $value)
 * @method int getTimestamp()
 * @method void setTimestamp(integer $value)
 */
class Submission extends Entity {
	protected $formId;
	protected $userId;
	protected $timestamp;

	/**
	 * Submission constructor.
	 */
	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('timestamp', 'integer');
	}

	/**
	 * @return array{
	 *     id: int,
	 *     formId: int,
	 *     userId: string,
	 *     timestamp: int,
	 * }
	 */
	public function read(): array {
		return [
			'id' => $this->getId(),
			'formId' => $this->getFormId(),
			'userId' => $this->getUserId(),
			'timestamp' => $this->getTimestamp(),
		];
	}
}
