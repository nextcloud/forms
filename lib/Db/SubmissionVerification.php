<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getSubmissionId()
 * @method void setSubmissionId(int $value)
 * @method string getRecipientEmailHash()
 * @method void setRecipientEmailHash(string $value)
 * @method string getTokenHash()
 * @method void setTokenHash(string $value)
 * @method int getExpires()
 * @method void setExpires(int $value)
 * @method int|null getUsed()
 * @method void setUsed(?int $value)
 */
class SubmissionVerification extends Entity {
	protected $submissionId;
	protected $recipientEmailHash;
	protected $tokenHash;
	protected $expires;
	protected $used;

	public function __construct() {
		$this->addType('submissionId', 'integer');
		$this->addType('expires', 'integer');
		$this->addType('used', 'integer');
	}

	/**
	 * @return array{
	 *   id: int,
	 *   submissionId: int,
	 *   recipientEmailHash: string,
	 *   tokenHash: string,
	 *   expires: int,
	 *   used: int|null,
	 * }
	 */
	public function read(): array {
		return [
			'id' => $this->getId(),
			'submissionId' => $this->getSubmissionId(),
			'recipientEmailHash' => $this->getRecipientEmailHash(),
			'tokenHash' => $this->getTokenHash(),
			'expires' => $this->getExpires(),
			'used' => $this->getUsed(),
		];
	}
}
