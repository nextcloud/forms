<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Forms\Db;

use OCA\Forms\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @psalm-import-type FormsAnswer from ResponseDefinitions
 * @method integer getSubmissionId()
 * @method void setSubmissionId(integer $value)
 * @method integer getQuestionId()
 * @method void setQuestionId(integer $value)
 * @method integer|null getFileId()
 * @method void setFileId(?integer $value)
 * @method string getText()
 * @method void setText(string $value)
 */
class Answer extends Entity {
	protected $submissionId;
	protected $questionId;
	protected $fileId;
	protected $text;

	/**
	 * Answer constructor.
	 */
	public function __construct() {
		$this->addType('submissionId', 'integer');
		$this->addType('questionId', 'integer');
		$this->addType('fileId', 'integer');
	}

	/**
	 * @return FormsAnswer
	 */
	public function read(): array {
		return [
			'id' => $this->getId(),
			'submissionId' => $this->getSubmissionId(),
			'fileId' => $this->getFileId(),
			'questionId' => $this->getQuestionId(),
			'text' => (string)$this->getText(),
		];
	}
}
