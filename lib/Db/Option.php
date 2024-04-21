<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Inigo Jiron <ijiron@terpmail.umd.edu>
 *
 * @author Jonas Rittershofer <jotoeri@users.noreply.github.com>
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
 * @method int|float getQuestionId()
 * @method void setQuestionId(int|float $value)
 * @method string getText()
 * @method void setText(string $value)
 * @method int getOrder();
 * @method void setOrder(int $value)
 */
class Option extends Entity {

	// For 32bit PHP long integers, like IDs, are represented by floats
	protected int|float|null $questionId;
	protected ?string $text;
	protected ?int $order;

	/**
	 * Option constructor.
	 */
	public function __construct() {
		$this->questionId = null;
		$this->text = null;
		$this->order = null;
		$this->addType('questionId', 'integer');
		$this->addType('order', 'integer');
		$this->addType('text', 'string');
	}

	public function read(): array {
		return [
			'id' => $this->getId(),
			'questionId' => $this->getQuestionId(),
			'order' => $this->getOrder(),
			'text' => (string)$this->getText(),
		];
	}
}
