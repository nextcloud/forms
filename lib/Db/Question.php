<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
 *
 * @author Inigo Jiron <ijiron@terpmail.umd.edu>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Forms\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method integer getFormId()
 * @method void setFormId(integer $value)
 * @method string getFormQuestionType()
 * @method void setFormQuestionType(string $value)
 * @method string getFormQuestionText()
 * @method void setFormQuestionText(string $value)
 * @method integer getTimestamp()
 * @method void setTimestamp(integer $value)
 */
class Question extends Entity {
	protected $formId;
	protected $formQuestionType;
	protected $formQuestionText;
	protected $timestamp;

	/**
	 * Question constructor.
	 */
	public function __construct() {
		$this->addType('formId', 'integer');
		$this->addType('timestamp', 'integer');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'formId' => $this->getFormId(),
			'type' => htmlspecialchars_decode($this->getFormQuestionType()),
			'text' => htmlspecialchars_decode($this->getFormQuestionText()),
			'timestamp' => $this->getTimestamp()
		];
	}





}
