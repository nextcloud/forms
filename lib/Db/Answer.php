<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Jonas Rittershofer <jotoeri@users.noreply.github.com>
 *
 * @author affan98 <affan98@gmail.com>
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
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
 * @method integer getSubmissionId()
 * @method void setSubmissionId(integer $value)
 * @method integer getQuestionId()
 * @method void setQuestionId(integer $value)
 * @method string getText()
 * @method void setText(string $value)
 */
class Answer extends Entity {
	protected $submissionId;
	protected $questionId;
	protected $text;

	/**
	 * Answer constructor.
	 */
	public function __construct() {
		$this->addType('submissionId', 'integer');
		$this->addType('questionId', 'integer');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'submissionId' => $this->getSubmissionId(),
			'questionId' => $this->getQuestionId(),
			'text' => htmlspecialchars_decode($this->getText()),
		];
	}
}
