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
 * @method integer getQuestionId()
 * @method void setQuestionId(integer $value)
 * @method string getText()
 * @method void setText(string $value)
 */
class Option extends Entity {

	/** @var int */
	protected $questionId;
	/** @var string */
	protected $text;

	/**
	 * Option constructor.
	 */
	public function __construct() {
		$this->addType('questionId', 'integer');
		$this->addType('text', 'string');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'questionId' => $this->getQuestionId(),
			'text' => htmlspecialchars_decode($this->getText()),
		];
	}
}
